<?php
/**
 * Project Controller
 * Campaign Management & Project Leads Workspace
 * Gmap Prospek
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProjectModel;
use App\Models\ProspectModel;
use App\Services\ScraperService;

class ProjectController extends Controller
{
    protected ProjectModel $projectModel;
    protected ProspectModel $prospectModel;
    protected ScraperService $scraperService;

    public function __construct()
    {
        $this->projectModel = new ProjectModel();
        $this->prospectModel = new ProspectModel();
        $this->scraperService = new ScraperService();
    }

    /**
     * Projects List & Campaign Dashboard
     */
    public function index(): void
    {
        $this->requireAuth();

        $projects = $this->projectModel->getAllProjects();

        // Calculate aggregate data stats across all projects
        $totalLeads = 0;
        $totalPhones = 0;
        $totalWebsites = 0;
        $totalRatings = 0;

        foreach ($projects as $p) {
            $totalLeads += (int)($p['count_total'] ?? 0);
            $totalPhones += (int)($p['count_phones'] ?? 0);
            $totalWebsites += (int)($p['count_websites'] ?? 0);
            $totalRatings += (int)($p['count_with_ratings'] ?? 0);
        }

        $this->view('project/index', [
            'pageTitle' => 'Manajemen Proyek - Gmap Prospek',
            'projects' => $projects,
            'stats' => [
                'total_projects' => count($projects),
                'total_leads' => $totalLeads,
                'total_phones' => $totalPhones,
                'total_websites' => $totalWebsites,
                'total_ratings' => $totalRatings
            ]
        ]);
    }

    /**
     * Dedicated Project Workspace / Leads Table
     * 
     * @param int|string $id
     */
    public function viewProject($id = null): void
    {
        $this->requireAuth();

        $projectId = (int)$id;
        if ($projectId <= 0) {
            $this->redirect('projects');
            return;
        }

        $project = $this->projectModel->getProjectById($projectId);
        if (!$project) {
            set_flash('error', 'Proyek yang Anda cari tidak ditemukan.');
            $this->redirect('projects');
            return;
        }

        $search = trim(filter_input(INPUT_GET, 'q', FILTER_DEFAULT) ?? '');
        $sort = trim(filter_input(INPUT_GET, 'sort', FILTER_DEFAULT) ?? 'rating_desc');

        // Fetch all leads for this project
        $leads = $this->projectModel->getProjectLeads($projectId, null, $search, $sort);

        $this->view('project/view', [
            'pageTitle' => e($project['name']) . ' - Gmap Prospek',
            'project' => $project,
            'leads' => $leads,
            'duplicates' => (new \App\Models\SalesModel())->duplicates($leads),
            'search' => $search,
            'sort' => $sort
        ]);
    }

    /**
     * Create a new project and scrape real Google Maps data
     */
    public function create(): void
    {
        $this->requireAuth();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->validateCsrf();

            $keyword = trim(filter_input(INPUT_POST, 'keyword', FILTER_DEFAULT) ?? '');
            $location = trim(filter_input(INPUT_POST, 'location', FILTER_DEFAULT) ?? '');
            $projectName = trim(filter_input(INPUT_POST, 'name', FILTER_DEFAULT) ?? '');
            $limit = (int)(filter_input(INPUT_POST, 'limit', FILTER_VALIDATE_INT) ?: 20);
            $limit = max(5, min(100, $limit));
            if (empty($keyword) || empty($location)) {
                set_flash('error', 'Katakunci pencarian dan Lokasi wajib diisi.');
                $this->redirect('projects');
                return;
            }

            if (empty($projectName)) {
                $projectName = "Proyek " . ucwords($keyword) . " - " . ucwords($location);
            }

            try {
                // Always qualify cached records before creating a project.
                $cached = $this->prospectModel->getCachedProspects($keyword, $location, CACHE_EXPIRY_HOURS) ?? [];
                $items = \App\Services\LeadQualification::filter($cached, $limit);
                if (count($items) < $limit) {
                    $result = $this->scraperService->scrapePotential($keyword, $location, $limit);
                    if (!empty($result['success'])) {
                        $items = $result['items'];
                        if ($items) $this->prospectModel->saveScrapedProspects($keyword, $location, $items);
                    }
                    // On extraction failure, qualified fresh cache remains usable.
                }

                if (empty($items)) {
                    set_flash('error', $result['message'] ?? 'Tidak ada prospek yang berhasil diperoleh. Proyek belum dibuat.');
                    $this->redirect('projects');
                    return;
                }

                // 3. Create isolated project with these leads
                $items = array_slice($items, 0, $limit);
                $projectId = $this->projectModel->createProject($projectName, $keyword, $location, $items);
            } catch (\PDOException $e) {
                error_log('Project creation storage: ' . $e->getMessage());
                set_flash('error', 'Proyek belum berhasil disimpan. Data sebelumnya tetap aman. Coba lagi.');
                $this->redirect('projects');
                return;
            }

            $criteria = ' (memiliki kontak)';
            set_flash('success', "Proyek '{$projectName}' berhasil dibuat dengan " . count($items) . " prospek dari target {$limit}" . $criteria . '.');
            $this->redirect("projects/viewProject/{$projectId}");
            return;
        }

        $this->redirect('projects');
    }

    /**
     * AJAX endpoint to update lead status
     */
    public function updateStatus(): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $leadId = (int)($_POST['lead_id'] ?? 0);
        $status = trim($_POST['status'] ?? '');
        $notes = isset($_POST['notes']) ? trim($_POST['notes']) : null;

        if ($leadId <= 0 || empty($status)) {
            $this->json(['success' => false, 'message' => 'Parameter tidak valid'], 422);
            return;
        }

        $ok = $this->projectModel->updateLeadStatus($leadId, $status, $notes);
        if ($ok) {
            $this->json([
                'success' => true, 
                'message' => 'Status prospek berhasil diperbarui!',
                'status' => $status
            ]);
        } else {
            $this->json(['success' => false, 'message' => 'Gagal memperbarui status'], 500);
        }
    }

    /**
     * Export leads for a single project to Excel-compatible CSV
     * 
     * @param int|string $id
     */
    public function export($id = null): void
    {
        $this->requireAuth();

        $projectId = (int)$id;
        $project = $this->projectModel->getProjectById($projectId);
        if (!$project) {
            set_flash('error', 'Proyek tidak ditemukan.');
            $this->redirect('projects');
            return;
        }

        $leads = $this->projectModel->getExportLeads($projectId, array_key_exists('ids', $_GET) ? $_GET['ids'] : null);
        if (empty($leads)) {
            set_flash('error', 'Tidak ada prospek dalam proyek ini untuk diekspor.');
            $this->redirect("projects/viewProject/{$projectId}");
            return;
        }

        $slug = preg_replace('/[^a-zA-Z0-9]/', '_', strtolower($project['name']));
        $filename = "prospek_{$slug}_" . date('Ymd_His') . ".csv";

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // UTF-8 BOM for Microsoft Excel
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, [
            'No',
            'Nama Toko / Bisnis',
            'Kategori',
            'Alamat Lengkap',
            'Nomor HP',
            'Format WhatsApp (Link)',
            'Status WhatsApp',
            'Rating',
            'Jumlah Ulasan',
            'Status Lead',
            'Catatan Follow-Up',
            'Website',
            'Link Google Maps',
            'Tanggal Ditambahkan'
        ]);

        $no = 1;
        foreach ($leads as $r) {
            $waLink = !empty($r['whatsapp']) ? "https://wa.me/{$r['whatsapp']}" : '';
            fputcsv($output, array_map(static function ($value) {
                $text = (string)$value;
                return preg_match('/^[\s]*[=+@\-\t\r]/u', $text) ? "'" . $text : $text;
            }, [
                $no++,
                $r['shop_name'],
                $r['category'] ?? '-',
                $r['address'] ?? '-',
                !empty($r['phone']) ? "'" . $r['phone'] : '-',
                $waLink,
                ['active' => 'Aktif', 'inactive' => 'Tidak aktif', 'unknown' => 'Belum pasti'][$r['wa_status'] ?? ''] ?? 'Belum dicek',
                $r['rating'],
                $r['reviews_count'],
                ucfirst($r['lead_status'] ?? 'Baru'),
                $r['notes'] ?? '-',
                $r['website'] ?? '-',
                $r['gmap_url'] ?? '-',
                $r['created_at']
            ]));
        }

        fclose($output);
        exit;
    }

    /**
     * Delete an entire project and its leads
     * 
     * @param int|string $id
     */
    public function delete($id = null): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $projectId = (int)($_POST['id'] ?? $id ?? 0);
        if ($projectId > 0) {
            try { $this->projectModel->deleteProject($projectId); }
            catch (\DomainException $e) {
                if ($this->isAjax()) $this->json(['success'=>false,'message'=>$e->getMessage()],409);
                set_flash('error',$e->getMessage());$this->redirect('projects');
            }
            set_flash('success', 'Proyek beserta seluruh data prospeknya berhasil dihapus.');
        }

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'message' => 'Proyek berhasil dihapus.',
                'redirect' => base_url('projects')
            ]);
            return;
        }

        $this->redirect('projects');
    }

    /**
     * Delete a single lead from a project
     */
    public function deleteLead(): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $leadId = (int)($_POST['lead_id'] ?? 0);
        $projectId = (int)($_POST['project_id'] ?? 0);

        if ($leadId > 0) {
            try { $this->projectModel->deleteLead($leadId); }
            catch (\DomainException $e) {
                if ($this->isAjax()) $this->json(['success'=>false,'message'=>$e->getMessage()],409);
                set_flash('error',$e->getMessage());$this->redirect("projects/viewProject/{$projectId}");
            }
            set_flash('success', 'Prospek berhasil dihapus dari proyek ini.');
        }

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'message' => 'Prospek berhasil dihapus.'
            ]);
            return;
        }

        $this->redirect("projects/viewProject/{$projectId}");
    }

    /**
     * Move selected leads to CRM
     */
    public function moveToCrm(): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $idsInput = $_POST['ids'] ?? [];
        if (is_string($idsInput)) {
            $idsInput = explode(',', $idsInput);
        }

        $leadIds = array_filter(array_map('intval', (array)$idsInput), fn($id) => $id > 0);

        if (empty($leadIds)) {
            if ($this->isAjax()) {
                $this->json(['success' => false, 'message' => 'Pilih setidaknya 1 data prospek untuk dipindahkan ke CRM.'], 400);
                return;
            }
            set_flash('error', 'Pilih setidaknya 1 data prospek untuk dipindahkan ke CRM.');
            $projectId = (int)($_POST['project_id'] ?? 0);
            $this->redirect($projectId > 0 ? "projects/viewProject/{$projectId}" : 'projects');
            return;
        }

        $crmModel = new \App\Models\CrmModel();
        $updatedCount = $crmModel->moveToCrm($leadIds);

        if ($this->isAjax()) {
            $this->json([
                'success' => true,
                'count' => $updatedCount,
                'lead_ids' => array_values($leadIds),
                'message' => "{$updatedCount} prospek berhasil dipindahkan ke CRM!"
            ]);
            return;
        }

        set_flash('success', "{$updatedCount} data prospek berhasil dipindahkan ke CRM.");
        $projectId = (int)($_POST['project_id'] ?? 0);
        $this->redirect($projectId > 0 ? "projects/viewProject/{$projectId}" : 'crm');
    }

    /**
     * Check whether lead phone numbers have an active WhatsApp account (AJAX, small batches)
     */
    public function checkWhatsapp(): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $projectId = (int)($_POST['project_id'] ?? 0);
        $idsInput = $_POST['ids'] ?? [];
        if (is_string($idsInput)) {
            $idsInput = explode(',', $idsInput);
        }
        // Keep each request short; the page sends the rest in follow-up batches.
        $leadIds = array_slice(array_filter(array_map('intval', (array)$idsInput), fn($id) => $id > 0), 0, 10);
        if ($projectId <= 0 || empty($leadIds)) {
            $this->json(['success' => false, 'message' => 'Pilih prospek yang ingin dicek.'], 400);
            return;
        }

        try {
            $credentials = (new \App\Models\GatewayModel())->credentials();
        } catch (\InvalidArgumentException $e) {
            $this->json(['success' => false, 'message' => 'Pengecekan WhatsApp memerlukan WhatsApp Gateway (xSender atau Starsender). Lengkapi dan aktifkan di menu Pengaturan.', 'settings_url' => base_url('settings')], 422);
            return;
        }

        $gateway = \App\Models\GatewayModel::service($credentials['provider']);
        $label = \App\Models\GatewayModel::label($credentials['provider']);
        $gatewayHealthy = null;
        $results = [];
        foreach ($this->projectModel->getLeadsByIds($projectId, $leadIds) as $lead) {
            $raw = $lead['whatsapp'] ?: ($lead['phone'] ?? '');
            if (trim((string)$raw) === '') {
                $results[] = ['id' => (int)$lead['id'], 'status' => 'no_phone'];
                continue;
            }
            try {
                $number = \App\Models\GatewayModel::phone((string)$raw);
            } catch (\InvalidArgumentException $e) {
                // A malformed number cannot hold a WhatsApp account.
                $this->projectModel->updateWhatsappStatus((int)$lead['id'], 'inactive');
                $results[] = ['id' => (int)$lead['id'], 'status' => 'inactive'];
                continue;
            }

            $check = $gateway->checkNumber($credentials, $number);
            if (!empty($check['needs_gateway_check'])) {
                // The sender's own number is always registered; if it checks out, the failure is about the target number.
                $gatewayHealthy ??= $gateway->checkNumber($credentials, (string)$credentials['sender'])['status'] === 'active';
                if (!$gatewayHealthy) {
                    $check['diagnostic'] = 'Perangkat WhatsApp pengirim di ' . $label . ' tidak terhubung atau tidak merespons.';
                }
            }
            if (!empty($check['diagnostic'])) {
                // Configuration or connection problems apply to every number; stop instead of marking all unknown.
                $this->json(['success' => false, 'message' => $check['diagnostic'] . ' Periksa pengaturan ' . $label . ' lalu coba lagi.', 'results' => $results], 502);
                return;
            }
            $this->projectModel->updateWhatsappStatus((int)$lead['id'], $check['status']);
            $results[] = ['id' => (int)$lead['id'], 'status' => $check['status']];
        }

        $this->json(['success' => true, 'results' => $results]);
    }

    /**
     * Synchronize and refresh real-time ratings from Google Maps
     */
    public function syncRatingRealtime(): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $projectId = (int)($_POST['project_id'] ?? 0);
        if ($projectId <= 0) {
            $this->json(['success' => false, 'message' => 'ID proyek tidak valid.'], 400);
            return;
        }

        $project = $this->projectModel->getProjectById($projectId);
        if (!$project) {
            $this->json(['success' => false, 'message' => 'Proyek tidak ditemukan.'], 404);
            return;
        }

        $leads = $this->projectModel->getProjectLeads($projectId);
        if (empty($leads)) {
            $this->json(['success' => false, 'message' => 'Tidak ada data prospek dalam proyek ini.'], 400);
            return;
        }

        $targetLimit = max(20, min(100, count($leads)));
        // Scrape real-time fresh data
        $result = $this->scraperService->scrape($project['keyword'], $project['location'], $targetLimit);
        $scrapedItems = $result['items'] ?? [];

        if (empty($scrapedItems)) {
            $this->json(['success' => false, 'message' => 'Gagal mengambil data rating realtime dari Google Maps.'], 500);
            return;
        }

        // Match stable Maps identities; never use names or result positions.
        $itemsMap = \App\Services\MapsIdentity::index($scrapedItems);
        $updatedLeads = [];
        $updateCount = 0;
        foreach ($leads as $lead) {
            $key = \App\Services\MapsIdentity::key($lead['gmap_url'] ?? null);
            $matched = $key !== null ? ($itemsMap[$key] ?? null) : null;
            // Zero is also the scraper's unknown-rating marker; do not overwrite with it.
            if ($matched && ((float)($matched['rating'] ?? 0) <= 0 || (float)$matched['rating'] > 5 || (int)($matched['reviews_count'] ?? 0) <= 0)) {
                $matched = null;
            }

            if ($matched) {
                $newRating = (float)$matched['rating'];
                $newReviews = (int)$matched['reviews_count'];
                $this->projectModel->updateLeadRating($lead['id'], $newRating, $newReviews);
                $updatedLeads[] = [
                    'id' => $lead['id'],
                    'rating' => $newRating,
                    'reviews_count' => $newReviews,
                    'rating_display' => number_format($newRating, 1)
                ];
                $updateCount++;
            }
        }

        // Update cache as well
        $this->prospectModel->saveScrapedProspects($project['keyword'], $project['location'], $scrapedItems);

        $this->json([
            'success' => true,
            'message' => "Rating diperbarui untuk {$updateCount} prospek. Data yang tidak cocok atau belum terbaca dilewati.",
            'count' => $updateCount,
            'skipped' => count($leads) - $updateCount,
            'updated_leads' => $updatedLeads
        ]);
    }
}

