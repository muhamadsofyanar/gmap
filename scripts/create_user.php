<?php
/** Recovery CLI only: create/reset an account with an explicit role. */
require __DIR__.'/bootstrap.php';
use App\Core\Database;
use App\Models\TeamRepository;
[, $username,$email,$name,$role]=array_pad($argv,5,null);
if(!$username || !$email || !$name || !in_array($role,['admin','user'],true) || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/D',$username) || !filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($email)>100 || strlen($name)>100) {
    fwrite(STDERR,"Pemakaian: php scripts/create_user.php <username> <email> \"<nama>\" <admin|user>\n");exit(1);
}
echo 'Password baru (12–72 byte, input terlihat di terminal): ';
$password=rtrim((string)fgets(STDIN),"\r\n");
try {
    TeamRepository::validatePassword($password);
    $db=Database::getInstance();$db->beginTransaction();
    $q=$db->prepare('SELECT id,username,email FROM users WHERE username=? OR email=? FOR UPDATE');$q->execute([$username,$email]);$rows=$q->fetchAll();
    if(count($rows)>1 || ($rows && ($rows[0]['username']!==$username || $rows[0]['email']!==$email))) throw new RuntimeException('Identitas username/email tidak cocok. Gunakan identitas akun yang sama.');
    if($rows) $db->prepare('UPDATE users SET name=?,password=?,role=?,deleted_at=NULL,session_version=session_version+1 WHERE id=?')->execute([$name,password_hash_secure($password),$role,$rows[0]['id']]);
    else $db->prepare('INSERT INTO users (username,email,name,password,role,workspace_id) VALUES (?,?,?,?,?,1)')->execute([$username,$email,$name,password_hash_secure($password),$role]);
    $db->commit();echo "Akun disimpan.\n";
} catch(Throwable $e) {if(isset($db) && $db->inTransaction())$db->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
