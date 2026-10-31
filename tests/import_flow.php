<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/../config/database.php';require __DIR__.'/sqlite_compat.php';
use App\Core\Database;use App\Models\IntakeModel;
$path=tempnam(sys_get_temp_dir(),'intake-db-');unlink($path);
$fixture=fixture();$fixture->exec('VACUUM INTO '.$fixture->quote($path));
try {
$db=new IntakeTestPDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
$db->sqliteCreateFunction('DATABASE',static fn()=>'fixture');$db->sqliteCreateFunction('NOW',static fn()=>date('Y-m-d H:i:s'));
(new ReflectionProperty(Database::class,'instance'))->setValue(null,$db);
(new ReflectionProperty(App\Services\WhatsAppCheckMigration::class,'applied'))->setValue(null,['fixture'=>true]);
$db->exec("CREATE TABLE crm_intake_batches(token TEXT PRIMARY KEY,project_id INTEGER,project_name TEXT,source TEXT,payload TEXT,mapping TEXT,result TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
CREATE TABLE crm_intake_chunks(batch_token TEXT,chunk_no INTEGER,content BLOB);
CREATE TABLE crm_lead_sources(lead_id INTEGER,source TEXT);
ALTER TABLE projects ADD COLUMN keyword TEXT; ALTER TABLE projects ADD COLUMN location TEXT;
ALTER TABLE projects ADD COLUMN status TEXT; ALTER TABLE projects ADD COLUMN total_leads INTEGER DEFAULT 0;");
$model=new IntakeModel();$token=str_repeat('a',32);
$payload=['headers'=>['Nama bisnis','Telepon'],'rows'=>[['Import A','081234567892'],['Import duplicate','081234567892'],['Invalid','nope']]];
expectDenied(fn()=>$model->draft(str_repeat('c',32),2,'',$payload,'csv'),'other workspace project denied');
$model->draft($token,1,'',$payload,'csv');
$db->exec("UPDATE crm_intake_batches SET created_at=datetime('now','+7 hours')");
expectDenied(fn()=>$model->map($token,['shop_name'=>'0','phone'=>'0']),'duplicate mapping rejected');
$model->map($token,['shop_name'=>'0','phone'=>'1']);
$review=$model->review($model->batch($token));
check(array_column($review,'eligible')===[true,false,false],'duplicates and invalid number identified');
$result=$model->commit($token,['0','1','2']);check($result['inserted']===1 && $result['skipped']===2,'only valid selected row saved');
check((int)$db->query("SELECT in_crm FROM project_leads WHERE shop_name='Import A'")->fetchColumn()===0,'import starts in project before CRM');
check($result['project_id']===1,'import remains in selected project');
check($db->query("SELECT phone FROM project_leads WHERE shop_name='Import A'")->fetchColumn()==='6281234567892','phone normalized');
check($model->commit($token,['0','1','2'])===$result,'repeated submit does not duplicate');
$rows=[];for($i=0;$i<1000;$i++)$rows[]=['Unique import '.$i,''];
$next=str_repeat('b',32);$model->draft($next,0,'New import project',['headers'=>['Nama bisnis','Telepon'],'rows'=>$rows],'xlsx');
$db->exec("UPDATE crm_intake_batches SET created_at=datetime('now','+7 hours')");$model->map($next,['shop_name'=>'0','phone'=>'1']);
$r=$model->commit($next,array_map('strval',range(0,999)));check($r['inserted']===1000,'1000 selected rows saved');check($r['project_id']!==1,'new project created');
check((int)$db->query('SELECT total_leads FROM projects WHERE id='.$r['project_id'])->fetchColumn()===1000,'project counter updated');
echo "Import flow: $checks checks passed (test-only SQLite SQL translation)\n";
}finally{unlink($path);}
