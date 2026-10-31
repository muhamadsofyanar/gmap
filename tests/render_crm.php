<?php
// Isolated view fixture used by frontend DOM tests, never a production route.
require __DIR__.'/bootstrap.php';require __DIR__.'/../config/config.php';
session_start();
$_SESSION=['role'=>'admin','user_name'=>'Demo'];
$selectedProject=1;$selectedStatus='all';$searchQuery='';$dueFilter='all';$viewMode='list';
$project=['name'=>'Proyek Demo','keyword'=>'Bisnis','location'=>'Jakarta'];
$stats=['total_leads'=>2];$dueCounts=['overdue'=>0,'today'=>0];
$stages=App\Models\CrmModel::STAGES;$templates=[];$leads=[];$page=1;$pageSize=20;$hasNext=false;
$reminderTotal=0;$activeSection='crm';$pageTitle='LegaOne Demo';
require __DIR__.'/../app/Views/layouts/header.php';
require __DIR__.'/../app/Views/crm/index.php';
require __DIR__.'/../app/Views/layouts/footer.php';
