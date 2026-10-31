<?php
require __DIR__.'/conversations.php';
use App\Services\ContactLabel;
check(ContactLabel::name('.',null,'6281234567890')==='6281234567890','punctuation name falls back to phone');
check(ContactLabel::name('   ','---','6281234567890')==='6281234567890','empty and punctuation names ignored');
check(ContactLabel::name('Toko A','Push name','62')==='Toko A','CRM name preferred');
check(ContactLabel::name(null,'Bapak Budi','62')==='Bapak Budi','meaningful push name retained');
check(ContactLabel::name(null,null,null)==='Penerima belum diketahui','unknown identity remains explicit');
// Store a contact before a matching prospect exists, then reconcile recorded history.
$late=$event;$late['contact_phone']='6281234567801';$late['message_id']='late-link';$inbox->ingest($late);
$anchor=(int)$db->query("SELECT id FROM whatsapp_inbox WHERE message_id='late-link'")->fetchColumn();
$db->exec("INSERT INTO project_leads(id,project_id,workspace_id,shop_name,phone,in_crm) VALUES(80,1,1,'Late prospect','081234567801',0)");
expectDenied(fn()=>$inbox->reconcile($alice,$anchor),'member cannot reconcile admin queue');
check($inbox->reconcile($admin,$anchor)===80,'exact unique phone reconciled');
check((int)$db->query('SELECT lead_id FROM whatsapp_inbox WHERE id='.$anchor)->fetchColumn()===80,'stored event linked');
$amb=$late;$amb['contact_phone']='6281234567802';$amb['message_id']='ambiguous-link';$inbox->ingest($amb);
$ambId=(int)$db->query("SELECT id FROM whatsapp_inbox WHERE message_id='ambiguous-link'")->fetchColumn();
$db->exec("INSERT INTO project_leads(id,project_id,workspace_id,shop_name,phone) VALUES(81,1,1,'First','081234567802'),(82,1,1,'Second','081234567802')");
expectDenied(fn()=>$inbox->reconcile($admin,$ambId),'ambiguous phone never guessed');
check($db->query('SELECT lead_id FROM whatsapp_inbox WHERE id='.$ambId)->fetchColumn()===null,'ambiguous event remains unlinked');
$foreign=$late;$foreign['message_id']='foreign-context';$foreign['contact_phone']='6281234567801';$inbox->ingest($foreign);
$foreignId=(int)$db->query("SELECT id FROM whatsapp_inbox WHERE message_id='foreign-context'")->fetchColumn();
$db->exec('UPDATE whatsapp_inbox SET lead_id=13 WHERE id='.$foreignId);
expectDenied(fn()=>$inbox->reconcile($admin,$foreignId),'cannot reconcile conversation from another workspace');
expectDenied(fn()=>$inbox->link($admin,$foreignId,80),'cannot relink conversation from another workspace');
echo "Usability: $checks checks passed\n";
