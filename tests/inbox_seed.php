<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/../config/config.php';
$db=fixture();$db->exec("UPDATE project_leads SET phone='081234567890' WHERE id=10");
App\Models\InboxRepository::install($db);
$db->exec('CREATE TABLE whatsapp_gateway (id INTEGER PRIMARY KEY,provider TEXT,enabled INTEGER,api_key_encrypted TEXT)');
$db->prepare('INSERT INTO whatsapp_gateway VALUES (1,?,?,?)')->execute(['starsender',1,(new App\Services\GatewaySecret())->encrypt('11111111-2222-4333-8444-555555555555')]);
$repo=new App\Models\InboxRepository($db);
$repo->ingest(App\Services\StarsenderWebhook::normalize(['device_id'=>'123','message_id'=>'ui-1','from'=>'081234567890','message'=>'Hello <script>alert(1)</script>','is_me'=>false,'is_group'=>false,'chat_type'=>'personal','timestamp'=>time(),'push_name'=>'Customer'],'123'));
$db->exec('VACUUM INTO '.$db->quote($argv[1]));
