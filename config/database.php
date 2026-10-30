<?php
namespace App\Core;
use PDO;

final class Database
{
    private static ?PDO $instance=null;
    private function __construct() {}
    public static function getInstance(): PDO
    {
        if (self::$instance===null) {
            self::$instance=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT.';dbname='.DB_NAME.';charset='.DB_CHARSET,DB_USER,DB_PASS,[
                PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES=>false,
                PDO::ATTR_PERSISTENT=>false,
            ]);
            self::$instance->exec("SET time_zone = '+07:00'");
        }
        return self::$instance;
    }
    public static function query(string $query,array $params=[]): \PDOStatement
    {
        $q=self::getInstance()->prepare($query);$q->execute($params);return $q;
    }
}
