<?php
namespace App\Services;
use PDO;
use App\Core\Database;

class WorkspaceProvisioner
{
    public static function ready(): bool
    {
        return PlatformContext::enabled() && (string)getenv('PLATFORM_PROVISION_USER')!=='' && (string)getenv('PLATFORM_PROVISION_PASSWORD')!=='';
    }
    protected function privileged(): PDO
    {
        if(!self::ready())throw new \RuntimeException('Konfigurasi provisioning workspace belum tersedia.');
        return new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';charset=utf8mb4',getenv('PLATFORM_PROVISION_USER'),getenv('PLATFORM_PROVISION_PASSWORD'),[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    /** Caller holds central account/workspace locks. Retrying never drops a database. */
    public function provision(int $id,array $owner): int
    {
        if($id<=1)throw new \DomainException('Workspace awal tidak boleh diprovision ulang.');
        $central=Database::platform();$q=$central->prepare('SELECT * FROM platform_workspaces WHERE id=?');$q->execute([$id]);$row=$q->fetch();
        if(!$row || !in_array($row['state'],['pending','ready'],true))throw new \DomainException('Workspace tidak dapat disiapkan.');
        $server=$this->privileged();$name=substr(DB_NAME,0,36).'_client_'.$id;
        if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$name))throw new \RuntimeException('Nama database provisioning tidak valid.');
        if($row['db_name'] && $row['db_name']!==$name)throw new \RuntimeException('Identitas database workspace berubah.');
        $user=$row['db_user'] ?: 'lgw_'.bin2hex(random_bytes(8));
        $password=$row['db_secret'] ? (new GatewaySecret())->decrypt($row['db_secret']) : bin2hex(random_bytes(24));
        if(!preg_match('/^lgw_[a-f0-9]{16}$/D',$user))throw new \RuntimeException('Identitas pengguna database tidak valid.');
        $central->prepare('UPDATE platform_workspaces SET db_name=?,db_user=?,db_secret=? WHERE id=?')->execute([$name,$user,(new GatewaySecret())->encrypt($password),$id]);
        $server->exec('CREATE DATABASE IF NOT EXISTS `'.$name.'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $server->exec('CREATE USER IF NOT EXISTS '.$server->quote($user)."@'%' IDENTIFIED BY ".$server->quote($password));
        $server->exec('ALTER USER '.$server->quote($user)."@'%' IDENTIFIED BY ".$server->quote($password));
        // MariaDB treats underscores in database GRANT identifiers as wildcards.
        $grantName=str_replace('_','\\_',$name);
        $server->exec('GRANT ALL PRIVILEGES ON `'.$grantName.'`.* TO '.$server->quote($user)."@'%'");
        $db=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.$name.';charset=utf8mb4',$user,$password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $db->exec("SET time_zone = '+07:00'");
        Installer::schema($db,ROOT_PATH.'/database.sql');\App\Models\InboxRepository::install($db);
        $existing=$db->query("SELECT id,username,email FROM users WHERE role='admin' AND deleted_at IS NULL ORDER BY id LIMIT 1")->fetch();
        if($existing){
            if($existing['email']!==$owner['email'] || $existing['username']!==$owner['username'])throw new \RuntimeException('Identitas pemilik workspace tidak cocok.');
            $local=(int)$existing['id'];
        }else{
            $db->prepare("INSERT INTO users(username,email,name,password,role,workspace_id) VALUES(?,?,?,?,'admin',1)")->execute([$owner['username'],$owner['email'],$owner['name'],$owner['password_hash']]);$local=(int)$db->lastInsertId();
        }
        $central->prepare("UPDATE platform_workspaces SET state='ready' WHERE id=?")->execute([$id]);return $local;
    }
}
