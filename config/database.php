<?php
namespace App\Core;
use PDO;

final class Database
{
    private static ?PDO $instance=null;
    private static ?PDO $platform=null;
    private static int $boundWorkspace=0;
    private function __construct() {}
    private static function connect(string $name,string $user,string $password): PDO
    {
        if(!preg_match('/^[a-zA-Z0-9_]{1,64}$/D',$name))throw new \RuntimeException('Nama database tidak valid.');
        $db=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.$name.';charset='.DB_CHARSET,$user,$password,[
            PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES=>false,PDO::ATTR_PERSISTENT=>false]);
        $db->exec("SET time_zone = '+07:00'");return $db;
    }
    public static function getInstance(): PDO
    {
        return self::$instance ??= self::connect(DB_NAME,DB_USER,DB_PASS);
    }
    /** Original database is the registry; it never follows a client session. */
    public static function platform(): PDO
    {
        if(!\App\Services\PlatformContext::enabled())return self::getInstance();
        return self::$platform ??= self::connect(DB_NAME,DB_USER,DB_PASS);
    }
    public static function resetWorkspace(): void
    {
        self::$instance=self::platform();self::$boundWorkspace=1;
    }
    public static function useWorkspace(int $id): void
    {
        if($id<1)throw new \DomainException('Workspace tidak valid.');
        $q=self::platform()->prepare('SELECT * FROM platform_workspaces WHERE id=? AND state=\'ready\'');$q->execute([$id]);$row=$q->fetch();
        if(!$row)throw new \DomainException('Workspace tidak aktif atau belum tersedia.');
        if(self::$boundWorkspace===$id && self::$instance)return;
        if($id===1){self::$instance=self::platform();self::$boundWorkspace=1;return;}
        if(!$row['db_name'] || !$row['db_user'] || !$row['db_secret'])throw new \DomainException('Konfigurasi workspace belum tersedia.');
        $db=self::connect($row['db_name'],$row['db_user'],(new \App\Services\GatewaySecret())->decrypt($row['db_secret']));
        self::$instance=$db;self::$boundWorkspace=$id;
    }
    public static function query(string $query,array $params=[]): \PDOStatement
    {
        $q=self::getInstance()->prepare($query);$q->execute($params);return $q;
    }
}
