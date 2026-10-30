<?php
namespace App\Services;
use App\Core\Database;

final class AuthGuard
{
    public static function identity(): ?array
    {
        if (!is_authenticated()) return null;
        $q=Database::getInstance()->prepare('SELECT id,username,name,email,session_version,role,workspace_id FROM users WHERE id=? AND deleted_at IS NULL');
        $q->execute([(int)$_SESSION['user_id']]);$user=$q->fetch();
        if (!$user || (int)$user['workspace_id']!==1 || !in_array($user['role'],['admin','user'],true)
            || (int)($_SESSION['session_version'] ?? -1)!==(int)$user['session_version']) {
            session_unset();return null;
        }
        // Database identity overrides cached session roles on every request.
        foreach(['name'=>'user_name','email'=>'user_email','role'=>'role','workspace_id'=>'workspace_id','username'=>'username'] as $field=>$session) $_SESSION[$session]=$user[$field];
        return $user;
    }

    public static function guard(string $controller): void
    {
        $user=self::identity();
        if ($controller==='AuthController') return;
        if (!$user) { header('Location: '.base_url('login'));exit; }
        if (!TeamAccess::allowsController($user,$controller)) {
            // Root/dashboard is a navigation entry point, not a member data source.
            if ($controller==='DashboardController') {header('Location: '.base_url('team'));exit;}
            http_response_code(403);header('Content-Type: text/plain; charset=utf-8');
            echo 'Akses ditolak. Gunakan halaman kerja anggota.';exit;
        }
    }
}
