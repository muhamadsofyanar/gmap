<?php
/** Dedicated callback: no login session, browser redirects, or automatic replies. */
error_reporting(E_ALL);
ini_set('display_errors','0');
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
function webhook_response(int $code,array $data): never {
    http_response_code($code);echo json_encode($data,JSON_UNESCAPED_UNICODE);exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') {
    header('Allow: POST');webhook_response(405,['success'=>false,'message'=>'Gunakan POST.']);
}
$secret=getenv('STARSENDER_WEBHOOK_TOKEN') ?: '';
$device=getenv('STARSENDER_DEVICE_ID') ?: '';
if (!preg_match('/^[a-f0-9]{64}$/D',$secret) || !preg_match('/^[A-Za-z0-9_-]{1,100}$/D',$device)) webhook_response(503,['success'=>false,'message'=>'Webhook belum dikonfigurasi.']);
$token=$_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? $_GET['token'] ?? '';
if (!is_string($token) || !hash_equals($secret,$token)) webhook_response(403,['success'=>false,'message'=>'Akses ditolak.']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>262144) webhook_response(413,['success'=>false,'message'=>'Payload terlalu besar.']);
$input=fopen('php://input','rb');$body=stream_get_contents($input,262145);fclose($input);
if ($body===false || strlen($body)>262144) webhook_response(413,['success'=>false,'message'=>'Payload terlalu besar.']);
try {
    $event=json_decode($body,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($event) || array_is_list($event)) throw new InvalidArgumentException();
    require_once __DIR__.'/../app/Services/StarsenderWebhook.php';
    $normalized=\App\Services\StarsenderWebhook::normalize($event,$device);
} catch (JsonException|InvalidArgumentException $e) {
    webhook_response(422,['success'=>false,'message'=>'Payload tidak valid.']);
}
if ($normalized===null) webhook_response(200,['success'=>true,'ignored'=>true]);
try {
    require_once __DIR__.'/../config/config.php';
    require_once __DIR__.'/../config/database.php';
    require_once __DIR__.'/../app/Models/InboxRepository.php';
    $repo=new \App\Models\InboxRepository(\App\Core\Database::getInstance());
    $created=$repo->ingest($normalized);
    webhook_response(200,['success'=>true,'duplicate'=>!$created]);
} catch (Throwable $e) {
    // Never log secrets, bodies, phone numbers, query strings or raw provider data.
    error_log('Starsender webhook persistence failed: '.get_class($e));
    webhook_response(503,['success'=>false,'message'=>'Penyimpanan belum tersedia.']);
}
