<?php
require __DIR__.'/bootstrap.php';require __DIR__.'/../config/config.php';session_start();
$_SESSION=['role'=>'admin','user_name'=>'Demo'];$reminderTotal=0;$activeSection='inbox';$pageTitle='Inbox Demo';
$filter='all';$page=1;$historyPage=1;$search='';$isAdmin=true;
$lead=['id'=>10,'shop_name'=>'Toko Demo','project_name'=>'Demo','in_crm'=>1,'assigned_name'=>'Admin','lead_status'=>'contacted','deal_value'=>0,'project_id'=>1];
$context=['lead'=>$lead,'lead_id'=>10,'phone'=>'6281234567890','name'=>'Toko Demo','anchor'=>1,'device'=>null];
$event=['id'=>1,'source'=>'webhook','lead_id'=>10,'direction'=>'incoming','push_name'=>'Budi','contact_phone'=>'6281234567890','message'=>'Halo','occurred_at'=>'2026-10-31 10:00:00','reviewed_at'=>null,'file_url'=>null,'delivery_status'=>null];
$thread=['context'=>$context,'events'=>[$event],'hasOlder'=>false];
$groups=[$event+['shop_name'=>'Toko Demo','project_name'=>'Demo','unread_count'=>1]];
$replyGateway=['enabled'=>1,'has_key'=>true];$replyBlocked=false;$options=['projects'=>[],'members'=>[]];$candidates=[];
require __DIR__.'/../app/Views/layouts/header.php';require __DIR__.'/../app/Views/inbox/index.php';require __DIR__.'/../app/Views/layouts/footer.php';
