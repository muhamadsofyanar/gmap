<?php
error_reporting(E_ALL);
$checks=0;
spl_autoload_register(function(string $class):void {
    if(str_starts_with($class,'App\\')) {
        $file=__DIR__.'/../app/'.str_replace('\\','/',substr($class,4)).'.php';
        if(is_file($file)) require_once $file;
    }
});
require __DIR__.'/../app/Core/Helpers.php';
function check(bool $condition,string $message):void {
    global $checks; $checks++;
    if(!$condition) throw new RuntimeException('FAIL: '.$message);
}
function expectDenied(callable $action,string $message):void {
    try {$action();} catch (DomainException|InvalidArgumentException $e) {check(true,$message);return;}
    check(false,$message);
}
function fixture():PDO {
    $db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
    $db->exec("CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT,username TEXT UNIQUE,email TEXT UNIQUE,name TEXT,password TEXT,role TEXT,workspace_id INTEGER DEFAULT 1,deleted_at TEXT,session_version INTEGER DEFAULT 0,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE projects(id INTEGER PRIMARY KEY,name TEXT,workspace_id INTEGER DEFAULT 1);
    CREATE TABLE project_leads(id INTEGER PRIMARY KEY,project_id INTEGER,workspace_id INTEGER DEFAULT 1,assigned_user_id INTEGER,shop_name TEXT,category TEXT,address TEXT,phone TEXT,whatsapp TEXT,website TEXT,gmap_url TEXT,lead_status TEXT DEFAULT 'new',notes TEXT,deal_value NUMERIC DEFAULT 0,in_crm INTEGER DEFAULT 1,crm_added_at TEXT,last_follow_up TEXT,follow_up_count INTEGER DEFAULT 0,updated_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE crm_funnel_history(id INTEGER PRIMARY KEY AUTOINCREMENT,lead_id INTEGER,from_status TEXT,to_status TEXT,action_type TEXT,notes TEXT,deal_value NUMERIC DEFAULT 0,actor_user_id INTEGER,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE crm_tasks(id INTEGER PRIMARY KEY AUTOINCREMENT,lead_id INTEGER,title TEXT,due_at TEXT,completed_at TEXT,kind TEXT DEFAULT 'follow_up',request_token TEXT UNIQUE,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE crm_attachments(id INTEGER PRIMARY KEY AUTOINCREMENT,lead_id INTEGER,name TEXT,mime TEXT,size INTEGER,content BLOB,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    CREATE TABLE crm_attachment_chunks(attachment_id INTEGER,chunk_no INTEGER,content BLOB);
    CREATE TABLE team_audit(id INTEGER PRIMARY KEY AUTOINCREMENT,workspace_id INTEGER,actor_user_id INTEGER,lead_id INTEGER,action TEXT,details TEXT,created_at TEXT DEFAULT CURRENT_TIMESTAMP);
    INSERT INTO users(id,username,email,name,password,role,workspace_id) VALUES(1,'admin','admin@x.test','Admin','hash','admin',1),(2,'alice','alice@x.test','Alice','hash','user',1),(3,'bob','bob@x.test','Bob','hash','user',1),(4,'outsider','out@x.test','Out','hash','user',2);
    INSERT INTO projects VALUES(1,'Team project',1),(2,'Other workspace',2);
    INSERT INTO project_leads(id,project_id,assigned_user_id,shop_name,workspace_id) VALUES(10,1,2,'Alice lead',1),(11,1,3,'Bob lead',1),(12,1,NULL,'Unassigned',1),(13,2,2,'Other workspace',2);
    INSERT INTO crm_tasks(id,lead_id,title,due_at,request_token) VALUES(30,10,'A task','2026-11-01 10:00:00','a'),(31,11,'B task','2026-11-01 10:00:00','b');
    INSERT INTO crm_attachments(id,lead_id,name,mime,size,content) VALUES(20,10,'a.pdf','application/pdf',5,'hello'),(21,11,'b.pdf','application/pdf',6,'secret');");
    return $db;
}
