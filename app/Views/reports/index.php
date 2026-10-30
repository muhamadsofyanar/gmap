<?php
$rupiah=static fn($value)=>'Rp '.number_format((float)$value,0,',','.');
$percent=static fn($part,$whole)=>(int)$whole ? round(100*(int)$part/(int)$whole,1) : 0;
$crm=(int)$totals['crm_total'];
$funnel=[
    ['Masuk CRM',$crm,'Prospek yang Anda pilih untuk dikelola.'],
    ['Sudah dihubungi',(int)$totals['contacted'],'Pernah berstatus dihubungi atau lebih lanjut.'],
    ['Tertarik / negosiasi',(int)$totals['interested'],'Sedang berstatus tertarik atau negosiasi.'],
    ['Closing',(int)$totals['closing'],'Sudah berstatus closing.'],
];
?>
<section class="crm-workspace reports-page">
    <header class="page-heading"><div><p class="eyebrow">HASIL PENJUALAN</p><h1>Laporan</h1><p>Pantau perjalanan prospek dari masuk CRM sampai menjadi pembelian, untuk semua proyek.</p></div><a href="<?= e(base_url('crm')) ?>" class="crm-secondary">Buka CRM</a></header>

    <section class="report-kpis" aria-label="Ringkasan semua proyek">
        <article class="report-kpi"><span>Prospek di CRM</span><strong><?= $crm ?></strong><small>dari <?= (int)$totals['prospects'] ?> prospek hasil pencarian</small></article>
        <article class="report-kpi"><span>Sudah dihubungi</span><strong><?= (int)$totals['contacted'] ?></strong><small><?= $percent($totals['contacted'],$crm) ?>% dari prospek CRM</small></article>
        <article class="report-kpi"><span>Closing</span><strong><?= (int)$totals['closing'] ?></strong><small>Konversi <?= $percent($totals['closing'],$crm) ?>% · nilai deal <?= $rupiah($totals['deal_value']) ?></small></article>
        <article class="report-kpi" data-tone="money"><span>Pendapatan tercatat</span><strong><?= $rupiah($totals['revenue']) ?></strong><small>dari <?= (int)$totals['orders'] ?> transaksi pembelian</small></article>
    </section>

    <section class="crm-panel crm-detail-card">
        <h2>Alur prospek</h2>
        <p class="crm-footnote">Berapa banyak prospek CRM yang sampai ke setiap tahap. Semakin panjang batang, semakin banyak prospek.</p>
        <ol class="report-funnel">
            <?php foreach ($funnel as [$label,$value,$hint]): ?>
                <li><div class="report-funnel-label"><strong><?= e($label) ?></strong><small><?= e($hint) ?></small></div><progress max="<?= max(1,$crm) ?>" value="<?= $value ?>"><?= $percent($value,$crm) ?>%</progress><span class="report-funnel-value"><?= $value ?> <small>(<?= $percent($value,$crm) ?>%)</small></span></li>
            <?php endforeach; ?>
        </ol>
    </section>

    <section class="crm-panel crm-detail-card">
        <h2>Hasil per proyek</h2>
        <p class="crm-footnote">Klik nama proyek untuk membuka prospeknya di CRM.</p>
        <div class="crm-table-scroll"><table class="crm-table sales-report-table"><thead><tr><th>Proyek</th><th>Pencarian</th><th>Prospek / CRM</th><th>Dihubungi</th><th title="Tertarik / negosiasi">Negosiasi</th><th title="Closing / konversi CRM">Closing</th><th title="Nilai deal closing">Nilai deal</th><th>Transaksi</th><th>Pendapatan</th></tr></thead><tbody>
            <?php foreach ($reports as $report): ?><tr><td><a class="crm-text-button" href="<?= e(base_url('crm?project_id='.(int)$report['id'])) ?>"><?= e($report['name']) ?></a></td><td class="sales-report-muted"><?= e($report['keyword'].' / '.$report['location']) ?></td><td><?= (int)$report['prospects'] ?> / <?= (int)$report['crm_total'] ?></td><td><?= (int)$report['contacted'] ?></td><td><?= (int)$report['interested'] ?></td><td><?= (int)$report['closing'] ?> (<?= $percent($report['closing'],$report['crm_total']) ?>%)</td><td><?= $rupiah($report['deal_value']) ?></td><td><?= (int)$report['orders'] ?></td><td><?= $rupiah($report['revenue']) ?></td></tr><?php endforeach; ?>
            <?php if (!$reports): ?><tr><td colspan="9">Belum ada proyek. Buat proyek pencarian terlebih dahulu.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>

    <details class="crm-panel crm-detail-card report-glossary">
        <summary>Cara membaca laporan</summary>
        <dl>
            <dt>Prospek / CRM</dt><dd>Jumlah hasil pencarian Google Maps / jumlah yang sudah dimasukkan ke CRM.</dd>
            <dt>Dihubungi</dt><dd>Prospek yang status CRM-nya pernah menjadi dihubungi atau lebih lanjut. Klik tombol WhatsApp saja tidak dihitung.</dd>
            <dt>Konversi</dt><dd>Prospek berstatus closing dibagi jumlah prospek di CRM.</dd>
            <dt>Nilai deal</dt><dd>Perkiraan nilai penawaran pada prospek closing, bukan uang yang sudah diterima.</dd>
            <dt>Transaksi &amp; pendapatan</dt><dd>Pembelian yang Anda catat di detail prospek. Menghapus prospek secara permanen juga menghapus catatan pembeliannya.</dd>
        </dl>
    </details>
</section>
