<?php
namespace App\Services;
use PDO;
class UserMigration
{
    private static ?\WeakMap $applied=null;
    public static function apply(PDO $db): void {
        self::$applied ??= new \WeakMap();
        if(isset(self::$applied[$db])) return;
        if($db->inTransaction()) throw new \RuntimeException('Migrasi pengguna harus mendahului transaksi.');
        $columns=array_column($db->query('SHOW COLUMNS FROM users')->fetchAll(),'Field');
        foreach(['deleted_at'=>'DATETIME NULL','session_version'=>'INT UNSIGNED NOT NULL DEFAULT 0','contact_phone'=>"VARCHAR(20) NOT NULL DEFAULT ''"] as $name=>$type) {
            if(!in_array($name,$columns,true)) {
                try {$db->exec("ALTER TABLE users ADD COLUMN `$name` $type");}
                catch(\PDOException $e) {if(($e->errorInfo[1] ?? 0)!==1060) throw $e;}
            }
        }
        self::$applied[$db]=true;
    }
}
