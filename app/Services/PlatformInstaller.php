<?php
namespace App\Services;
use PDO;
final class PlatformInstaller
{
    public static function install(PDO $db): void
    {
        $sql=file_get_contents(ROOT_PATH.'/platform.sql');
        if($sql===false)throw new \RuntimeException('Skema platform tidak ditemukan.');
        foreach(explode(';',$sql) as $part)if(trim($part)!=='')$db->exec($part);
        $db->prepare("INSERT IGNORE INTO platform_workspaces(id,name,state,db_name,db_user) VALUES(1,'Workspace awal','ready',?,?)")->execute([DB_NAME,DB_USER]);
        // Migration imports only the original local identities; no passwords are copied.
        $first=(int)$db->query("SELECT id FROM users WHERE role='admin' AND workspace_id=1 AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetchColumn();
        $q=$db->prepare('INSERT IGNORE INTO platform_accounts(workspace_id,local_user_id,email,username,name,phone,state,verified_channel,is_platform_admin) VALUES(1,?,?,?,?,?,?,NULL,?)');
        foreach($db->query('SELECT * FROM users WHERE workspace_id=1')->fetchAll() as $u)$q->execute([$u['id'],mb_strtolower($u['email']),$u['username'],$u['name'],$u['contact_phone']??'',$u['deleted_at']===null?'active':'disabled',(int)$u['id']===$first?1:0]);
        if(!$db->query('SELECT id FROM platform_settings WHERE id=1')->fetchColumn())$db->prepare('INSERT INTO platform_settings(id,payload) VALUES(1,?)')->execute(['{}']);
    }
}
