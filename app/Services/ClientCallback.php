<?php
namespace App\Services;
use App\Core\Database;

final class ClientCallback
{
    public static function settings(): array
    {
        $actor=PlatformContext::requireOwner();
        $q=Database::platform()->prepare('SELECT * FROM platform_callbacks WHERE workspace_id=?');$q->execute([$actor['workspace_id']]);$row=$q->fetch();
        return $row?['device'=>$row['device'],'url'=>base_url('client-starsender-webhook.php?token='.(new GatewaySecret())->decrypt($row['secret_encrypted']))]:['device'=>'','url'=>''];
    }
    public static function save(string $device,bool $rotate=false): void
    {
        $actor=PlatformContext::requireOwner();
        if(!preg_match('/^(?:[A-Za-z0-9_-]{1,100}|sha256:[a-f0-9]{64})$/D',$device))throw new \InvalidArgumentException('Gunakan Device ID atau fingerprint sha256 perangkat Starsender Anda.');
        $db=Database::platform();$db->beginTransaction();
        try{
            $q=$db->prepare('SELECT * FROM platform_callbacks WHERE workspace_id=? FOR UPDATE');$q->execute([$actor['workspace_id']]);$old=$q->fetch();
            $secret=$old && !$rotate?(new GatewaySecret())->decrypt($old['secret_encrypted']):bin2hex(random_bytes(32));
            $db->prepare('INSERT INTO platform_callbacks(workspace_id,secret_hash,secret_encrypted,device) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE secret_hash=VALUES(secret_hash),secret_encrypted=VALUES(secret_encrypted),device=VALUES(device),updated_at=CURRENT_TIMESTAMP')->execute([$actor['workspace_id'],hash('sha256',$secret),(new GatewaySecret())->encrypt($secret),$device]);
            $db->commit();
        }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
    }
    /** No session or caller-provided workspace id participates in callback routing. */
    public static function routeToken(string $token): ?array
    {
        if(!PlatformContext::enabled() || !preg_match('/^[a-f0-9]{64}$/D',$token))return null;
        $hash=hash('sha256',$token);$q=Database::platform()->prepare("SELECT c.workspace_id,c.device,c.secret_hash FROM platform_callbacks c JOIN platform_workspaces w ON w.id=c.workspace_id WHERE c.secret_hash=? AND w.state='ready'");$q->execute([$hash]);$row=$q->fetch();
        return $row && hash_equals($row['secret_hash'],$hash)?$row:null;
    }
}
