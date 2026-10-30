<?php
/**
 * Prospect Model
 * Smart Database Caching & Data Management
 * Gmap Prospek
 */

namespace App\Models;

use App\Core\Database;
use PDO;

class ProspectModel
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Generate unique hash search key for keyword and location
     * 
     * @param string $keyword
     * @param string $location
     * @return string
     */
    public static function makeSearchKey(string $keyword, string $location): string
    {
        $k = strtolower(trim($keyword));
        $l = strtolower(trim($location));
        return md5('verified-contact-v2|' . $k . '|' . $l);
    }

    /**
     * Check and retrieve cached prospects if created within cache expiry window
     * 
     * @param string $keyword
     * @param string $location
     * @param int $cacheExpiryHours
     * @return array|null Returns array of items if cached and valid, null otherwise
     */
    public function getCachedProspects(string $keyword, string $location, int $cacheExpiryHours = 24): ?array
    {
        $searchKey = self::makeSearchKey($keyword, $location);

        // Check if fresh cache exists within cacheExpiryHours
        $stmt = $this->db->prepare("
            SELECT id, keyword, location, shop_name, category, address, phone, whatsapp, 
                   rating, reviews_count, opening_hours, website, gmap_url, created_at 
            FROM prospect_cache 
            WHERE search_key = :search_key 
              AND created_at >= (NOW() - INTERVAL :hours HOUR)
            ORDER BY rating DESC, reviews_count DESC, id ASC
        ");

        $stmt->bindValue(':search_key', $searchKey, PDO::PARAM_STR);
        $stmt->bindValue(':hours', $cacheExpiryHours, PDO::PARAM_INT);
        $stmt->execute();

        $results = $stmt->fetchAll();

        return !empty($results) ? $results : null;
    }

    /**
     * Save newly scraped prospects to database cache
     * 
     * @param string $keyword
     * @param string $location
     * @param array $items
     * @return int Count of newly inserted rows
     */
    public function saveScrapedProspects(string $keyword, string $location, array $items): int
    {
        if (empty($items)) {
            return 0;
        }

        $searchKey = self::makeSearchKey($keyword, $location);

        $this->db->beginTransaction();
        try {
            // Delete previous outdated cache for this exact search key to keep data fresh
            $deleteStmt = $this->db->prepare("DELETE FROM prospect_cache WHERE search_key = :search_key");
            $deleteStmt->bindValue(':search_key', $searchKey, PDO::PARAM_STR);
            $deleteStmt->execute();

            // Batch insert prepared statement
            $insertSql = "
                INSERT INTO prospect_cache (
                    keyword, location, search_key, shop_name, category, address, 
                    phone, whatsapp, rating, reviews_count, opening_hours, website, gmap_url, latitude, longitude, created_at
                ) VALUES (
                    :keyword, :location, :search_key, :shop_name, :category, :address, 
                    :phone, :whatsapp, :rating, :reviews_count, :opening_hours, :website, :gmap_url, :latitude, :longitude, NOW()
                )
            ";

            $stmt = $this->db->prepare($insertSql);
            $inserted = 0;

            foreach ($items as $item) {
                $phone = !empty($item['phone']) ? trim($item['phone']) : null;
                $whatsapp = !empty($item['whatsapp']) ? trim($item['whatsapp']) : format_whatsapp($phone);

                $stmt->bindValue(':keyword', trim($keyword), PDO::PARAM_STR);
                $stmt->bindValue(':location', trim($location), PDO::PARAM_STR);
                $stmt->bindValue(':search_key', $searchKey, PDO::PARAM_STR);
                $stmt->bindValue(':shop_name', trim($item['shop_name'] ?? 'Bisnis Tanpa Nama'), PDO::PARAM_STR);
                $stmt->bindValue(':category', $item['category'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(':address', $item['address'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(':phone', $phone, PDO::PARAM_STR);
                $stmt->bindValue(':whatsapp', $whatsapp ?: null, PDO::PARAM_STR);
                $stmt->bindValue(':rating', (float)($item['rating'] ?? 0.0));
                $stmt->bindValue(':reviews_count', (int)($item['reviews_count'] ?? 0), PDO::PARAM_INT);
                $stmt->bindValue(':opening_hours', $item['opening_hours'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(':website', $item['website'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(':gmap_url', $item['gmap_url'] ?? null, PDO::PARAM_STR);
                $stmt->bindValue(':latitude', isset($item['latitude']) ? (float)$item['latitude'] : null);
                $stmt->bindValue(':longitude', isset($item['longitude']) ? (float)$item['longitude'] : null);

                if ($stmt->execute()) {
                    $inserted++;
                }
            }
            $this->db->commit();
            return $inserted;
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Get all cached prospects with optional search query, sorting, and pagination
     * 
     * @param string|null $filterSearch
     * @param string $sortBy
     * @param string $sortDir
     * @param int $limit
     * @param int $offset
     * @return array
     */
    public function getAll(?string $filterSearch = null, string $sortBy = 'created_at', string $sortDir = 'DESC', int $limit = 100, int $offset = 0): array
    {
        $allowedSort = ['shop_name', 'rating', 'reviews_count', 'created_at', 'location', 'keyword'];
        $sortBy = in_array($sortBy, $allowedSort) ? $sortBy : 'created_at';
        $sortDir = strtoupper($sortDir) === 'ASC' ? 'ASC' : 'DESC';

        $whereClause = "";
        $params = [];

        if (!empty($filterSearch)) {
            $whereClause = "WHERE (shop_name LIKE :kw1 OR address LIKE :kw2 OR category LIKE :kw3 OR location LIKE :kw4 OR phone LIKE :kw5)";
            $searchTerm = '%' . trim($filterSearch) . '%';
            $params[':kw1'] = $searchTerm;
            $params[':kw2'] = $searchTerm;
            $params[':kw3'] = $searchTerm;
            $params[':kw4'] = $searchTerm;
            $params[':kw5'] = $searchTerm;
        }

        $sql = "
            SELECT id, keyword, location, search_key, shop_name, category, address, phone, whatsapp, 
                   rating, reviews_count, website, gmap_url, created_at 
            FROM prospect_cache 
            {$whereClause}
            ORDER BY {$sortBy} {$sortDir}
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $this->db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val, PDO::PARAM_STR);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    /**
     * Delete single prospect by ID
     * 
     * @param int $id
     * @return bool
     */
    public function deleteById(int $id): bool
    {
        $stmt = $this->db->prepare("DELETE FROM prospect_cache WHERE id = :id");
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Clear all cache for a specific search key or all caches older than 24 hours
     * 
     * @param string|null $searchKey
     * @return int
     */
    public function clearCache(?string $searchKey = null): int
    {
        if ($searchKey) {
            $stmt = $this->db->prepare("DELETE FROM prospect_cache WHERE search_key = :search_key");
            $stmt->bindValue(':search_key', $searchKey, PDO::PARAM_STR);
            $stmt->execute();
            return $stmt->rowCount();
        }

        // Delete expired cache > 24 hours
        $stmt = $this->db->prepare("DELETE FROM prospect_cache WHERE created_at < (NOW() - INTERVAL 24 HOUR)");
        $stmt->execute();
        return $stmt->rowCount();
    }

    /**
     * Get overall statistics for dashboard
     * 
     * @return array
     */
    public function getStats(): array
    {
        $totalStmt = $this->db->query("SELECT COUNT(*) as total FROM prospect_cache");
        $total = (int)$totalStmt->fetch()['total'];

        $waStmt = $this->db->query("SELECT COUNT(*) as total_wa FROM prospect_cache WHERE whatsapp IS NOT NULL AND whatsapp != ''");
        $totalWa = (int)$waStmt->fetch()['total_wa'];

        $uniqueSearchStmt = $this->db->query("SELECT COUNT(DISTINCT search_key) as total_searches FROM prospect_cache");
        $totalSearches = (int)$uniqueSearchStmt->fetch()['total_searches'];

        $activeCacheStmt = $this->db->query("SELECT COUNT(*) as active_cache FROM prospect_cache WHERE created_at >= (NOW() - INTERVAL 24 HOUR)");
        $activeCache = (int)$activeCacheStmt->fetch()['active_cache'];

        return [
            'total_prospects' => $total,
            'total_with_whatsapp' => $totalWa,
            'total_searches' => $totalSearches,
            'active_cache' => $activeCache,
            'wa_percentage' => $total > 0 ? round(($totalWa / $total) * 100, 1) : 0
        ];
    }
}
