<?php
/**
 * Prospect Controller
 * Core Extraction, Smart Caching, Data Table Management & Export
 * Gmap Prospek
 */

namespace App\Controllers;

use App\Core\Controller;
use App\Models\ProspectModel;
use App\Services\ScraperService;

class ProspectController extends Controller
{
    protected ProspectModel $prospectModel;
    protected ScraperService $scraperService;

    public function __construct()
    {
        $this->prospectModel = new ProspectModel();
        $this->scraperService = new ScraperService();
    }

    /**
     * Main Dashboard View - Redirected to Projects
     */
    public function index(): void
    {
        $this->requireAuth();
        $this->redirect('projects');
    }

    /**
     * AJAX Search API Endpoint
     */
    public function search(): void
    {
        $this->requireAuth();

        $this->validateCsrf();

        // Search can write cache and must use POST.
        $keyword = trim($_POST['keyword'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $limit = (int)($_POST['limit'] ?? 20);
        $limit = max(5, min(100, $limit));
        $forceRefresh = !empty($_POST['force_refresh']);

        if (empty($keyword) || empty($location)) {
            $this->json([
                'success' => false,
                'message' => 'Katakunci pencarian dan Lokasi wajib diisi.'
            ], 422);
            return;
        }

        $result = $this->executeSearch($keyword, $location, $limit, $forceRefresh);

        $this->json([
            'success' => true,
            'data' => $result['items'],
            'total' => count($result['items']),
            'is_cached' => $result['is_cached'],
            'execution_time' => $result['execution_time'],
            'keyword' => $keyword,
            'location' => $location,
            'message' => $result['is_cached']
                ? "⚡ Berhasil memuat data dari Cache Database dalam {$result['execution_time']} detik!"
                : "🌐 Berhasil mengekstrak {$result['total']} prospek riil langsung dari Google Maps dalam {$result['execution_time']} detik!"
        ]);
    }

    /**
     * Search execution core logic with Smart Database Caching (PRD Section 2 & 4)
     * 
     * @param string $keyword
     * @param string $location
     * @param int $limit
     * @param bool $forceRefresh
     * @return array
     */
    protected function executeSearch(string $keyword, string $location, int $limit, bool $forceRefresh = false): array
    {
        $startTime = microtime(true);

        // 1. Check Smart Database Cache (PRD Section 2) unless force refresh requested
        if (!$forceRefresh) {
            $cached = $this->prospectModel->getCachedProspects($keyword, $location, CACHE_EXPIRY_HOURS);
            $cached = \App\Services\LeadQualification::filter($cached ?? [], $limit);
            if (count($cached) >= $limit) {
                $endTime = microtime(true);
                $duration = round($endTime - $startTime, 4);

                return [
                    'items' => array_slice($cached, 0, $limit),
                    'is_cached' => true,
                    'execution_time' => $duration,
                    'total' => count($cached)
                ];
            }
        }

        // 2. Not in cache or force refresh -> Run Python/Selenium scraper
        $scrapeResult = $this->scraperService->scrapePotential($keyword, $location, $limit);
        if (empty($scrapeResult['success'])) {
            $this->json(['success' => false, 'message' => $scrapeResult['message']], 502);
        }
        $items = $scrapeResult['items'];

        // 3. Save automatically to MySQL Smart Cache (PRD Section 4: Data Flow step 5)
        if (!empty($items)) {
            $this->prospectModel->saveScrapedProspects($keyword, $location, $items);
            
            // Reload from database cache so every item contains its real MySQL primary key 'id'
            $cachedItems = $this->prospectModel->getCachedProspects($keyword, $location, CACHE_EXPIRY_HOURS);
            if (!empty($cachedItems)) {
                $items = \App\Services\LeadQualification::filter($cachedItems, $limit);
            }
        }

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 3);

        return [
            'items' => $items,
            'is_cached' => false,
            'execution_time' => $duration,
            'total' => count($items)
        ];
    }

    /**
     * Export prospects to Excel / CSV format (PRD Section 2)
     */
    public function export(): void
    {
        $this->requireAuth();

        $keyword = trim(filter_input(INPUT_GET, 'keyword', FILTER_DEFAULT) ?? '');
        $location = trim(filter_input(INPUT_GET, 'location', FILTER_DEFAULT) ?? '');
        $idsParam = trim(filter_input(INPUT_GET, 'ids', FILTER_DEFAULT) ?? '');

        // Fetch prospects to export
        if (!empty($idsParam)) {
            $ids = array_map('intval', explode(',', $idsParam));
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $db = \App\Core\Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM prospect_cache WHERE id IN ({$placeholders}) ORDER BY id ASC");
            $stmt->execute($ids);
            $prospects = $stmt->fetchAll();
        } elseif (!empty($keyword) && !empty($location)) {
            $searchKey = ProspectModel::makeSearchKey($keyword, $location);
            $db = \App\Core\Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM prospect_cache WHERE search_key = :key ORDER BY rating DESC, reviews_count DESC");
            $stmt->bindValue(':key', $searchKey);
            $stmt->execute();
            $prospects = $stmt->fetchAll();
        } else {
            // Export all recent cached records
            $prospects = $this->prospectModel->getAll(null, 'created_at', 'DESC', 500, 0);
        }

        if (empty($prospects)) {
            set_flash('error', 'Tidak ada data prospek yang dapat diekspor.');
            $this->redirect('projects');
            return;
        }

        // Clean filename
        $slug = !empty($keyword) ? preg_replace('/[^a-zA-Z0-9]/', '_', strtolower("{$keyword}_{$location}")) : 'semua_prospek';
        $filename = "prospek_gmap_{$slug}_" . date('Ymd_His') . ".csv";

        // Headers for CSV file download
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'w');

        // Output UTF-8 BOM for Microsoft Excel compatibility (PRD Section 2)
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        // Column Headers
        fputcsv($output, [
            'No',
            'Nama Toko / Bisnis',
            'Kategori',
            'Alamat Lengkap',
            'Nomor HP / WA',
            'Format WhatsApp (Link)',
            'Rating',
            'Jumlah Ulasan',
            'Website',
            'Link Google Maps',
            'Tanggal Ekstraksi'
        ]);

        $no = 1;
        foreach ($prospects as $row) {
            $waLink = !empty($row['whatsapp']) ? "https://wa.me/{$row['whatsapp']}" : '';
            fputcsv($output, [
                $no++,
                $row['shop_name'],
                $row['category'] ?? '-',
                $row['address'] ?? '-',
                // Prepend apostrophe to prevent Excel scientific notation on phone numbers
                !empty($row['phone']) ? "'" . $row['phone'] : '-',
                $waLink,
                $row['rating'],
                $row['reviews_count'],
                $row['website'] ?? '-',
                $row['gmap_url'] ?? '-',
                $row['created_at']
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * Delete individual prospect
     */
    public function delete(): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $id = (int)filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id > 0) {
            $this->prospectModel->deleteById($id);
            set_flash('success', 'Data prospek berhasil dihapus dari cache.');
        }

        $this->redirect('projects');
    }

    /**
     * Clear expired cache older than 24 hours
     */
    public function clearCache(): void
    {
        $this->requireAuth();
        $this->validateCsrf();

        $deleted = $this->prospectModel->clearCache();
        set_flash('info', "Berhasil membersihkan {$deleted} data cache lama.");
        $this->redirect('projects');
    }
}
