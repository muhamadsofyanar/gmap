<?php
if(PHP_SAPI!=='cli')exit(1);
try {
    require __DIR__.'/bootstrap.php';
    $db=\App\Core\Database::getInstance();
    $db->query('SELECT assigned_user_id FROM project_leads LIMIT 0');
    $context=stream_context_create(['http'=>['timeout'=>5,'ignore_errors'=>true,'follow_location'=>0]]);
    $response=@file_get_contents('http://127.0.0.1/login',false,$context);
    $status=$http_response_header[0] ?? '';
    if($response===false || !preg_match('/HTTP\/\S+ (200|301|302) /',$status))exit(1);
    exit(0);
} catch(Throwable $e) {exit(1);}
