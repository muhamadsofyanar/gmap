<?php
require __DIR__.'/bootstrap.php';
$db=fixture();$db->exec("CREATE TABLE whatsapp_deliveries(id INTEGER PRIMARY KEY,lead_id INTEGER,recipient TEXT,message TEXT,status TEXT,created_at TEXT); UPDATE project_leads SET phone='081234567890' WHERE id=10; UPDATE project_leads SET phone='081234567891' WHERE id=11;
INSERT INTO whatsapp_deliveries VALUES(1,10,'6281234567890','Reply','accepted','2026-10-30 17:05:00'),(2,11,'6281234567891','Failed','failed','2026-10-30 17:15:00');");
App\Models\InboxRepository::install($db);$inbox=new App\Models\InboxRepository($db);$repo=new App\Models\ConversationRepository($db);
$admin=['id'=>1,'role'=>'admin','workspace_id'=>1];$alice=['id'=>2,'role'=>'user','workspace_id'=>1];$bob=['id'=>3,'role'=>'user','workspace_id'=>1];
foreach(['081234567890','081234567891'] as $i=>$number){$event=App\Services\StarsenderWebhook::normalize(['device_id'=>'123','message_id'=>'reply-'.$i,'from'=>$number,'message'=>'Incoming','is_me'=>false,'is_group'=>false,'chat_type'=>'personal','timestamp'=>1793354400],'123');$event['sent_at']='2026-10-30 10:00:00';$inbox->ingest($event);}
check($repo->needsReplyCount($admin)===1,'failed outgoing does not resolve needs reply');
check($repo->needsReplyCount($alice)===0,'accepted reply resolves conversation');
check($repo->needsReplyCount($bob)===1,'member count limited to assigned contact');
check(array_map('intval',array_column($repo->groups($admin,'needs_reply'),'lead_id'))===[11],'needs-reply filter matches counter');
echo "Reply queue: $checks checks passed\n";
