<?php
namespace App\Services;

class PrivateStorage
{
    private static function target(string $kind): array
    {
        return match ($kind) {
            'attachment'=>['crm_attachment_chunks','attachment_id'],
            'intake'=>['crm_intake_chunks','batch_token'],
            default=>throw new \InvalidArgumentException('Jenis penyimpanan tidak valid.')
        };
    }

    // Call inside the parent metadata transaction. Each packet stays below 1 MB.
    public static function write(\PDO $db, string $kind, int|string $id, string $content): void
    {
        [$table,$key]=self::target($kind);
        $q=$db->prepare('INSERT INTO '.$table.' ('.$key.',chunk_no,content) VALUES (?,?,?)');
        $part=0;
        for ($offset=0,$length=strlen($content);$offset<$length;$offset+=262144) {
            $q->bindValue(1,$id); $q->bindValue(2,$part++,\PDO::PARAM_INT); $q->bindValue(3,substr($content,$offset,262144),\PDO::PARAM_LOB); $q->execute();
        }
    }

    public static function read(\PDO $db, string $kind, int|string $id): string
    {
        [$table,$key]=self::target($kind);
        $q=$db->prepare('SELECT content FROM '.$table.' WHERE '.$key.'=? ORDER BY chunk_no'); $q->execute([$id]);
        $content=''; while (($part=$q->fetchColumn())!==false) $content.=$part;
        return $content;
    }
}
