<!-- Dashboard Prospek Bisnis -->
<div class="space-y-6 dashboard-workspace">

    <section class="page-heading">
        <div><p class="eyebrow">RINGKASAN</p><h1>Dashboard</h1><p>Pantau prospek CRM, kontak pertama, dan tindak lanjut penjualan Anda.</p></div>
        <a href="<?= base_url('projects#projectSearch') ?>" class="primary-action"><span aria-hidden="true">+</span> Cari prospek baru</a>
    </section>
    <?php if (!$projects): ?>
    <section class="workspace-welcome"><div><p class="eyebrow">MULAI DARI SINI</p><h2>Bangun daftar prospek pertama Anda</h2><p>Cari jenis usaha dan lokasi, pilih bisnis yang sesuai, lalu lanjutkan penawaran melalui CRM.</p></div><a class="primary-action" href="<?= e(base_url('projects#projectSearch')) ?>">Cari prospek pertama</a></section>
    <?php endif; ?>
    <div class="workspace-stats" aria-label="Ringkasan prospek di CRM">
        <article class="workspace-stat" data-tone="green" data-dashboard-metric="total_leads"><span class="workspace-stat-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="4"/><path d="M2 21v-1a7 7 0 0 1 14 0v1M16 3.5a4 4 0 0 1 0 9M22 21v-1a7 7 0 0 0-4-6.3"/></svg></span><span>Total prospek di CRM</span><strong><?= number_format($stats['total_leads']) ?></strong><small>Seluruh prospek yang masuk ke CRM.</small></article>
        <article class="workspace-stat" data-tone="yellow" data-dashboard-metric="uncontacted"><span class="workspace-stat-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 11.5a8.5 8.5 0 0 1-8.5 8.5H4l-2 2V11.5A8.5 8.5 0 0 1 10.5 3h2a8.5 8.5 0 0 1 8.5 8.5Z"/></svg></span><span>Belum dihubungi</span><strong><?= number_format($stats['uncontacted']) ?></strong><small>Prospek yang masih di tahap awal.</small></article>
        <article class="workspace-stat" data-tone="red" data-dashboard-metric="awaiting_follow_up"><span class="workspace-stat-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg></span><span>Menunggu follow-up</span><strong><?= number_format($stats['awaiting_follow_up']) ?></strong><small>Sudah dihubungi, belum ada follow-up selesai.</small></article>
        <a class="workspace-stat" data-tone="leaf" data-dashboard-metric="closing" href="<?= e(base_url('reports')) ?>"><span class="workspace-stat-icon" aria-hidden="true"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/></svg></span><span>Total closing</span><strong><?= number_format($stats['closing']) ?></strong><small><?= $stats['total_leads'] ? round(100 * $stats['closing'] / $stats['total_leads'], 1) : 0 ?>% dari prospek CRM &middot; nilai deal Rp <?= number_format($stats['closing_value'], 0, ',', '.') ?></small></a>
    </div>

    <section class="crm-panel crm-detail-card dashboard-followup"><div class="dashboard-followup-head"><div><h2>Tindak lanjut penjualan</h2><p class="crm-footnote">Jadwalkan follow-up dan repeat order dari detail prospek CRM.</p></div><a class="crm-secondary" href="<?= e(base_url('sales')) ?>">Buka aktivitas</a></div>
        <div class="dashboard-followup-tiles">
            <a class="dashboard-followup-tile" data-tone="yellow" href="<?= e(base_url('sales?due=today')) ?>"><strong><?= (int)($reminders['today'] ?? 0) ?></strong><span>Aktivitas hari ini</span></a>
            <a class="dashboard-followup-tile" data-tone="red" href="<?= e(base_url('sales?due=overdue')) ?>"><strong><?= (int)($reminders['overdue'] ?? 0) ?></strong><span>Aktivitas terlambat</span></a>
            <a class="dashboard-followup-tile" data-tone="yellow" href="<?= e(base_url('dashboard/followups')) ?>"><strong><?= (int)($stats['unscheduled_active']??0) ?></strong><span>Prospek aktif tanpa jadwal</span></a>
            <a class="dashboard-followup-tile" data-tone="green" href="<?= e(base_url('inbox?filter=needs_reply')) ?>"><strong><?= (int)($needsReply??0) ?></strong><span>Pesan masuk perlu balasan</span></a>
        </div>
        <p class="crm-footnote">Tanpa jadwal = tahap Dihubungi/Berminat/Negosiasi tanpa aktivitas terbuka. Perlu balasan dihitung dari riwayat pesan yang tercatat, bukan status baca WhatsApp.</p>
    </section>

    <!-- Ringkasan Performa Proyek -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Ringkasan Proyek Terdaftar</h3>
                <p class="text-xs text-gray-500 mt-0.5">Daftar proyek yang tersimpan beserta metrik konversi data kontak.</p>
            </div>
            <a href="<?= base_url('projects') ?>" class="text-xs font-bold text-[#0f947e] hover:underline flex items-center space-x-1">
                <span>Kelola di Menu Proyek</span>
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
            </a>
        </div>

        <?php if (empty($projects)): ?>
        <div class="py-16 text-center">
            <div class="max-w-sm mx-auto text-center space-y-3">
                <div class="w-14 h-14 mx-auto rounded-lg bg-slate-100 flex items-center justify-center text-gray-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                </div>
                <h4 class="font-bold text-gray-800 text-sm">Belum Ada Proyek Terdaftar</h4>
                <p class="text-xs text-gray-500">Mulai buat proyek pencarian pertama Anda di menu proyek.</p>
                <div class="pt-2">
                    <a href="<?= base_url('projects') ?>" class="btn-brand inline-flex items-center px-4 py-2 rounded-lg text-xs font-bold">
                        <span>Buka Menu Proyek</span>
                    </a>
                </div>
            </div>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/75 border-b border-gray-200 text-[11px] font-semibold text-gray-500 uppercase tracking-wider">
                        <th class="py-3 px-5">Nama Proyek</th>
                        <th class="py-3 px-5">Target Pencarian</th>
                        <th class="py-3 px-5 text-center">Total Prospek</th>
                        <th class="py-3 px-5 text-center">Ada Nomor Kontak</th>
                        <th class="py-3 px-5 text-center">Website</th>
                        <th class="py-3 px-5">Tanggal Dibuat</th>
                        <th class="py-3 px-5 text-right">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-xs font-medium">
                    <?php foreach ($projects as $proj): 
                        $totalP = (int)$proj['count_total'];
                        $webP = (int)$proj['count_websites'];
                        $noWebP = max(0, $totalP - $webP);
                    ?>
                    <tr class="hover:bg-slate-50/80 transition-colors">
                        <td class="py-3.5 px-5">
                            <a href="<?= base_url('projects/viewProject/' . $proj['id']) ?>" 
                               class="font-semibold text-gray-900 hover:text-[#0f947e] transition-colors flex items-center space-x-1.5">
                                <span><?= e($proj['name']) ?></span>
                            </a>
                        </td>
                        <td class="py-3.5 px-5">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                <?= e($proj['keyword']) ?> &bull; <?= e($proj['location']) ?>
                            </span>
                        </td>
                        <td class="py-3.5 px-5 text-center font-bold text-gray-800">
                            <?= $totalP ?>
                        </td>
                        <td class="py-3.5 px-5 text-center font-bold text-emerald-600">
                            <?= (int)$proj['count_phones'] ?>
                        </td>
                        <td class="py-3.5 px-5 text-center">
                            <span class="status-chip" data-tone="neutral"><?= $webP ?> punya website</span>
                        </td>
                        <td class="py-3.5 px-5 text-gray-500 text-[11px]">
                            <?= date('d M Y, H:i', strtotime($proj['created_at'])) ?>
                        </td>
                        <td class="py-3.5 px-5 text-right whitespace-nowrap">
                            <div class="inline-flex items-center space-x-1.5">
                                <a href="<?= base_url('projects/viewProject/' . $proj['id']) ?>" class="crm-secondary table-action">Lihat prospek</a>
                                <a href="<?= base_url('crm?project_id=' . $proj['id']) ?>" class="primary-action table-action" title="Buka CRM untuk proyek ini">Buka CRM</a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>

</div>
