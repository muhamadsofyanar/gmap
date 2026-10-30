<?php
require __DIR__.'/../scripts/bootstrap.php';
if(APP_ENV!=='development' || !str_ends_with(DB_NAME,'_test'))exit(1);
$_SESSION=['user_id'=>1];
$crm=new \App\Models\CrmModel();
$crm->addHistory(10,'new','contacted','test','Admin test history');
$history=$crm->getFunnelHistory(10);
if(!$history || (int)$history[0]['actor_user_id']!==1)throw new RuntimeException('Legacy actor history broken');
echo "PASS: legacy CRM history inserts and reads\n";
