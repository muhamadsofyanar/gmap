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
/** Record field shapes only. Never record input values, message text, names or credentials. */
function webhook_field_shapes(array $event): array {
    $fields=[];
    foreach (['device_id','message_id','from','message','push_name','file','is_group','is_me','chat_type','timestamp'] as $key) {
        if (!array_key_exists($key,$event)) {$fields[$key]=['type'=>'missing'];continue;}
        $value=$event[$key];$info=['type'=>get_debug_type($value)];
        if (is_string($value)) $info['length']=strlen($value);
        if (in_array($key,['is_group','is_me'],true)) {
            $info['shape']=match(true) {
                $value===true || $value===1 || $value==='1' || $value==='true'=>'boolean_true',
                $value===false || $value===0 || $value==='0' || $value==='false'=>'boolean_false',
                default=>'other',
            };
        } elseif ($key==='chat_type') {
            $info['shape']=in_array($value,['personal','group'],true)?$value:'other';
        } elseif ($key==='from') {
            $info['shape']=match(true) {
                is_string($value) && preg_match('/^\+?[0-9 ().-]{8,30}$/D',$value)===1=>'phone',
                is_string($value) && str_ends_with($value,'@s.whatsapp.net')=>'whatsapp_jid',
                is_string($value) && str_ends_with($value,'@g.us')=>'group_jid',
                is_string($value) && str_ends_with($value,'@lid')=>'lid',
                default=>'other',
            };
        } elseif ($key==='timestamp') {
            $info['shape']=(is_int($value) || (is_string($value) && ctype_digit($value))) ? 'numeric_'.strlen((string)$value).'_digits' : 'other';
        }
        $fields[$key]=$info;
    }
    return $fields;
}
if (($_SERVER['REQUEST_METHOD'] ?? '')!=='POST') {
    header('Allow: POST');webhook_response(405,['success'=>false,'message'=>'Gunakan POST.']);
}
$secret=getenv('STARSENDER_WEBHOOK_TOKEN') ?: '';
$device=getenv('STARSENDER_DEVICE_ID') ?: '';
if (!preg_match('/^[a-f0-9]{64}$/D',$secret) || !preg_match('/^(?:[A-Za-z0-9_-]{1,100}|sha256:[a-f0-9]{64})$/D',$device)) webhook_response(503,['success'=>false,'message'=>'Webhook belum dikonfigurasi.']);
$token=$_SERVER['HTTP_X_WEBHOOK_TOKEN'] ?? $_GET['token'] ?? '';
if (!is_string($token) || !hash_equals($secret,$token)) webhook_response(403,['success'=>false,'message'=>'Akses ditolak.']);
if ((int)($_SERVER['CONTENT_LENGTH'] ?? 0)>262144) webhook_response(413,['success'=>false,'message'=>'Payload terlalu besar.']);
$input=fopen('php://input','rb');$body=stream_get_contents($input,262145);fclose($input);
if ($body===false || strlen($body)>262144) webhook_response(413,['success'=>false,'message'=>'Payload terlalu besar.']);
$event=null;
try {
    $event=json_decode($body,true,32,JSON_THROW_ON_ERROR);
    if (!is_array($event) || array_is_list($event)) throw new InvalidArgumentException();
    require_once __DIR__.'/../app/Services/StarsenderWebhook.php';
    $deviceKey=null;
    $incomingId=$event['device_id'] ?? null;
    if (is_string($incomingId) && preg_match('/^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$/iD',$incomingId)
        && !hash_equals($device,$incomingId) && !hash_equals($device,'sha256:'.hash('sha256',$incomingId))) {
        try {
            require_once __DIR__.'/../config/config.php';
            require_once __DIR__.'/../config/database.php';
            require_once __DIR__.'/../app/Services/GatewaySecret.php';
            // Read the existing active gateway only; do not migrate or change settings here.
            $row=\App\Core\Database::getInstance()->query('SELECT provider,enabled,api_key_encrypted FROM whatsapp_gateway WHERE id=1')->fetch();
            if ($row && $row['provider']==='starsender' && (int)$row['enabled']===1 && is_string($row['api_key_encrypted']) && $row['api_key_encrypted']!=='') {
                $deviceKey=(new \App\Services\GatewaySecret())->decrypt($row['api_key_encrypted']);
            }
        } catch (Throwable $lookupError) {
            error_log('Starsender gateway identity unavailable: '.get_class($lookupError));
            webhook_response(503,['success'=>false,'message'=>'Verifikasi perangkat belum tersedia.']);
        }
    }
    $normalized=\App\Services\StarsenderWebhook::normalize($event,$device,$deviceKey);
    unset($deviceKey);
} catch (JsonException|InvalidArgumentException $e) {
    // All allowed reasons are constant messages from our validator, never provider values.
    $allowedReasons=['Perangkat tidak sesuai.','Penanda pesan tidak valid.','Jenis pesan tidak valid.',
        'ID pesan tidak valid.','Isi pesan tidak valid.','Waktu pesan tidak valid.',
        'Waktu pesan di masa depan.','Media tidak valid.','Kontak tidak valid.'];
    $reason=$e instanceof JsonException ? 'JSON tidak valid.' : (in_array($e->getMessage(),$allowedReasons,true)?$e->getMessage():'Objek pesan tidak valid.');
    $diagnostic=['reason'=>$reason,'body_bytes'=>strlen($body)];
    if (is_array($event)) {
        $id=$event['device_id'] ?? null;
        $diagnostic['device_matches']=(is_int($id) || is_string($id)) && ((string)$id===$device || hash_equals($device,'sha256:'.hash('sha256',(string)$id)));
        if ((is_int($id) || is_string($id)) && strlen((string)$id)<=100 && (string)$id!=='') $diagnostic['device_fingerprint']='sha256:'.hash('sha256',(string)$id);
        $diagnostic['fields']=webhook_field_shapes($event);
        if (is_array($event['data'] ?? null)) $diagnostic['nested_data_fields']=webhook_field_shapes($event['data']);
    }
    error_log('Starsender webhook rejected: '.json_encode($diagnostic,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE));
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
