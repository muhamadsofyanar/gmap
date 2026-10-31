<?php
require __DIR__.'/bootstrap.php';
require __DIR__.'/../config/config.php';
$db=fixture();$db->exec("UPDATE project_leads SET phone='081234567890' WHERE id=10");
App\Models\InboxRepository::install($db);
$db->exec("ALTER TABLE projects ADD COLUMN total_leads INTEGER DEFAULT 0;
CREATE TABLE crm_lead_sources(lead_id INTEGER PRIMARY KEY,source TEXT);
CREATE TABLE whatsapp_deliveries(id INTEGER PRIMARY KEY,request_token TEXT UNIQUE,lead_id INTEGER,template_id INTEGER,recipient TEXT,message TEXT,status TEXT,http_code INTEGER,updated_at TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
ALTER TABLE projects ADD COLUMN keyword TEXT;ALTER TABLE projects ADD COLUMN location TEXT;
CREATE TABLE message_templates(id INTEGER PRIMARY KEY,name TEXT,body TEXT);
CREATE TABLE crm_contact_blocks(contact_key TEXT,reason TEXT);
CREATE TABLE whatsapp_broadcasts(token TEXT PRIMARY KEY,project_id INTEGER,template_id INTEGER,template_name TEXT,recipients TEXT,total INTEGER,invalid_count INTEGER,duplicate_count INTEGER,mark_contacted INTEGER);");
$db->exec("CREATE TABLE whatsapp_gateway (id INTEGER PRIMARY KEY,provider TEXT,enabled INTEGER,api_key_encrypted TEXT,sender TEXT DEFAULT '')");
$db->prepare('INSERT INTO whatsapp_gateway(id,provider,enabled,api_key_encrypted) VALUES (1,?,?,?)')->execute(['starsender',1,(new App\Services\GatewaySecret())->encrypt('11111111-2222-4333-8444-555555555555')]);
$repo=new App\Models\InboxRepository($db);
$repo->ingest(App\Services\StarsenderWebhook::normalize(['device_id'=>'123','message_id'=>'ui-1','from'=>'081234567890','message'=>'Hello <script>alert(1)</script>','is_me'=>false,'is_group'=>false,'chat_type'=>'personal','timestamp'=>time(),'push_name'=>'Customer'],'123'));
$db->exec('VACUUM INTO '.$db->quote($argv[1]));
