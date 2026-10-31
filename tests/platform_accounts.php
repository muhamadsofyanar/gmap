<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/../config/database.php';
use App\Services\PlatformAccounts;
use App\Services\NotificationService;
use App\Services\WorkspaceProvisioner;
use App\Models\PlatformSettings;
if(!defined('APP_ENV'))define('APP_ENV','production');
if(!defined('CONFIG_PATH'))define('CONFIG_PATH',__DIR__.'/../config');
putenv('XSENDER_ENCRYPTION_KEY='.base64_encode(str_repeat('k',32)));
check(class_exists(PlatformAccounts::class),'account lifecycle service exists');
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->exec('CREATE TABLE platform_workspaces(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,state TEXT DEFAULT "pending"); CREATE TABLE platform_accounts(id INTEGER PRIMARY KEY AUTOINCREMENT,workspace_id INTEGER,local_user_id INTEGER,email TEXT UNIQUE,username TEXT UNIQUE,name TEXT,phone TEXT DEFAULT "",state TEXT DEFAULT "pending",password_hash TEXT,verified_channel TEXT,is_platform_admin INTEGER DEFAULT 0); CREATE TABLE platform_tokens(id INTEGER PRIMARY KEY AUTOINCREMENT,token_hash TEXT UNIQUE,purpose TEXT,account_id INTEGER,workspace_id INTEGER,email TEXT,phone TEXT,channel TEXT,payload TEXT,expires_at INTEGER,consumed_at INTEGER); CREATE TABLE platform_settings(id INTEGER PRIMARY KEY,payload TEXT); CREATE TABLE rate_limits(bucket TEXT PRIMARY KEY,attempts INTEGER,reset_at INTEGER)');
class AccountNotifications extends NotificationService {
    public array $sent=[];
    public function ready(string $channel):bool{return true;}
    public function send(array $recipient,string $purpose,string $url):array{$this->sent[]=[$recipient,$purpose,$url];return ['status'=>'accepted','http_code'=>200];}
}
class AccountProvisioner extends WorkspaceProvisioner {
    public static function ready():bool{return true;}
    public function provision(int $id,array $owner):int{global $db;$db->prepare("UPDATE platform_workspaces SET state='ready' WHERE id=?")->execute([$id]);return 7;}
}
$settings=new PlatformSettings($db);$notify=new AccountNotifications($db,$settings);$accounts=new PlatformAccounts($db,$notify,new AccountProvisioner());
$input=['email'=>'owner@example.test','username'=>'owner','name'=>'Owner','phone'=>'081234567890','password'=>'Secure123!','password_confirmation'=>'Secure123!'];
expectDenied(fn()=>$accounts->register($input),'registration disabled by default');
$settings->save(['registration_enabled'=>1,'approval_required'=>1]);
$accounts->register($input);
$account=$db->query('SELECT * FROM platform_accounts')->fetch();
check($account['state']==='pending'&&password_verify('Secure123!',$account['password_hash']),'pending owner retains password hash');
check(count($notify->sent)===1,'verification notification sent');
parse_str(parse_url($notify->sent[0][2],PHP_URL_QUERY),$query);$token=$query['token'];
check(strlen($token)===64,'token contains 32 random bytes');
$row=$db->query('SELECT * FROM platform_tokens')->fetch();
check($row['token_hash']===hash('sha256',$token)&&!str_contains(json_encode($row),$token),'token stored as hash only');
check($row['purpose']==='verify'&&$row['expires_at']>time()+1700,'verification purpose and expiry');
check($accounts->inspectToken($token,'verify')!==null,'GET token inspection allowed');
check($db->query('SELECT consumed_at FROM platform_tokens')->fetchColumn()===null,'inspection does not consume');
expectDenied(fn()=>$accounts->inspectToken($token,'reset'),'token purpose cannot change');
$accounts->verify($token);
$account=$db->query('SELECT * FROM platform_accounts')->fetch();
check($account['state']==='verified-pending-approval'&&$account['verified_channel']==='mailketing','verified account awaits approval');
expectDenied(fn()=>$accounts->verify($token),'verification replay rejected');
$accounts->forgot('owner@example.test');
check(count($notify->sent)===1,'pending account cannot recover password');
$before=count($notify->sent);$accounts->resend('missing@example.test');check(count($notify->sent)===$before,'unknown resend has no notification');
expectDenied(fn()=>$accounts->register($input+['role'=>'admin']),'duplicate registration rejected');
$accounts->resend('owner@example.test');check(count($notify->sent)===$before,'verified account receives no new verification');
check($accounts->authenticate('owner@example.test','Secure123!')===false,'pending account cannot authenticate');
$settings->save(['approval_required'=>0]);
$input['email']='second@example.test';$input['username']='second';$accounts->register($input);
parse_str(parse_url(end($notify->sent)[2],PHP_URL_QUERY),$second);$accounts->verify($second['token']);
$active=$db->query("SELECT * FROM platform_accounts WHERE username='second'")->fetch();
check($active['state']==='active'&&$active['password_hash']===null&&(int)$active['local_user_id']===7,'activation clears central password copy');

class LocalAccounts extends PlatformAccounts {
    public PDO $local;
    public ?array $actor=null;
    protected function currentAccount():?array{return $this->actor;}
    protected function workspaceDb(int $id):PDO{return $this->local;}
}
$local=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$local->exec('CREATE TABLE users(id INTEGER PRIMARY KEY AUTOINCREMENT,username TEXT UNIQUE,email TEXT UNIQUE,name TEXT,password TEXT,role TEXT,workspace_id INTEGER DEFAULT 1,deleted_at TEXT,session_version INTEGER DEFAULT 0)');
$local->prepare("INSERT INTO users(id,username,email,name,password,role) VALUES(7,'second','second@example.test','Second',?,'admin')")->execute([password_hash_secure('Secure123!')]);
$localAccounts=new LocalAccounts($db,$notify,new AccountProvisioner());$localAccounts->local=$local;
$localAccounts->forgot('second@example.test');$last=end($notify->sent);
check($last[1]==='reset'&&$last[0]['email']==='second@example.test'&&$last[0]['channel']==='mailketing','recovery uses stored verified destination');
parse_str(parse_url($last[2],PHP_URL_QUERY),$reset);$db->exec("UPDATE platform_tokens SET expires_at=0 WHERE purpose='reset'");
expectDenied(fn()=>$localAccounts->inspectToken($reset['token'],'reset'),'expired reset rejected');

$user=$localAccounts->authenticate('second','Secure123!');
check($user!==false&&(int)$user['id']===7&&(int)$user['tenant_id']===(int)$active['workspace_id']&&!isset($user['password']),'authenticate returns local identity and central binding only');
check($localAccounts->authenticate('second','wrong-password')===false,'bad local password denied');
$local->exec("UPDATE users SET email='Second@Example.Test' WHERE id=7");
check($localAccounts->authenticate('second','Secure123!')!==false,'legacy mixed-case email authenticates through normalized registry');
$local->exec("UPDATE users SET email='second@example.test' WHERE id=7");
$local->exec("UPDATE users SET deleted_at='2026-01-01' WHERE id=7");
check($localAccounts->authenticate('second','Secure123!')===false,'deleted local identity denied');
$local->exec('UPDATE users SET deleted_at=NULL WHERE id=7');
$localAccounts->forgot('second@example.test');$firstReset=end($notify->sent);parse_str(parse_url($firstReset[2],PHP_URL_QUERY),$firstResetToken);
$localAccounts->forgot('second@example.test');$secondReset=end($notify->sent);parse_str(parse_url($secondReset[2],PHP_URL_QUERY),$secondResetToken);
$localAccounts->reset($firstResetToken['token'],['password'=>'Changed123!','password_confirmation'=>'Changed123!']);
check(password_verify('Changed123!',$local->query('SELECT password FROM users WHERE id=7')->fetchColumn()),'reset updates tenant-local password');
check((int)$local->query('SELECT session_version FROM users WHERE id=7')->fetchColumn()===1,'reset invalidates existing local sessions');
expectDenied(fn()=>$localAccounts->reset($firstResetToken['token'],['password'=>'Again123!','password_confirmation'=>'Again123!']),'reset replay denied');
expectDenied(fn()=>$localAccounts->inspectToken($secondResetToken['token'],'reset'),'successful reset revokes other reset links');
check($db->query("SELECT password_hash FROM platform_accounts WHERE username='second'")->fetchColumn()===null,'reset never copies active password centrally');
$_SERVER['REMOTE_ADDR']='198.51.100.1';
$localAccounts->forgot('second@example.test');parse_str(parse_url(end($notify->sent)[2],PHP_URL_QUERY),$staleReset);
$local->exec('UPDATE users SET session_version=session_version+1 WHERE id=7');
expectDenied(fn()=>$localAccounts->reset($staleReset['token'],['password'=>'Stale123!','password_confirmation'=>'Stale123!']),'password/session version change invalidates previously issued reset');
check(password_verify('Changed123!',$local->query('SELECT password FROM users WHERE id=7')->fetchColumn()),'stale reset does not alter current local password');
expectDenied(fn()=>$localAccounts->inspectToken($staleReset['token'],'reset'),'stale reset is consumed fail-closed');

$inviteToken=bin2hex(random_bytes(32));
$db->prepare("INSERT INTO platform_tokens(token_hash,purpose,workspace_id,email,phone,channel,expires_at) VALUES(?,'invite',?,'member@example.test','081234567890','mailketing',?)")->execute([hash('sha256',$inviteToken),$active['workspace_id'],time()+172800]);
$memberInput=['username'=>'member','name'=>'Member','password'=>'Member123!','password_confirmation'=>'Member123!','role'=>'admin','email'=>'attacker@example.test','workspace_id'=>999];
$localAccounts->accept($inviteToken,$memberInput);
$member=$local->query("SELECT * FROM users WHERE username='member'")->fetch();$centralMember=$db->query("SELECT * FROM platform_accounts WHERE username='member'")->fetch();
check($member['role']==='user'&&$member['email']==='member@example.test','invitation role and email cannot be changed by acceptance input');
check((int)$centralMember['workspace_id']===(int)$active['workspace_id']&&$centralMember['verified_channel']==='mailketing','invitation fixes registry workspace and channel');
expectDenied(fn()=>$localAccounts->accept($inviteToken,$memberInput),'invitation replay denied');
// Original workspace uses a single PDO for registry and users; it must not nest transactions.
$db->exec('CREATE TABLE users(id INTEGER PRIMARY KEY AUTOINCREMENT,username TEXT UNIQUE,email TEXT UNIQUE,name TEXT,password TEXT,role TEXT,workspace_id INTEGER DEFAULT 1,deleted_at TEXT,session_version INTEGER DEFAULT 0)');
$sameAccounts=new LocalAccounts($db,$notify,new AccountProvisioner());$sameAccounts->local=$db;
$sameToken=bin2hex(random_bytes(32));$db->prepare("INSERT INTO platform_tokens(token_hash,purpose,workspace_id,email,phone,channel,expires_at) VALUES(?,'invite',?,'same@example.test','','mailketing',?)")->execute([hash('sha256',$sameToken),$active['workspace_id'],time()+172800]);
$sameAccounts->accept($sameToken,['name'=>'Same','username'=>'same','password'=>'Same123!','password_confirmation'=>'Same123!']);
check($db->query("SELECT role FROM users WHERE username='same'")->fetchColumn()==='user','same-PDO acceptance succeeds without nested transaction');
expectDenied(fn()=>$accounts->invite(['email'=>'evil@example.test']),'unauthenticated invite requires owner');
expectDenied(fn()=>$accounts->approve((int)$active['id']),'unauthenticated approval requires platform administrator');
expectDenied(fn()=>$accounts->disable((int)$active['id']),'unauthenticated disable requires platform administrator');
expectDenied(fn()=>$accounts->publicSettings(),'unauthenticated settings read denied');
expectDenied(fn()=>$accounts->saveSettings(['registration_enabled'=>1]),'unauthenticated settings mutation denied');
expectDenied(fn()=>$accounts->testNotification('mailketing','evil@example.test'),'unauthenticated test notification denied');
expectDenied(fn()=>$accounts->listAccounts(),'unauthenticated registry listing denied');
expectDenied(fn()=>$accounts->members(),'unauthenticated workspace members listing denied');

class RetryingProvisioner extends AccountProvisioner {
    public int $attempts=0;
    public function provision(int $id,array $owner):int{if(++$this->attempts===1)throw new RuntimeException('simulated provisioning failure');return parent::provision($id,$owner);}
}
$settings->save(['channel'=>'mailketing']);
$retryProvisioner=new RetryingProvisioner();$retryAccounts=new PlatformAccounts($db,$notify,$retryProvisioner);
$retryAccounts->register(['email'=>'retry@example.test','username'=>'retry','name'=>'Retry','phone'=>'','password'=>'Retry123!','password_confirmation'=>'Retry123!']);
parse_str(parse_url(end($notify->sent)[2],PHP_URL_QUERY),$retryLink);
$failed=false;try{$retryAccounts->verify($retryLink['token']);}catch(RuntimeException $e){$failed=true;}
check($failed,'provisioning failure is not claimed as activation success');
$retry=$db->query("SELECT * FROM platform_accounts WHERE username='retry'")->fetch();
check($retry['state']==='pending'&&$retry['password_hash']!==null&&$retry['verified_channel']===null,'failed provisioning rolls central lifecycle back to retryable pending');
check($retryAccounts->inspectToken($retryLink['token'],'verify')!==null,'failed provisioning does not burn verification token');
$retryAccounts->verify($retryLink['token']);
check($db->query("SELECT state FROM platform_accounts WHERE username='retry'")->fetchColumn()==='active','verification can retry after provisioning failure');
$_SERVER['REMOTE_ADDR']='127.0.0.2';$settings->save(['channel'=>'starsender']);$notifyCount=count($notify->sent);$localAccounts->forgot('member@example.test');$recovery=end($notify->sent);
check(count($notify->sent)===$notifyCount+1&&$recovery[0]['channel']==='mailketing'&&$recovery[0]['email']==='member@example.test','global channel changes never redirect recovery from verified destination');
$db->exec("UPDATE platform_accounts SET verified_channel=NULL WHERE username='member'");$notifyCount=count($notify->sent);$localAccounts->forgot('member@example.test');
check(count($notify->sent)===$notifyCount,'unverified destination receives no recovery fallback');
$_SERVER['REMOTE_ADDR']='198.51.100.2';$settings->save(['channel'=>'mailketing']);
$notifyCount=count($notify->sent);$localAccounts->resend('member@example.test');
check(count($notify->sent)===$notifyCount,'unauthenticated resend never verifies an active legacy destination');
$localAccounts->actor=$active;$localAccounts->resend('member@example.test');
check(count($notify->sent)===$notifyCount,'one logged-in account cannot request verification of another active account');
$localAccounts->actor=$centralMember;$localAccounts->resend('member@example.test');
check(count($notify->sent)===$notifyCount+1,'logged-in matching active account can prove its existing destination');
parse_str(parse_url(end($notify->sent)[2],PHP_URL_QUERY),$destinationLink);
$destinationToken=$localAccounts->inspectToken($destinationLink['token'],'verify');$payload=json_decode($destinationToken['payload'],true);
check($payload['kind']==='destination'&&$payload['session_version']===0,'destination verification token binds current credential version');
$localAccounts->actor=null;
expectDenied(fn()=>$localAccounts->verify($destinationLink['token']),'destination proof requires a logged-in matching account');
$localAccounts->actor=$centralMember;$local->prepare('UPDATE users SET session_version=1 WHERE id=?')->execute([$centralMember['local_user_id']]);
expectDenied(fn()=>$localAccounts->verify($destinationLink['token']),'credential change invalidates destination verification link');
$localAccounts->resend('member@example.test');parse_str(parse_url(end($notify->sent)[2],PHP_URL_QUERY),$freshDestinationLink);
$localAccounts->verify($freshDestinationLink['token']);
check($db->query("SELECT verified_channel FROM platform_accounts WHERE username='member'")->fetchColumn()==='mailketing','authenticated destination proof records verified recovery channel');
expectDenied(fn()=>$localAccounts->verify($freshDestinationLink['token']),'destination verification proof cannot replay');
$localAccounts->actor=null;

$_SERVER['REMOTE_ADDR']='192.0.2.1';
$accounts->throttle('independent-token-attempt','',1);
expectDenied(fn()=>$accounts->throttle('independent-token-attempt','',1),'empty identifier retains per-IP attempt budget');
$_SERVER['REMOTE_ADDR']='192.0.2.2';
$independent=true;try{$accounts->throttle('independent-token-attempt','',1);}catch(DomainException $e){$independent=false;}
check($independent,'empty identifier never shares a global account bucket across different IPs');
$accounts->throttle('shared-target','known-account',1);
$_SERVER['REMOTE_ADDR']='192.0.2.3';
expectDenied(fn()=>$accounts->throttle('shared-target','known-account',1),'nonempty account identifier retains shared per-account budget across IPs');
echo "OK {$checks} platform account checks\n";


