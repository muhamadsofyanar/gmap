<?php
/** Workspace identity is derived only from a registered opaque callback token. */
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('Referrer-Policy: no-referrer');
$deny=static function(int $code): never {http_response_code($code);echo json_encode(['success'=>false,'message'=>'Callback tidak tersedia atau akses ditolak.']);exit;};
if(($_SERVER['REQUEST_METHOD']??'')!=='POST'){$deny(405);}
$token=$_SERVER['HTTP_X_WEBHOOK_TOKEN']??$_GET['token']??'';
if(!is_string($token) || !preg_match('/^[a-f0-9]{64}$/D',$token))$deny(403);
try{
    require_once __DIR__.'/../config/config.php';require_once __DIR__.'/../config/database.php';require_once __DIR__.'/../app/Core/Helpers.php';
    spl_autoload_register(static function(string $class):void{if(str_starts_with($class,'App\\')){$file=__DIR__.'/../app/'.str_replace('\\','/',substr($class,4)).'.php';if(is_file($file))require_once $file;}});
    $route=\App\Services\ClientCallback::routeToken($token);if(!$route)$deny(403);
    \App\Core\Database::useWorkspace((int)$route['workspace_id']);
    putenv('STARSENDER_WEBHOOK_TOKEN='.$token);putenv('STARSENDER_DEVICE_ID='.$route['device']);
    require __DIR__.'/starsender-webhook.php';
}catch(Throwable $e){error_log('Client callback unavailable: '.get_class($e));$deny(503);}
