<?php
require __DIR__.'/messaging.php';
$db->exec("UPDATE project_leads SET lead_status='contacted' WHERE id=11; UPDATE project_leads SET lead_status='negotiation' WHERE id=12;");
check(array_column($broadcast->candidates(1,['contacted']),'id')===[11],'filter one pipeline stage');
check(array_column($broadcast->candidates(1,['new','negotiation']),'id')===[10,12,14],'filter multiple stages');
expectDenied(fn()=>$broadcast->candidates(1,['invalid']),'unknown stage rejected');
expectDenied(fn()=>$broadcast->prepare(str_repeat('e',32),1,$template,false,[10],['contacted']),'selection outside chosen stage rejected');
$stageBatch=$broadcast->prepare(str_repeat('e',32),1,$template,false,[10],['new']);
check($stageBatch['stage_filters']===['new'],'stage filters survive snapshot');
expectDenied(fn()=>$broadcast->prepare(str_repeat('e',32),1,$template,false,[10],[]),'stage intent cannot change on token replay');
check($broadcast->find(str_repeat('b',32))['stage_filters']===[],'legacy snapshot readable');
echo "Pipeline broadcast: $checks checks passed\n";
