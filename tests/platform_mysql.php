<?php
if(PHP_SAPI!=='cli'||getenv('PLATFORM_MYSQL_TEST')!=='1'||!preg_match('/^lqa_[a-f0-9]{8}$/D',getenv('DB_NAME')?:''))exit(1);
$server=new PDO('mysql:host=127.0.0.1;port='.getenv('DB_PORT'),'root','',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name=getenv('DB_NAME');$server->exec('CREATE DATABASE `'.$name.'`');
foreach(['DB_USER'=>'DB_PASS','PLATFORM_PROVISION_USER'=>'PLATFORM_PROVISION_PASSWORD'] as $user=>$pass){$server->exec('CREATE USER '.$server->quote(getenv($user))."@'%' IDENTIFIED BY ".$server->quote(getenv($pass)));}
$server->exec('GRANT ALL PRIVILEGES ON `'.str_replace('_','\\_',$name).'`.* TO '.$server->quote(getenv('DB_USER'))."@'%'");
$server->exec('GRANT CREATE USER ON *.* TO '.$server->quote(getenv('PLATFORM_PROVISION_USER'))."@'%'");
$tenantPattern=str_replace('_','\\_',$name).'\\_client\\_%';
$server->exec('GRANT ALL PRIVILEGES ON `'.$tenantPattern.'`.* TO '.$server->quote(getenv('PLATFORM_PROVISION_USER'))."@'%' WITH GRANT OPTION");
require __DIR__.'/../scripts/bootstrap.php';
use App\Core\Database;use App\Services\PlatformInstaller;use App\Services\Installer;use App\Services\PlatformAccounts;use App\Services\NotificationService;use App\Services\PlatformContext;
$checks=0;
function qa(bool $yes,string $message):void{global $checks;$checks++;if(!$yes)throw new RuntimeException('QA failed: '.$message);}
function denied(callable $fn,string $message):void{try{$fn();}catch(DomainException|InvalidArgumentException $e){qa(true,$message);return;}qa(false,$message);}
$db=Database::platform();Installer::schema($db,ROOT_PATH.'/database.sql');App\Models\InboxRepository::install($db);
$ownerPass='Original!Owner_2026';Installer::bootstrapAdmin($db,['ADMIN_USERNAME'=>'originaladmin','ADMIN_EMAIL'=>'root@example.test','ADMIN_NAME'=>'Root','ADMIN_PASSWORD'=>$ownerPass]);PlatformInstaller::install($db);
class CapturePlatformMail extends NotificationService {
 public array $sent=[];
 public function send(array $recipient,string $purpose,string $url):array{$this->sent[]=['recipient'=>$recipient,'purpose'=>$purpose,'url'=>$url];return ['status'=>'accepted','http_code'=>200];}
}
$mail=new CapturePlatformMail($db);$settings=new App\Models\PlatformSettings($db);$settings->save(['registration_enabled'=>1,'channel'=>'mailketing','mailketing_api_key'=>'qa-not-real','from_email'=>'sender@example.test']);
$accounts=new PlatformAccounts($db,$mail);
function lastToken(CapturePlatformMail $mail,string $purpose):string{foreach(array_reverse($mail->sent) as $entry)if($entry['purpose']===$purpose){parse_str(parse_url($entry['url'],PHP_URL_QUERY)?:'',$query);return $query['token']??'';}throw new RuntimeException('QA notification missing');}
function authQA(PlatformAccounts $accounts,string $identifier,string $password):array|false{static $request=0;$_SERVER['REMOTE_ADDR']='192.0.2.'.(++$request);return $accounts->authenticate($identifier,$password);}
function loginSession(array $user):void{$_SESSION=['user_id'=>(int)$user['id'],'role'=>$user['role'],'session_version'=>(int)$user['session_version'],'last_activity'=>time(),'tenant_id'=>(int)$user['tenant_id'],'platform_account_id'=>(int)$user['platform_account_id']];}
foreach(['alpha','beta'] as $slug){
 $pass='New!Client_'.$slug.'_2026';$accounts->register(['name'=>ucfirst($slug),'username'=>$slug,'email'=>$slug.'@example.test','phone'=>'','password'=>$pass,'password_confirmation'=>$pass]);
 $token=lastToken($mail,'verification');qa(strlen($token)===64,'strong verify token');
 qa(!authQA($accounts,$slug,$pass),'pending account cannot login');
 $accounts->verify($token);denied(fn()=>$accounts->verify($token),'verify token single use');
 qa((bool)authQA($accounts,$slug,$pass),'verified client login');
}
$alpha=authQA($accounts,'alpha','New!Client_alpha_2026');$beta=authQA($accounts,'beta','New!Client_beta_2026');
qa($alpha['tenant_id']!==$beta['tenant_id'],'client registry IDs differ');
Database::useWorkspace($alpha['tenant_id']);$a=Database::getInstance();$a->exec("INSERT INTO projects(id,name,keyword,location) VALUES(100,'Alpha private','qa','qa')");
Database::useWorkspace($beta['tenant_id']);$b=Database::getInstance();$b->exec("INSERT INTO projects(id,name,keyword,location) VALUES(100,'Beta private','qa','qa')");
qa($b->query('SELECT name FROM projects WHERE id=100')->fetchColumn()==='Beta private','same row ID is isolated in beta');
Database::useWorkspace($alpha['tenant_id']);qa(Database::getInstance()->query('SELECT name FROM projects WHERE id=100')->fetchColumn()==='Alpha private','same row ID is isolated in alpha');
try{$a->query('SELECT * FROM `'.$name.'`.platform_accounts');qa(false,'client database credential cannot read central registry');}catch(PDOException $e){qa(true,'client database credential cannot read central registry');}
$betaName=$b->query('SELECT DATABASE()')->fetchColumn();
try{$a->query('SELECT * FROM `'.$betaName.'`.projects');qa(false,'alpha credential cannot read beta database');}catch(PDOException $e){qa(true,'alpha credential cannot read beta database');}
// A database whose name matches unescaped SQL underscore wildcards must remain inaccessible.
$alphaName=$a->query('SELECT DATABASE()')->fetchColumn();$shadow=str_replace('_','x',$alphaName);
$server->exec('CREATE DATABASE `'.$shadow.'`');$server->exec('CREATE TABLE `'.$shadow.'`.secret(value INT)');
try{$a->query('SELECT * FROM `'.$shadow.'`.secret');qa(false,'client grants do not leak via underscore wildcards');}catch(PDOException $e){qa(true,'client grants do not leak via underscore wildcards');}
loginSession($alpha);qa(PlatformContext::requireOwner()['workspace_id']==$alpha['tenant_id'],'owner context binding');denied(fn()=>PlatformContext::requirePlatformAdmin(),'client cannot become platform administrator');
$accounts->invite(['email'=>'member@example.test','phone'=>'']);$invite=lastToken($mail,'invite');
$accounts->accept($invite,['name'=>'Member','username'=>'member','password'=>'Member!Password_2026','password_confirmation'=>'Member!Password_2026','role'=>'admin','workspace_id'=>$beta['tenant_id']]);
$member=authQA($accounts,'member','Member!Password_2026');qa($member && $member['role']==='user' && $member['tenant_id']===$alpha['tenant_id'],'invitation fixes workspace and user role');
loginSession($member);denied(fn()=>PlatformContext::requireOwner(),'member cannot invite or manage owner settings');
$accounts->forgot('alpha@example.test');$reset=lastToken($mail,'reset');$new='Changed!ClientAlpha_2026';$accounts->reset($reset,['password'=>$new,'password_confirmation'=>$new]);
denied(fn()=>$accounts->reset($reset,['password'=>$new,'password_confirmation'=>$new]),'reset token cannot replay');
qa(!authQA($accounts,'alpha','New!Client_alpha_2026'),'old password revoked');$changed=authQA($accounts,'alpha',$new);qa((bool)$changed,'new password works');
loginSession($alpha);qa(PlatformContext::current()===null,'old owner session invalid after reset');
$root=authQA($accounts,'originaladmin',$ownerPass);loginSession($root);qa((bool)PlatformContext::requirePlatformAdmin(),'original bootstrap admin can configure platform');
// Workspace1 invitations share the central PDO; nested transactions must not occur.
$accounts->invite(['email'=>'rootmember@example.test','phone'=>'']);$rootInvite=lastToken($mail,'invite');$accounts->accept($rootInvite,['name'=>'Root Member','username'=>'rootmember','password'=>'RootMember!Pass_2026','password_confirmation'=>'RootMember!Pass_2026']);
qa((bool)authQA($accounts,'rootmember','RootMember!Pass_2026'),'original workspace invitation works');
loginSession($root);$accounts->disable((int)$db->query("SELECT id FROM platform_accounts WHERE username='beta'")->fetchColumn());qa(!authQA($accounts,'beta','New!Client_beta_2026'),'disabled client cannot login');
qa($db->query("SELECT COUNT(*) FROM platform_accounts WHERE state='active' AND password_hash IS NOT NULL")->fetchColumn()==0,'active central accounts have no password copy');
loginSession($changed);App\Services\ClientCallback::save('123');$callback=App\Services\ClientCallback::settings();parse_str(parse_url($callback['url'],PHP_URL_QUERY),$callbackQuery);
if(getenv('PLATFORM_QA_CONTEXT_FILE')){file_put_contents(getenv('PLATFORM_QA_CONTEXT_FILE'),json_encode(['callback_token'=>$callbackQuery['token']]));chmod(getenv('PLATFORM_QA_CONTEXT_FILE'),0600);}
qa(App\Services\ClientCallback::routeToken($callbackQuery['token'])['workspace_id']==$changed['tenant_id'],'callback token binds workspace server-side');
// HTTP QA uses one additional valid reset token; created only inside this disposable server.
$account=$db->query("SELECT * FROM platform_accounts WHERE username='alpha'")->fetch();
Database::useWorkspace((int)$account['workspace_id']);$version=Database::getInstance()->query('SELECT session_version FROM users WHERE id='.(int)$account['local_user_id'])->fetchColumn();
$db->prepare('INSERT INTO platform_tokens(token_hash,purpose,account_id,workspace_id,email,phone,channel,payload,expires_at) VALUES(?,?,?,?,?,?,?,?,?)')->execute([hash('sha256',str_repeat('7',64)),'reset',$account['id'],$account['workspace_id'],$account['email'],$account['phone'],$account['verified_channel'],json_encode(['session_version'=>(int)$version]),time()+1200]);
$db->exec('DELETE FROM rate_limits');
echo "Real MariaDB platform: $checks checks passed\n";
