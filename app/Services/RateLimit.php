<?php
namespace App\Services;
use App\Core\Database;

class RateLimit
{
    /**
     * Fixed-window limiter. Buckets are per client IP by default; pass $perClient=false for a limit
     * shared by every client (e.g. per target account, so rotating IPs cannot brute-force one login).
     */
    public static function allow(string $scope, int $limit, int $seconds, bool $perClient = true): bool
    {
        $db=Database::getInstance();
        $now=time();
        $key=hash('sha256',$scope.'|'.($perClient ? ($_SERVER['REMOTE_ADDR'] ?? 'local') : '*'));
        $q=$db->prepare('INSERT INTO rate_limits (bucket,attempts,reset_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE attempts=IF(reset_at<=?,1,attempts+1),reset_at=IF(reset_at<=?,VALUES(reset_at),reset_at)');
        $q->execute([$key,$now+$seconds,$now,$now]);
        $q=$db->prepare('SELECT attempts FROM rate_limits WHERE bucket=?'); $q->execute([$key]);
        $allowed=(int)$q->fetchColumn()<=$limit;
        $q=$db->prepare('DELETE FROM rate_limits WHERE reset_at<? LIMIT 100'); $q->execute([$now-86400]);
        return $allowed;
    }
}
