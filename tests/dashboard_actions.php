<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/../config/database.php';
$db=fixture();(new ReflectionProperty(App\Core\Database::class,'instance'))->setValue(null,$db);
$db->exec("UPDATE project_leads SET lead_status='contacted' WHERE id=10; UPDATE project_leads SET lead_status='interested' WHERE id=11; UPDATE project_leads SET lead_status='contacted' WHERE id=13; UPDATE crm_tasks SET completed_at='2026-10-30 10:00:00' WHERE id=31");
$model=new App\Models\CrmModel();$stats=$model->getDashboardStats();
check((int)($stats['unscheduled_active']??-1)===1,'active unscheduled excludes existing tasks, new leads and foreign workspace');
check(array_column($model->unscheduledActive(1),'id')===[11],'dashboard drilldown matches counter');
echo "Dashboard actions: $checks checks passed\n";
