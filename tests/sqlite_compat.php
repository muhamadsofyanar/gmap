<?php
// Test-only SQL translation. This does not test MariaDB locking or concurrency.
class IntakeTestPDO extends PDO
{
    private static function sql(string $sql): string
    {
        $sql=str_replace([' FOR UPDATE','INSERT IGNORE','CURDATE()+INTERVAL 1 DAY'],['','INSERT OR IGNORE',"date('now','+7 hours','+1 day')"],$sql);
        return preg_replace("/DATE_SUB\\(NOW\\(\\), INTERVAL ([0-9]+) DAY\\)/", "datetime('now','+7 hours','-$1 day')",$sql);
    }
    public function prepare(string $query,array $options=[]): PDOStatement|false {return parent::prepare(self::sql($query),$options);}
    public function query(string $query,?int $fetchMode=null,mixed ...$args): PDOStatement|false
    {
        return $fetchMode===null?parent::query(self::sql($query)):parent::query(self::sql($query),$fetchMode,...$args);
    }
}
