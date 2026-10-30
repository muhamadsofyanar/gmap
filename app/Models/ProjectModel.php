<?php
/**
 * Project Model
 * Campaign Management & Project Leads CRM
 * Gmap Prospek
 */

namespace App\Models;

use App\Core\Database;
use PDO;

class ProjectModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
        \App\Services\WhatsAppCheckMigration::apply($this->db);
    }

    /**
     * Create a new project and save its scraped leads
     * 
     * @param string $name
     * @param string $keyword
     * @param string $location
     * @param array $leads
     * @return int Created project ID
     */
    public function createProject(string $name, string $keyword, string $location, array $leads): int
    {
        if (empty($leads)) {
            throw new \InvalidArgumentException('Proyek harus memiliki setidaknya satu prospek hasil pencarian.');
        }
        $this->db->beginTransaction();
        try {
            // 1. Insert into projects
            $stmt = $this->db->prepare("
                INSERT INTO projects (name, keyword, location, total_leads, status, created_at)
                VALUES (:name, :keyword, :location, :total, 'active', NOW())
            ");
            $stmt->bindValue(':name', trim($name), PDO::PARAM_STR);
            $stmt->bindValue(':keyword', trim($keyword), PDO::PARAM_STR);
            $stmt->bindValue(':location', trim($location), PDO::PARAM_STR);
            $stmt->bindValue(':total', count($leads), PDO::PARAM_INT);
            $stmt->execute();

            $projectId = (int)$this->db->lastInsertId();

            // 2. Insert leads
            if (!empty($leads)) {
                $leadStmt = $this->db->prepare("
                    INSERT INTO project_leads (
                        project_id, shop_name, category, address, phone, whatsapp, 
                        rating, reviews_count, opening_hours, website, gmap_url, lead_status, created_at
                    ) VALUES (
                        :project_id, :shop_name, :category, :address, :phone, :whatsapp, 
                        :rating, :reviews_count, :opening_hours, :website, :gmap_url, 'new', NOW()
                    )
                ");

                foreach ($leads as $item) {
                    $phone = !empty($item['phone']) ? trim($item['phone']) : null;
                    $whatsapp = !empty($item['whatsapp']) ? trim($item['whatsapp']) : format_whatsapp($phone);

                    $leadStmt->bindValue(':project_id', $projectId, PDO::PARAM_INT);
                    $leadStmt->bindValue(':shop_name', trim($item['shop_name'] ?? 'Bisnis Tanpa Nama'), PDO::PARAM_STR);
                    $leadStmt->bindValue(':category', $item['category'] ?? $keyword, PDO::PARAM_STR);
                    $leadStmt->bindValue(':address', $item['address'] ?? null, PDO::PARAM_STR);
                    $leadStmt->bindValue(':phone', $phone, PDO::PARAM_STR);
                    $leadStmt->bindValue(':whatsapp', $whatsapp ?: null, PDO::PARAM_STR);
                    $leadStmt->bindValue(':rating', (float)($item['rating'] ?? 0.0));
                    $leadStmt->bindValue(':reviews_count', (int)($item['reviews_count'] ?? 0), PDO::PARAM_INT);
                    $leadStmt->bindValue(':opening_hours', $item['opening_hours'] ?? null, PDO::PARAM_STR);
                    $leadStmt->bindValue(':website', $item['website'] ?? null, PDO::PARAM_STR);
                    $leadStmt->bindValue(':gmap_url', $item['gmap_url'] ?? null, PDO::PARAM_STR);
                    $leadStmt->execute();
                }
            }

            $this->db->commit();
            return $projectId;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get all projects with lead status aggregations
     * 
     * @return array
     */
    public function getAllProjects(): array
    {
        $sql = "
            SELECT p.*,
                   COUNT(l.id) AS count_total,
                   SUM(CASE WHEN l.phone IS NOT NULL AND l.phone != '' THEN 1 ELSE 0 END) AS count_phones,
                   SUM(CASE WHEN l.website IS NOT NULL AND l.website != '' THEN 1 ELSE 0 END) AS count_websites,
                   SUM(CASE WHEN (l.website IS NULL OR l.website = '') AND l.phone IS NOT NULL AND l.phone != '' THEN 1 ELSE 0 END) AS count_gold_targets,
                   SUM(CASE WHEN l.rating > 0 THEN 1 ELSE 0 END) AS count_with_ratings
            FROM projects p
            LEFT JOIN project_leads l ON p.id = l.project_id
            GROUP BY p.id
            ORDER BY p.id DESC
        ";
        return $this->db->query($sql)->fetchAll();
    }

    /**
     * Get single project by ID
     * 
     * @param int $id
     * @return array|null
     */
    public function getProjectById(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM projects WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $p = $stmt->fetch();
        return $p ?: null;
    }

    /**
     * Get leads for a specific project
     * 
     * @param int $projectId
     * @param string|null $statusFilter
     * @param string|null $search
     * @param string|null $sort
     * @return array
     */
    public function getProjectLeads(int $projectId, ?string $statusFilter = null, ?string $search = null, ?string $sort = null): array
    {
        $conditions = ["project_id = :pid"];
        $params = [':pid' => $projectId];

        if (!empty($statusFilter) && $statusFilter !== 'all') {
            $conditions[] = "lead_status = :status";
            $params[':status'] = $statusFilter;
        }

        if (!empty($search)) {
            $conditions[] = "(shop_name LIKE :kw1 OR address LIKE :kw2 OR phone LIKE :kw3 OR website LIKE :kw4)";
            $term = '%' . trim($search) . '%';
            $params[':kw1'] = $term;
            $params[':kw2'] = $term;
            $params[':kw3'] = $term;
            $params[':kw4'] = $term;
        }

        $orderBy = "rating DESC, reviews_count DESC, id ASC";
        if ($sort === 'rating_asc') {
            $orderBy = "rating ASC, reviews_count ASC, id ASC";
        } elseif ($sort === 'rating_desc') {
            $orderBy = "rating DESC, reviews_count DESC, id ASC";
        } elseif ($sort === 'reviews_desc') {
            $orderBy = "reviews_count DESC, rating DESC, id ASC";
        } elseif ($sort === 'reviews_asc') {
            $orderBy = "reviews_count ASC, rating ASC, id ASC";
        } elseif ($sort === 'name_asc') {
            $orderBy = "shop_name ASC";
        } elseif ($sort === 'name_desc') {
            $orderBy = "shop_name DESC";
        }

        $where = implode(' AND ', $conditions);
        $sql = "SELECT * FROM project_leads WHERE {$where} ORDER BY {$orderBy}";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $type = is_int($v) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($k, $v, $type);
        }
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Update rating & review count of a single lead
     * 
     * @param int $leadId
     * @param float $rating
     * @param int $reviewsCount
     * @return bool
     */
    public function getExportLeads(int $projectId, $idsInput = null): array
    {
        $leads = $this->getProjectLeads($projectId);
        // No selection parameter means an explicit full-project export.
        if ($idsInput === null) return $leads;
        // Empty or malformed selections must never fall back to exporting everything.
        if (!is_string($idsInput) || !preg_match('/^[1-9][0-9]*(,[1-9][0-9]*)*$/D', $idsInput)) return [];
        $ids = array_fill_keys(array_map('intval', explode(',', $idsInput)), true);
        return array_values(array_filter($leads, fn($lead) => isset($ids[(int)$lead['id']])));
    }

    public function updateLeadRating(int $leadId, float $rating, int $reviewsCount): bool
    {
        $stmt = $this->db->prepare("UPDATE project_leads SET rating = :rating, reviews_count = :reviews_count WHERE id = :id");
        return $stmt->execute([
            ':rating' => $rating,
            ':reviews_count' => $reviewsCount,
            ':id' => $leadId
        ]);
    }

    /**
     * Leads of one project restricted to the given IDs (ownership check for batch actions)
     */
    public function getLeadsByIds(int $projectId, array $ids): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), fn($id) => $id > 0)));
        if (!$ids) return [];
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare("SELECT id, phone, whatsapp FROM project_leads WHERE project_id = ? AND id IN ($marks)");
        $stmt->execute(array_merge([$projectId], $ids));
        return $stmt->fetchAll();
    }

    public function updateWhatsappStatus(int $leadId, string $status): bool
    {
        if (!in_array($status, ['active', 'inactive', 'unknown'], true)) return false;
        $stmt = $this->db->prepare("UPDATE project_leads SET wa_status = :status, wa_checked_at = NOW() WHERE id = :id");
        return $stmt->execute([':status' => $status, ':id' => $leadId]);
    }

    /**
     * Update lead status (e.g. new -> contacted -> interested -> closed)
     * 
     * @param int $leadId
     * @param string $status
     * @param string|null $notes
     * @return bool
     */
    public function updateLeadStatus(int $leadId, string $status, ?string $notes = null): bool
    {
        $allowed = array_keys(CrmModel::STAGES);
        if (!in_array($status, $allowed)) {
            return false;
        }

        $sql = "UPDATE project_leads SET lead_status = :status";
        if ($notes !== null) {
            $sql .= ", notes = :notes";
        }
        $sql .= " WHERE id = :id";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':status', $status, PDO::PARAM_STR);
        if ($notes !== null) {
            $stmt->bindValue(':notes', $notes, PDO::PARAM_STR);
        }
        $stmt->bindValue(':id', $leadId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Delete project (cascades to project_leads)
     * 
     * @param int $id
     * @return bool
     */
    public function deleteProject(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT keyword,location FROM projects WHERE id=? FOR UPDATE');$q->execute([$id]);$project=$q->fetch();
            if (!$project) { $this->db->commit(); return false; }
            $q=$this->db->prepare('SELECT id FROM project_leads WHERE project_id=? ORDER BY id FOR UPDATE');$q->execute([$id]);
            \App\Services\WorkspaceDeletion::leads($this->db,$q->fetchAll(PDO::FETCH_COLUMN));
            $this->db->prepare('DELETE FROM whatsapp_broadcasts WHERE project_id=?')->execute([$id]);
            \App\Services\WorkspaceDeletion::intake($this->db,$id);
            $this->db->prepare('DELETE FROM projects WHERE id=?')->execute([$id]);
            $q=$this->db->prepare('SELECT id FROM projects WHERE LOWER(TRIM(keyword))=LOWER(TRIM(?)) AND LOWER(TRIM(location))=LOWER(TRIM(?)) LIMIT 1');$q->execute([$project['keyword'],$project['location']]);
            if (!$q->fetch()) $this->db->prepare('DELETE FROM prospect_cache WHERE search_key=?')->execute([ProspectModel::makeSearchKey($project['keyword'],$project['location'])]);
            $this->db->commit();return true;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack();throw $e; }
    }

    /**
     * Delete single lead from project
     * 
     * @param int $leadId
     * @return bool
     */
    public function deleteLead(int $leadId): bool
    {
        $this->db->beginTransaction();
        try {
            $removed=\App\Services\WorkspaceDeletion::leads($this->db,[$leadId]);
            $this->db->commit();return $removed>0;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack();throw $e; }
    }
}
