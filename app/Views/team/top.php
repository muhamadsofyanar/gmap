<?php $title=!empty($inboxPage)?'Kotak masuk WhatsApp':($isAdmin?'Kelola tim dan penugasan':'Prospek saya'); ?>
<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> · <?= e(APP_NAME) ?></title><link rel="stylesheet" href="<?= e(base_url('assets/css/team.css')) ?>"></head>
<body><header><a class="brand" href="<?= e(base_url('team')) ?>"><?= e(APP_NAME) ?></a><nav>
<?php if($isAdmin): ?><a href="<?= e(base_url('dashboard')) ?>">Dashboard admin</a><?php endif ?>
<a href="<?= e(base_url('inbox')) ?>">Kotak masuk</a><a href="<?= e(base_url('team')) ?>"><?= $isAdmin?'Tim & penugasan':'Prospek saya' ?></a><a href="<?= e(base_url('profile')) ?>">Profil</a>
<form method="post" action="<?= e(base_url('logout')) ?>"><?= csrf_field() ?><button class="secondary">Keluar</button></form></nav></header>
<main><div class="page-heading"><div><span class="eyebrow">WORKSPACE TIM</span><h1><?= e($title) ?></h1><p><?= e($_SESSION['user_name'] ?? '') ?> · <?= $isAdmin?'Admin':'Anggota' ?></p></div></div>
<?php foreach(get_flashes() as $flash): ?><p class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></p><?php endforeach ?>
