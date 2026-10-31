<?php
/**
 * CRM Model
 * Sales Funnel Pipeline, Lead Status Management & Follow-up Notes
 * Gmap Prospek CRM
 */

namespace App\Models;

use App\Core\Database;
use PDO;

class CrmModel
{
    protected PDO $db;

    // Supported Sales Funnel Stages
    public const STAGES = [
        'new'         => ['label' => 'Belum dihubungi',   'color' => 'slate',   'bg' => 'bg-slate-100',   'text' => 'text-slate-700',   'border' => 'border-slate-300'],
        'contacted'   => ['label' => 'Dihubungi',   'color' => 'blue',    'bg' => 'bg-blue-50',     'text' => 'text-blue-700',    'border' => 'border-blue-200'],
        'interested'  => ['label' => 'Tertarik',   'color' => 'violet',  'bg' => 'bg-purple-50',   'text' => 'text-purple-700',  'border' => 'border-purple-200'],
        'negotiation' => ['label' => 'Negosiasi',  'color' => 'amber',   'bg' => 'bg-amber-50',    'text' => 'text-amber-800',   'border' => 'border-amber-200'],
        'closing'     => ['label' => 'Deal','color' => 'emerald', 'bg' => 'bg-emerald-50',  'text' => 'text-emerald-700', 'border' => 'border-emerald-300'],
        'lost'        => ['label' => 'Tidak lanjut','color' => 'rose',   'bg' => 'bg-rose-50',     'text' => 'text-rose-700',    'border' => 'border-rose-200'],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function getDashboardStats(): array
    {
        $row = $this->db->query("SELECT COUNT(*) AS total_leads,
            COALESCE(SUM(lead_status='new'),0) AS uncontacted,
            COALESCE(SUM(lead_status IN ('contacted','interested','negotiation')
                AND COALESCE(follow_up_count,0)=0 AND last_follow_up IS NULL),0) AS awaiting_follow_up,
            COALESCE(SUM(lead_status IN ('contacted','interested','negotiation') AND NOT EXISTS (SELECT 1 FROM crm_tasks t WHERE t.lead_id=l.id AND t.completed_at IS NULL)),0) AS unscheduled_active,
            COALESCE(SUM(lead_status='closing'),0) AS closing,
            COALESCE(SUM(CASE WHEN lead_status='closing' THEN deal_value ELSE 0 END),0) AS closing_value
            FROM project_leads l JOIN projects p ON p.id=l.project_id WHERE l.in_crm=1 AND l.workspace_id=1 AND p.workspace_id=1")->fetch();
        $stats = array_map('intval', $row);
        $stats['closing_value'] = (float)$row['closing_value'];
        return $stats;
    }

    public function unscheduledActive(int $page=1): array
    {
        $offset=(max(1,min(100000,$page))-1)*50;
        return $this->db->query("SELECT l.id,l.shop_name,l.lead_status,p.name AS project_name FROM project_leads l JOIN projects p ON p.id=l.project_id WHERE l.workspace_id=1 AND p.workspace_id=1 AND l.in_crm=1 AND l.lead_status IN ('contacted','interested','negotiation') AND NOT EXISTS (SELECT 1 FROM crm_tasks t WHERE t.lead_id=l.id AND t.completed_at IS NULL) ORDER BY l.updated_at,l.id LIMIT 51 OFFSET $offset")->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getProjectOverview(string $search = '', ?string $status = null): array
    {
        $conditions = [];
        $params = [];
        if ($search !== '') {
            $conditions[] = '(p.name LIKE :name OR p.keyword LIKE :keyword OR p.location LIKE :location)';
            foreach (['name', 'keyword', 'location'] as $key) $params[$key] = '%' . $search . '%';
        }
        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $having = '';
        if ($status !== null && isset(self::STAGES[$status])) {
            $having = 'HAVING SUM(CASE WHEN l.lead_status = :stage THEN 1 ELSE 0 END) > 0';
            $params['stage'] = $status;
        }
        $stmt = $this->db->prepare("SELECT p.id, p.name, p.keyword, p.location,
            COUNT(l.id) AS crm_total,
            SUM(CASE WHEN l.lead_status = 'new' THEN 1 ELSE 0 END) AS crm_new,
            SUM(CASE WHEN l.lead_status IN ('contacted','interested','negotiation') THEN 1 ELSE 0 END) AS crm_active,
            SUM(CASE WHEN l.lead_status = 'closing' THEN 1 ELSE 0 END) AS crm_deals,
            MAX(l.updated_at) AS last_activity
            FROM projects p JOIN project_leads l ON l.project_id = p.id AND l.in_crm = 1
            {$where} GROUP BY p.id, p.name, p.keyword, p.location {$having}
            ORDER BY (COUNT(l.id) > 0) DESC, last_activity DESC, p.id DESC");
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function saveOpeningHours(int $id, string $hours): bool
    {
        if (trim($hours) === '') return false;
        $stmt = $this->db->prepare('UPDATE project_leads SET opening_hours = :hours WHERE id = :id AND in_crm = 1');
        return $stmt->execute(['hours' => $hours, 'id' => $id]);
    }

    /**
     * Get Sales Funnel Aggregations
     *
     * @param int|null $projectId
     * @return array
     */
    public function getFunnelStats(?int $projectId = null): array
    {
        $where = "WHERE in_crm = 1";
        if ($projectId) {
            $where .= " AND project_id = :pid";
        }

        $sql = "
            SELECT
                COUNT(*) as total_leads,
                SUM(CASE WHEN lead_status = 'new' OR lead_status IS NULL THEN 1 ELSE 0 END) as stage_new,
                SUM(CASE WHEN lead_status = 'contacted' THEN 1 ELSE 0 END) as stage_contacted,
                SUM(CASE WHEN lead_status = 'interested' THEN 1 ELSE 0 END) as stage_interested,
                SUM(CASE WHEN lead_status = 'negotiation' THEN 1 ELSE 0 END) as stage_negotiation,
                SUM(CASE WHEN lead_status = 'closing' THEN 1 ELSE 0 END) as stage_closing,
                SUM(CASE WHEN lead_status = 'lost' THEN 1 ELSE 0 END) as stage_lost,
                SUM(CASE WHEN lead_status = 'closing' THEN deal_value ELSE 0 END) as total_closing_value,
                SUM(CASE WHEN lead_status = 'negotiation' THEN deal_value ELSE 0 END) as pipeline_value,
                SUM(CASE WHEN (website IS NULL OR website = '') THEN 1 ELSE 0 END) as total_no_website,
                SUM(CASE WHEN (website IS NULL OR website = '') AND (phone IS NOT NULL AND phone != '') THEN 1 ELSE 0 END) as gold_targets
            FROM project_leads
            {$where}
        ";

        $stmt = $this->db->prepare($sql);
        if ($projectId) {
            $stmt->bindValue(':pid', $projectId, PDO::PARAM_INT);
        }
        $stmt->execute();
        $stats = $stmt->fetch(PDO::FETCH_ASSOC);

        $total = (int)($stats['total_leads'] ?? 0);
        $closing = (int)($stats['stage_closing'] ?? 0);
        $conversionRate = $total > 0 ? round(($closing / $total) * 100, 1) : 0.0;

        $stats['conversion_rate'] = $conversionRate;
        return $stats;
    }

    /**
     * Get Leads with Campaign Information (Only leads moved to CRM)
     *
     * @param int|null $projectId
     * @param string|null $status
     * @param string|null $search
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public static function dueCondition(string $due): string
    {
        $base = 'SELECT 1 FROM crm_tasks ft WHERE ft.lead_id=l.id AND ft.completed_at IS NULL';
        return match ($due) {
            'today' => 'EXISTS (' . $base . ' AND DATE(ft.due_at)=CURDATE())',
            'overdue' => 'EXISTS (' . $base . ' AND ft.due_at<NOW())',
            'unscheduled' => 'NOT EXISTS (' . $base . ')',
            'repeat' => "EXISTS (" . $base . " AND ft.kind='repeat_order')",
            default => '1=1'
        };
    }

    public function getLeads(?int $projectId = null, ?string $status = null, ?string $search = null, ?string $websiteFilter = null, int $limit = 200, int $offset = 0, string $due = 'all'): array
    {
        // Only fetch leads that have been moved to CRM
        $conditions = ["l.in_crm = 1"];
        $conditions[] = self::dueCondition($due);
        $params = [];

        if ($projectId) {
            $conditions[] = "l.project_id = :pid";
            $params[':pid'] = $projectId;
        }

        if ($status && $status !== 'all' && isset(self::STAGES[$status])) {
            $conditions[] = "l.lead_status = :status";
            $params[':status'] = $status;
        }

        if (!empty($search)) {
            $conditions[] = "(l.shop_name LIKE :search OR l.address LIKE :search_address OR l.phone LIKE :search_phone OR l.notes LIKE :search_notes)";
            foreach ([':search', ':search_address', ':search_phone', ':search_notes'] as $key) {
                $params[$key] = '%' . trim($search) . '%';
            }
        }

        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';

        $sql = "
            SELECT l.*, p.name as project_name, p.keyword as project_keyword, p.location as project_location
            FROM project_leads l
            JOIN projects p ON l.project_id = p.id
            {$whereClause}
            ORDER BY
                CASE l.lead_status
                    WHEN 'negotiation' THEN 1
                    WHEN 'interested' THEN 2
                    WHEN 'contacted' THEN 3
                    WHEN 'new' THEN 4
                    WHEN 'closing' THEN 5
                    WHEN 'lost' THEN 6
                    ELSE 7
                END,
                l.updated_at DESC,
                l.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPipeline(int $projectId, string $search, array $pages, ?string $status = null, string $due = 'all'): array
    {
        $params = [$projectId];
        $where = 'l.project_id = ? AND l.in_crm = 1 AND ' . self::dueCondition($due);
        if ($search !== '') {
            $where .= ' AND (shop_name LIKE ? OR address LIKE ? OR phone LIKE ? OR notes LIKE ?)';
            $params = array_merge($params, array_fill(0, 4, '%' . $search . '%'));
        }
        $query = $this->db->prepare('SELECT lead_status, COUNT(*) AS total FROM project_leads l WHERE ' . $where . ' GROUP BY lead_status');
        $query->execute($params);
        $counts = array_column($query->fetchAll(PDO::FETCH_ASSOC), 'total', 'lead_status');
        $columns = [];
        foreach (self::STAGES as $key => $_) {
            if ($status !== null && $status !== $key) continue;
            $total = (int)($counts[$key] ?? 0);
            $page = min(max(1, (int)($pages[$key] ?? 1)), max(1, (int)ceil($total / 20)));
            $leads = $this->getLeads($projectId, $key, $search, null, 20, ($page - 1) * 20, $due);
            $columns[$key] = ['leads' => $leads, 'total' => $total, 'page' => $page, 'hasNext' => $page * 20 < $total];
        }
        foreach ($columns as &$column) $column['leads'] = $this->withNextFollowup($column['leads']);
        unset($column);
        return $columns;
    }

    /** Add the earliest open follow-up (next_followup, next_followup_title, next_followup_task) to each lead. */
    public function withNextFollowup(array $leads): array
    {
        $ids = array_map(static fn($lead) => (int)$lead['id'], $leads);
        $followups = [];
        if ($ids) {
            $query = $this->db->prepare('SELECT id, lead_id, title, due_at FROM crm_tasks WHERE completed_at IS NULL AND lead_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY due_at DESC, id DESC');
            $query->execute($ids);
            // Ordered latest first, so the earliest task per lead is written last.
            foreach ($query->fetchAll(PDO::FETCH_ASSOC) as $task) $followups[$task['lead_id']] = $task;
        }
        foreach ($leads as &$lead) {
            $task = $followups[$lead['id']] ?? null;
            $lead['next_followup'] = $task['due_at'] ?? null;
            $lead['next_followup_title'] = $task['title'] ?? null;
            $lead['next_followup_task'] = isset($task['id']) ? (int)$task['id'] : null;
        }
        unset($lead);
        return $leads;
    }

    /**
     * Get single lead by ID
     *
     * @param int $id
     * @return array|null
     */
    public function getLeadById(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT l.*, p.name as project_name, p.keyword as project_keyword, p.location as project_location
            FROM project_leads l
            JOIN projects p ON l.project_id = p.id
            WHERE l.id = :id
            LIMIT 1
        ");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $res = $stmt->fetch(PDO::FETCH_ASSOC);
        return $res ?: null;
    }

    /**
     * Get Sales Funnel History for a Lead
     *
     * @param int $leadId
     * @return array
     */
    public function getFunnelHistory(int $leadId): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM crm_funnel_history
            WHERE lead_id = :lid
            ORDER BY created_at DESC, id DESC
        ");
        $stmt->bindValue(':lid', $leadId, PDO::PARAM_INT);
        $stmt->execute();
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $history;
    }

    /**
     * Add entry to crm_funnel_history
     */
    public function addHistory(int $leadId, ?string $fromStatus, string $toStatus, string $actionType = 'status_change', ?string $notes = null, float $dealValue = 0.0, ?string $createdAt = null): bool
    {
        $sql = "
            INSERT INTO crm_funnel_history (lead_id, from_status, to_status, action_type, notes, deal_value, actor_user_id, created_at)
            VALUES (:lid, :from_s, :to_s, :act, :notes, :deal, :actor, " . ($createdAt ? ":created_at" : "NOW()") . ")
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':actor', $_SESSION['user_id'] ?? null, isset($_SESSION['user_id']) ? PDO::PARAM_INT : PDO::PARAM_NULL);
        $stmt->bindValue(':lid', $leadId, PDO::PARAM_INT);
        $stmt->bindValue(':from_s', $fromStatus, $fromStatus !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':to_s', $toStatus, PDO::PARAM_STR);
        $stmt->bindValue(':act', $actionType, PDO::PARAM_STR);
        $stmt->bindValue(':notes', $notes !== null ? trim($notes) : null, $notes !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
        $stmt->bindValue(':deal', max(0.0, $dealValue));
        if ($createdAt) {
            $stmt->bindValue(':created_at', $createdAt, PDO::PARAM_STR);
        }
        return $stmt->execute();
    }

    /**
     * Update Lead Status, Notes, and Deal Value with Funnel History Audit
     *
     * @param int $id
     * @param string $status
     * @param string|null $notes
     * @param float $dealValue
     * @param bool $incrementFollowUp
     * @return bool
     */
    public function updateLead(int $id, string $status, ?string $notes = null, ?float $dealValue = null, bool $incrementFollowUp = false): bool
    {
        if (!isset(self::STAGES[$status])) {
            return false;
        }

        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT * FROM project_leads WHERE id = :id AND in_crm = 1 FOR UPDATE');
            $lock->execute([':id' => $id]);
            $current = $lock->fetch(PDO::FETCH_ASSOC);
            if (!$current) {
                $this->db->rollBack();
                return false;
            }
            // Omitted fields preserve their stored values. Empty notes and zero deal are explicit edits.
            $notes = $notes === null ? $current['notes'] : trim($notes);
            $dealValue = $dealValue ?? (float)$current['deal_value'];
            $oldStatus = $current['lead_status'] ?? 'new';
            $oldNotes = $current['notes'] ?? '';
            $oldDeal = (float)($current['deal_value'] ?? 0);

            if ($oldStatus === $status && (string)$notes === (string)$oldNotes && $dealValue === $oldDeal && !$incrementFollowUp) {
                $this->db->commit();
                return true;
            }
            $updateFollowUp = "";
            if ($incrementFollowUp) {
                $updateFollowUp = ", last_follow_up = NOW(), follow_up_count = follow_up_count + 1";
            }

            $sql = "
                UPDATE project_leads
                SET lead_status = :status,
                    notes = :notes,
                    deal_value = :deal_value,
                    updated_at = NOW()
                    {$updateFollowUp}
                WHERE id = :id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            $stmt->bindValue(':notes', $notes !== null ? trim($notes) : null, $notes !== null ? PDO::PARAM_STR : PDO::PARAM_NULL);
            $stmt->bindValue(':deal_value', max(0.0, $dealValue));
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);

            $ok = $stmt->execute();
            if ($ok) {
                $actionType = 'status_change';
                $logNote = $notes;

                if ($oldStatus === $status) {
                    if ($dealValue != $oldDeal) {
                        $actionType = 'deal_updated';
                        $logNote = "Nilai deal diubah menjadi Rp " . number_format($dealValue, 0, ',', '.') . ($notes ? " - {$notes}" : "");
                    } elseif ((string)$notes !== (string)$oldNotes) {
                        $actionType = 'note_added';
                        $logNote = $notes;
                    }
                } else {
                    $stageLabel = (new SalesModel())->stages((int)$current['project_id'])[$status]['label'] ?? $status;
                    if ($dealValue > 0) {
                        $logNote = "Tahapan diubah ke {$stageLabel} (Nilai: Rp " . number_format($dealValue, 0, ',', '.') . ")" . ($notes ? " - {$notes}" : "");
                    } else {
                        $logNote = "Tahapan diubah ke {$stageLabel}" . ($notes ? " - {$notes}" : "");
                    }
                }

                $this->addHistory($id, $oldStatus, $status, $actionType, $logNote, $dealValue);
            }

            $this->db->commit();
            return $ok;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Record WhatsApp follow-up action with history
     *
     * @param int $id
     * @return bool
     */
    /** Move a lead from "new" to "contacted"; leads already further along keep their stage. */
    public function markContacted(int $id, string $note): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM project_leads WHERE id = :id AND in_crm = 1 FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $lead = $stmt->fetch();
            if (!$lead || ($lead['lead_status'] ?? 'new') !== 'new') {
                $this->db->rollBack();
                return false;
            }
            $this->db->prepare("UPDATE project_leads SET lead_status = 'contacted', updated_at = NOW() WHERE id = ?")->execute([$id]);
            $this->addHistory($id, 'new', 'contacted', 'status_change', $note, (float)$lead['deal_value']);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function logWhatsAppContact(int $id): bool
    {
        return $this->appendActivity($id, 'whatsapp_opened', 'Tautan WhatsApp dibuka. Pengiriman pesan belum dikonfirmasi.');
    }

    public function addNote(int $id, string $note): bool
    {
        $note = trim($note);
        return $note !== '' && $this->appendActivity($id, 'note_added', $note);
    }

    private function appendActivity(int $id, string $action, string $note): bool
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare('SELECT * FROM project_leads WHERE id = :id AND in_crm = 1 FOR UPDATE');
            $stmt->execute([':id' => $id]);
            $lead = $stmt->fetch();
            if (!$lead) {
                $this->db->rollBack();
                return false;
            }
            $this->addHistory($id, $lead['lead_status'], $lead['lead_status'], $action, $note, (float)$lead['deal_value']);
            $this->db->commit();
            return true;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Leads per project with an open follow-up due today or already overdue, for the quick filters. */
    public function dueCounts(int $projectId): array
    {
        $q = $this->db->prepare('SELECT COUNT(DISTINCT CASE WHEN DATE(t.due_at)=CURDATE() THEN l.id END) AS today, COUNT(DISTINCT CASE WHEN t.due_at<NOW() THEN l.id END) AS overdue FROM project_leads l JOIN crm_tasks t ON t.lead_id=l.id AND t.completed_at IS NULL WHERE l.project_id=? AND l.in_crm=1');
        $q->execute([$projectId]);
        return array_map('intval', $q->fetch(PDO::FETCH_ASSOC) ?: ['today' => 0, 'overdue' => 0]);
    }

    /** Add only new CRM members, preserving existing statuses and history. */
    public function moveToCrm(array $leadIds): int
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $leadIds), fn($id) => $id > 0)));
        if (!$ids) return 0;
        $this->db->beginTransaction();
        try {
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $select = $this->db->prepare("SELECT id, lead_status, deal_value FROM project_leads WHERE in_crm = 0 AND id IN ({$placeholders}) ORDER BY id FOR UPDATE");
            $select->execute($ids);
            $rows = $select->fetchAll();
            $update = $this->db->prepare('UPDATE project_leads SET in_crm = 1, crm_added_at = NOW() WHERE id = :id');
            foreach ($rows as $lead) {
                $update->execute([':id' => $lead['id']]);
                $this->addHistory((int)$lead['id'], null, $lead['lead_status'], 'moved_to_crm', 'Prospek ditambahkan ke CRM dari proyek.', (float)$lead['deal_value']);
            }
            $this->db->commit();
            return count($rows);
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /** Delete the CRM leads and their dependent records permanently. */
    public function removeProjectFromCrm(int $projectId): int
    {
        $q=$this->db->prepare('SELECT COUNT(*) FROM project_leads WHERE project_id=?');$q->execute([$projectId]);$count=(int)$q->fetchColumn();
        return (new ProjectModel())->deleteProject($projectId) ? $count : 0;
    }

    public function removeFromCrm(int $leadId): bool
    {
        $this->db->beginTransaction();
        try {
            $q=$this->db->prepare('SELECT id FROM project_leads WHERE id=? AND in_crm=1 FOR UPDATE');$q->execute([$leadId]);
            $removed=$q->fetchColumn() ? \App\Services\WorkspaceDeletion::leads($this->db,[$leadId]) : 0;
            $this->db->commit();return $removed>0;
        } catch (\Throwable $e) { if ($this->db->inTransaction()) $this->db->rollBack();throw $e; }
    }
}
