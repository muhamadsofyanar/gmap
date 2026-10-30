<?php
namespace App\Services;
use PDO;
use InvalidArgumentException;
use RuntimeException;
use App\Models\TeamRepository;

final class Installer
{
    public static function validateEnvironment(array $env): void
    {
        $url=(string)($env['APP_URL'] ?? '');$parts=parse_url($url);
        if (!$parts || ($parts['scheme'] ?? '')!=='https' || empty($parts['host']) || !empty($parts['path'])
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) throw new InvalidArgumentException('APP_URL wajib HTTPS tanpa path.');
        if (($env['DB_PASS'] ?? '')==='') throw new InvalidArgumentException('DB_PASS wajib diisi.');
        $key=base64_decode((string)($env['XSENDER_ENCRYPTION_KEY'] ?? ''),true);
        if ($key===false || strlen($key)!==32) throw new InvalidArgumentException('XSENDER_ENCRYPTION_KEY harus base64 dari 32 byte acak.');
    }

    public static function schema(PDO $db,string $file): void
    {
        $sql=file_get_contents($file);
        if ($sql===false) throw new RuntimeException('Skema tidak ditemukan.');
        // This release supports fresh databases and its own redeploys; do not silently mutate a legacy install.
        $tables=$db->query("SHOW TABLES LIKE 'users'")->fetchAll();
        if ($tables) {
            $columns=array_column($db->query('SHOW COLUMNS FROM users')->fetchAll(),'Field');
            if (!in_array('workspace_id',$columns,true)) throw new RuntimeException('Database versi lokal terdeteksi. Gunakan database baru untuk versi tim; cadangkan database lama.');
        }
        foreach (explode(';',$sql) as $statement) if (trim($statement)!=='') $db->exec($statement);
        foreach(['users'=>['workspace_id'],'projects'=>['workspace_id'],'project_leads'=>['workspace_id','assigned_user_id'],'crm_funnel_history'=>['actor_user_id']] as $table=>$required) {
            $columns=array_column($db->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(),'Field');
            foreach($required as $column) if(!in_array($column,$columns,true)) throw new RuntimeException('Skema tidak sesuai. Gunakan database baru untuk paket tim ini.');
        }
    }

    public static function bootstrapAdmin(PDO $db,array $env): void
    {
        if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()>0) {
            if (!(int)$db->query("SELECT COUNT(*) FROM users WHERE role='admin' AND workspace_id=1 AND deleted_at IS NULL")->fetchColumn()) throw new RuntimeException('Tidak ada admin aktif. Pulihkan admin melalui CLI.');
            return;
        }
        $username=trim((string)($env['ADMIN_USERNAME'] ?? ''));
        $email=trim((string)($env['ADMIN_EMAIL'] ?? ''));
        $name=trim((string)($env['ADMIN_NAME'] ?? 'Admin'));
        $password=(string)($env['ADMIN_PASSWORD'] ?? '');
        TeamRepository::validatePassword($password);
        if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/D',$username) || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100 || $name==='' || strlen($name)>100) throw new InvalidArgumentException('ADMIN_USERNAME, ADMIN_EMAIL, ADMIN_NAME tidak valid.');
        $q=$db->prepare("INSERT INTO users (username,email,name,password,role,workspace_id) VALUES (?,?,?,?,'admin',1)");
        $q->execute([$username,$email,$name,password_hash_secure($password)]);
    }
}
