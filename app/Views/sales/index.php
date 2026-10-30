<?php
$days=['Minggu','Senin','Selasa','Rabu','Kamis','Jumat','Sabtu'];
$months=['','Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
$tabs=[
    'all'=>['Semua',(int)($counts['open_count']??0)],
    'today'=>['Hari ini',(int)($counts['today']??0)],
    'overdue'=>['Terlambat',(int)($counts['overdue']??0)],
    'upcoming'=>['Mendatang',(int)($counts['upcoming']??0)],
    'repeat'=>['Repeat order',(int)($counts['repeat_count']??0)],
];
$emptyText=[
    'all'=>'Belum ada aktivitas yang perlu dikerjakan.',
    'today'=>'Tidak ada aktivitas untuk hari ini.',
    'overdue'=>'Bagus, tidak ada aktivitas yang terlambat.',
    'upcoming'=>'Belum ada aktivitas terjadwal untuk hari-hari berikutnya.',
    'repeat'=>'Belum ada jadwal repeat order.',
];
$now=time();
?>
<section class="crm-workspace activity-page">
    <header class="page-heading"><div><p class="eyebrow">TINDAK LANJUT</p><h1>Aktivitas</h1><p>Daftar follow-up dan repeat order yang perlu Anda kerjakan. Tandai selesai setelah menghubungi prospek.</p></div><a href="<?= e(base_url('crm')) ?>" class="primary-action">Buka CRM</a></header>

    <nav class="activity-tabs" aria-label="Saring aktivitas">
        <?php foreach ($tabs as $key=>[$label,$count]): ?>
            <a href="<?= e(base_url('sales?due='.$key)) ?>" data-tone="<?= e($key) ?>" <?= $dueFilter===$key?'aria-current="page"':'' ?>><?= e($label) ?><span><?= $count ?></span></a>
        <?php endforeach; ?>
    </nav>

    <section class="crm-panel crm-detail-card activity-list" aria-label="Daftar aktivitas">
        <?php foreach ($tasks as $task):
            $due=strtotime($task['due_at']);
            $state=$due<$now ? 'overdue' : (date('Y-m-d',$due)===date('Y-m-d',$now) ? 'today' : 'upcoming');
            $stateLabel=['overdue'=>'Terlambat','today'=>'Hari ini','upcoming'=>'Terjadwal'][$state];
        ?>
            <article class="activity-item">
                <div class="activity-main">
                    <p class="activity-tags"><span class="activity-badge" data-state="<?= $state ?>"><?= $stateLabel ?></span><span class="activity-kind"><?= $task['kind']==='repeat_order'?'Repeat order':'Follow-up' ?></span></p>
                    <h2><?= e($task['title']) ?></h2>
                    <p class="activity-meta"><?= e($days[(int)date('w',$due)].', '.date('j',$due).' '.$months[(int)date('n',$due)].' '.date('Y · H:i',$due)) ?> WIB &middot; <a class="crm-text-button" href="<?= e(base_url('crm/detail?id='.(int)$task['lead_id'].'#salesTasks')) ?>"><?= e($task['shop_name']) ?></a> <span>(<?= e($task['project_name']) ?>)</span></p>
                </div>
                <form method="post" action="<?= e(base_url('reminders/complete')) ?>"><?= csrf_field() ?><input type="hidden" name="lead_id" value="<?= (int)$task['lead_id'] ?>"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><input type="hidden" name="due" value="<?= e($dueFilter) ?>"><button class="crm-secondary" type="submit">Tandai selesai</button></form>
            </article>
        <?php endforeach; ?>

        <?php if (!$tasks): ?>
            <div class="crm-empty">
                <span class="crm-empty-icon" aria-hidden="true"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 5h16v16H4z M8 3v4 M16 3v4 M4 10h16 M9 15l2 2 4-4"/></svg></span>
                <h2><?= e($emptyText[$dueFilter]) ?></h2>
                <p>Untuk menambah aktivitas: buka <strong>CRM</strong>, pilih prospek, lalu isi bagian <strong>Tindak lanjut</strong>. Jadwal repeat order dibuat otomatis saat Anda mencatat pembelian.</p>
                <a href="<?= e(base_url('crm')) ?>" class="primary-action">Buka CRM</a>
            </div>
        <?php endif; ?>

        <?php if ($page>1 || $hasNext): ?>
            <footer class="crm-pagination"><span>Halaman <?= (int)$page ?></span><nav aria-label="Halaman aktivitas"><?php if ($page>1): ?><a class="crm-secondary" href="<?= e(base_url('sales?due='.$dueFilter.'&page='.($page-1))) ?>">Sebelumnya</a><?php endif; ?><?php if ($hasNext): ?><a class="crm-secondary" href="<?= e(base_url('sales?due='.$dueFilter.'&page='.($page+1))) ?>">Berikutnya</a><?php endif; ?></nav></footer>
        <?php endif; ?>
    </section>
    <p class="crm-footnote">Pengingat muncul di ikon lonceng dan dashboard saat aplikasi dibuka. Aplikasi tidak mengirim pesan otomatis.</p>
</section>
