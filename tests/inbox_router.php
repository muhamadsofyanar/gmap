<?php
// Local test server only: never route production requests through this file.
if (PHP_SAPI!=='cli-server' || !getenv('INBOX_TEST_DB')) {http_response_code(404);exit;}
require_once __DIR__.'/../config/config.php';
require_once __DIR__.'/../config/database.php';
require_once __DIR__.'/bootstrap.php';
$db=new PDO('sqlite:'.getenv('INBOX_TEST_DB'),null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
(new ReflectionProperty(App\Core\Database::class,'instance'))->setValue(null,$db);
$db->sqliteCreateFunction('DATABASE',static fn()=>'fixture');
(new ReflectionProperty(App\Services\WhatsAppCheckMigration::class,'applied'))->setValue(null,['fixture'=>true]);
$db->sqliteCreateFunction('NOW',static fn()=>date('Y-m-d H:i:s'));
(new ReflectionProperty(App\Models\GatewayModel::class,'migrated'))->setValue(null,true);
(new ReflectionProperty(App\Models\BroadcastModel::class,'migrated'))->setValue(null,true);
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if ($path==='/test-login') {
    $id=(int)($_GET['id'] ?? 0);
    $row=$db->query('SELECT * FROM users WHERE id='.$id)->fetch();
    if (!$row) {http_response_code(404);exit;}
    $dir=ROOT_PATH.'/storage/sessions';if(!is_dir($dir)) mkdir($dir,0700,true);
    session_save_path($dir);session_name(SESSION_COOKIE_NAME);session_start();
    $_SESSION=['user_id'=>$id,'role'=>$row['role'],'session_version'=>$row['session_version'],'last_activity'=>time()];
    echo 'ok';return;
}
if ($path==='/crm/sendWhatsApp') {
    session_save_path(ROOT_PATH.'/storage/sessions');session_name(SESSION_COOKIE_NAME);session_start();
    App\Services\AuthGuard::guard('CrmController');
    // No network calls: exercise the real controller, validation and reservation with a local gateway response.
    $controller=new class extends App\Controllers\CrmController {
        protected function gatewayService(string $provider): object {return new class {public function send(array $credentials,string $number,string $message):array {return ['status'=>'accepted','http_code'=>200];}};}
    };
    $controller->sendWhatsApp();return;
}
if ($path==='/starsender-webhook.php') {require __DIR__.'/../public/starsender-webhook.php';return;}
if (str_starts_with($path,'/assets/') && is_file(__DIR__.'/../public'.$path)) return false;
$_GET['url']=ltrim($path,'/');require __DIR__.'/../public/index.php';
