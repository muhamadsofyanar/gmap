<?php
namespace App\Services;
use App\Core\Database;

final class PlatformContext
{
    public static function enabled(): bool { return getenv('PLATFORM_ACCOUNTS_ENABLED')==='1'; }
    /** Session identifiers are only pointers: registry binding is checked on every request. */
    public static function current(): ?array
    {
        if(!self::enabled() || empty($_SESSION['platform_account_id']))return null;
        $id=$_SESSION['platform_account_id'];$tenant=$_SESSION['tenant_id']??0;$local=$_SESSION['user_id']??0;
        if(!is_int($id) || !is_int($tenant) || !is_int($local))return null;
        $q=Database::platform()->prepare("SELECT a.* FROM platform_accounts a JOIN platform_workspaces w ON w.id=a.workspace_id WHERE a.id=? AND a.workspace_id=? AND a.local_user_id=? AND a.state='active' AND w.state='ready'");
        $q->execute([$id,$tenant,$local]);$account=$q->fetch();if(!$account)return null;
        Database::useWorkspace($tenant);
        $q=Database::getInstance()->prepare('SELECT role,deleted_at,session_version FROM users WHERE id=? AND workspace_id=1');$q->execute([$local]);$user=$q->fetch();
        if(!$user || $user['deleted_at']!==null || !in_array($user['role'],['admin','user'],true) || (int)$user['session_version']!==(int)($_SESSION['session_version']??-1))return null;
        $account['local_role']=$user['role'];return $account;
    }
    public static function requirePlatformAdmin(): array
    {
        $actor=self::current();
        if(!$actor || (int)$actor['workspace_id']!==1 || !(int)$actor['is_platform_admin'] || $actor['local_role']!=='admin')throw new \DomainException('Akses administrator platform diperlukan.');
        return $actor;
    }
    public static function requireOwner(): array
    {
        $actor=self::current();if(!$actor || $actor['local_role']!=='admin')throw new \DomainException('Akses pemilik workspace diperlukan.');return $actor;
    }
}
