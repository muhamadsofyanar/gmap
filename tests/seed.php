<?php
require __DIR__.'/../scripts/bootstrap.php';
if(APP_ENV!=='development' || !str_ends_with(DB_NAME,'_test') || getenv('ALLOW_TEST_RESET')!=='1') throw new RuntimeException('Requires development, *_test database and ALLOW_TEST_RESET=1.');
$db=\App\Core\Database::getInstance();
$db->exec('SET FOREIGN_KEY_CHECKS=0');
foreach($db->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $table) {
    if(!preg_match('/^[a-zA-Z0-9_]+$/D',$table))throw new RuntimeException('Invalid table name');
    $db->exec('DROP TABLE `'.$table.'`');
}
$db->exec('SET FOREIGN_KEY_CHECKS=1');
\App\Services\Installer::schema($db,ROOT_PATH.'/database.sql');
\App\Services\Installer::bootstrapAdmin($db,getenv());
$before=$db->query('SELECT password FROM users WHERE id=1')->fetchColumn();
\App\Services\Installer::schema($db,ROOT_PATH.'/database.sql');
\App\Services\Installer::bootstrapAdmin($db,array_replace(getenv(),['ADMIN_PASSWORD'=>'ChangedOnRedeploy!']));
if($db->query('SELECT password FROM users WHERE id=1')->fetchColumn()!==$before) throw new RuntimeException('Bootstrap reset password');
$repo=new \App\Models\TeamRepository($db,['id'=>1,'workspace_id'=>1,'role'=>'admin','session_version'=>0]);
$repo->createMember('alice','alice@example.test','Alice','LongPassword123!');
$repo->createMember('bob','bob@example.test','Bob','LongPassword123!');
$db->exec("INSERT INTO projects(id,name,keyword,location,total_leads) VALUES(1,'Shared project','coffee','Jakarta',3)");
$q=$db->prepare('INSERT INTO project_leads (id,project_id,shop_name,assigned_user_id,in_crm) VALUES (?,1,?,?,1)');
$q->execute([10,'Alice assigned business',2]);$q->execute([11,'Bob secret business',3]);$q->execute([12,'Unassigned business',null]);
$db->exec("INSERT INTO crm_attachments(id,lead_id,name,mime,size,content) VALUES(20,10,'alice.pdf','application/pdf',5,'hello'),(21,11,'bob.pdf','application/pdf',10,'SECRET-BOB')");
$db->exec("INSERT INTO crm_tasks(id,lead_id,title,due_at,request_token) VALUES(30,10,'Alice follow-up','2026-11-01 10:00:00','aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa'),(31,11,'Bob hidden task','2026-11-01 10:00:00','bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb')");
echo "MariaDB schema/bootstrap fixture OK\n";
