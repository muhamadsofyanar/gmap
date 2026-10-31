<!-- Manajemen Proyek -->
<div class="space-y-6 project-workspace">
    <section class="page-heading"><div><p class="eyebrow">PROYEK</p><h1>Proyek Prospek</h1><p>Temukan calon pelanggan dan kelola setiap proyek di satu tempat.</p></div><a class="primary-action" href="<?= e(base_url('intake')) ?>">Tambah / impor prospek</a></section>

    <!-- Form Buat Proyek & Scraping Baru -->
    <div id="projectSearch" class="bg-white rounded-lg p-6 border border-gray-200 shadow-sm">
        <div class="flex items-center justify-between pb-4 border-b border-gray-100">
            <div>
                <h2 class="text-base font-semibold text-gray-900 flex items-center space-x-2">
                    <span>Cari Lead Potensial</span>
                </h2>
                <p class="text-xs text-gray-500 mt-0.5">Cari lead potensial berdasarkan kata kunci dan lokasi.</p>
            </div>
        </div>

        <p class="workspace-help">Hasil disaring otomatis: hanya bisnis yang memiliki nomor kontak. Isi jenis usaha calon pelanggan Anda sebagai kata kunci.</p>
        <form action="<?= base_url('projects/create') ?>" method="POST" id="scraperForm" class="mt-5 space-y-4">
            <?= csrf_field() ?>

            <div class="project-search-grid">
                <!-- Nama Proyek -->
                <div class="md:col-span-4">
                    <label for="name" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Nama Proyek
                    </label>
                    <input type="text" id="name" name="name"
                           placeholder="Opsional: Restoran Bekasi"
                           class="w-full px-3.5 py-2.5 text-xs font-medium border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#15D8B3] focus:border-[#15D8B3] outline-none text-gray-900 bg-slate-50/40 focus:bg-white transition-all">
                </div>

                <!-- Katakunci -->
                <div class="md:col-span-3">
                    <label for="keyword" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Kata Kunci Bisnis
                    </label>
                    <input type="text" id="keyword" name="keyword" required
                           placeholder="Contoh: Laundry, Cafe, Bengkel..."
                           class="w-full px-3.5 py-2.5 text-xs font-medium border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#15D8B3] focus:border-[#15D8B3] outline-none text-gray-900 bg-slate-50/40 focus:bg-white transition-all">
                </div>

                <!-- Lokasi -->
                <div class="md:col-span-3">
                    <label for="location" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Area Lokasi
                    </label>
                    <input type="text" id="location" name="location" required
                           placeholder="Contoh: Bekasi, Surabaya, Jakarta..."
                           class="w-full px-3.5 py-2.5 text-xs font-medium border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#15D8B3] focus:border-[#15D8B3] outline-none text-gray-900 bg-slate-50/40 focus:bg-white transition-all">
                </div>

                <!-- Limit -->
                <div class="md:col-span-2">
                    <label for="limit" class="block text-xs font-bold text-gray-700 uppercase tracking-wider mb-1.5">
                        Maksimal Hasil
                    </label>
                    <select id="limit" name="limit" 
                            class="w-full py-2.5 px-3 text-xs font-medium border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#15D8B3] focus:border-[#15D8B3] outline-none text-gray-900 bg-slate-50/40 focus:bg-white transition-all">
                        <option value="10">10 Lead Potensial</option>
                        <option value="20" selected>20 Lead Potensial</option>
                        <option value="50">50 Lead Potensial</option>
                        <option value="100">100 Lead Potensial</option>
                    </select>
                </div>
            </div>

            <div class="flex items-center justify-end pt-2">
                <button type="submit" id="searchSubmitBtn"
                        class="btn-brand py-2.5 px-6 rounded-lg text-xs font-semibold flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/>
                    </svg>
                    <span>Cari Lead Potensial</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Daftar Proyek -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        <div class="p-5 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="text-sm font-semibold text-gray-900">Daftar Proyek</h3>
                <p class="text-xs text-gray-500 mt-0.5">Kelola data setiap proyek dan pantau perkembangan prospeknya.</p>
            </div>
            <span class="text-xs font-semibold px-3 py-1 rounded-lg bg-slate-100 text-slate-600 border border-slate-200 whitespace-nowrap flex-shrink-0">
                <?= count($projects) ?> Proyek Aktif
            </span>
        </div>

        <?php if (empty($projects)): ?>
        <div class="py-16 text-center">
            <div class="max-w-sm mx-auto text-center space-y-3">
                <div class="w-14 h-14 mx-auto rounded-lg bg-slate-100 flex items-center justify-center text-gray-400">
                    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"/>
                    </svg>
                </div>
                <h4 class="font-bold text-gray-800 text-sm">Belum Ada Proyek</h4>
                <p class="text-xs text-gray-500">Gunakan form di atas untuk memulai scraping pertama Anda (contoh: Kata Kunci "Laundry", Lokasi "Bekasi"). Sistem akan otomatis membuatkan proyek.</p>
            </div>
        </div>
        <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($projects as $proj): ?>
            <div class="p-5 hover:bg-slate-50/80 transition-colors flex flex-col md:flex-row md:items-center justify-between gap-4">
                
                <!-- Project Info -->
                <div class="space-y-1.5 flex-1 project-card-info">
                    <div class="flex items-center space-x-2.5 project-card-title">
                        <a href="<?= base_url('projects/viewProject/' . $proj['id']) ?>" 
                           class="font-semibold text-sm text-gray-900 hover:text-[#0f947e] transition-colors flex items-center space-x-1.5">
                            <span><?= e($proj['name']) ?></span>
                            <svg class="w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </a>
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-lg bg-slate-100 text-slate-600 border border-slate-200">
                            <?= e($proj['keyword']) ?> &bull; <?= e($proj['location']) ?>
                        </span>
                    </div>

                    <!-- Data metrics chips -->
                    <div class="flex flex-wrap items-center gap-2 text-xs">
                        <span class="font-semibold text-gray-700 flex items-center space-x-1">
                            <span class="w-2 h-2 rounded-lg bg-emerald-500"></span>
                            <span><strong><?= (int)$proj['count_total'] ?></strong> Prospek</span>
                        </span>
                        <span class="text-gray-300">&bull;</span>
                        <span class="text-gray-400">
                            Dibuat: <?= date('d M Y, H:i', strtotime($proj['created_at'])) ?>
                        </span>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="project-card-actions flex items-center space-x-2 self-start md:self-auto flex-shrink-0">
                    <a href="<?= base_url('projects/viewProject/' . $proj['id']) ?>"
                       class="text-xs font-bold px-4 py-2 rounded-lg bg-[#15D8B3] hover:bg-[#12BA9B] text-slate-900 shadow-sm transition-all flex items-center space-x-1.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                        </svg>
                        <span>Buka Proyek</span>
                    </a>

                    <a href="<?= base_url('projects/export/' . $proj['id']) ?>"
                       class="text-xs font-semibold px-3 py-2 rounded-lg bg-slate-100 hover:bg-slate-200 text-gray-700 border border-gray-200 transition-all flex items-center space-x-1"
                       title="Export CSV / Excel proyek ini">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span class="hidden sm:inline">Export</span>
                    </a>

                    <!-- Delete Project Button -->
                    <button type="button" 
                            data-click="deleteProject" data-click-args="<?= e(json_encode([(int)$proj['id'], $proj['name']])) ?>"
                            class="p-2 rounded-lg text-gray-400 hover:text-rose-600 hover:bg-rose-50 border border-transparent hover:border-rose-200 transition-colors"
                            title="Hapus proyek ini">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>

</div>

<!-- Custom Confirmation Modal for Deleting Project -->
<div id="deleteConfirmModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6 shadow-sm border border-gray-100 transform transition-all animate-scale-up">
        <div class="flex items-center space-x-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 text-base" id="delModalTitle">Konfirmasi Hapus Proyek</h3>
                <p class="text-xs text-gray-500 mt-0.5">Penghapusan permanen tidak dapat dibatalkan.</p>
            </div>
        </div>

        <div class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 text-xs text-gray-700 mb-5 leading-relaxed font-medium" id="delModalMessage">
            Apakah Anda yakin ingin menghapus proyek ini?
        </div>

        <div class="flex items-center justify-end space-x-2.5">
            <button type="button" data-click="closeDeleteModal" class="px-4 py-2 rounded-lg text-xs font-bold text-gray-600 hover:bg-slate-100 transition-colors">
                Batal
            </button>
            <button type="button" id="confirmDeleteActionBtn" class="px-4 py-2 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-sm transition-colors flex items-center space-x-1.5">
                <span>Ya, Hapus Sekarang</span>
            </button>
        </div>
    </div>
</div>

<script nonce="<?= e(csp_nonce()) ?>">
let onConfirmDeleteCallback = null;

function closeDeleteModal() {
    document.getElementById('deleteConfirmModal').classList.add('hidden');
    onConfirmDeleteCallback = null;
}

document.getElementById('confirmDeleteActionBtn')?.addEventListener('click', function() {
    if (typeof onConfirmDeleteCallback === 'function') {
        const cb = onConfirmDeleteCallback;
        cb();
    }
});

function deleteProject(projectId, projectName) {
    document.getElementById('delModalMessage').innerHTML = `Apakah Anda yakin ingin menghapus proyek <strong>"${escapeHtml(projectName)}"</strong>?<br><br>Seluruh data hasil scraping di dalam proyek ini akan dihapus permanen dari database.`;
    onConfirmDeleteCallback = function() {
        const btn = document.getElementById('confirmDeleteActionBtn');
        btn.disabled = true;
        btn.innerHTML = '<span>Menghapus Proyek...</span>';

        const fd = new FormData();
        fd.append('id', projectId);
        fd.append('_csrf_token', '<?= csrf_token() ?>');

        fetch('<?= base_url('projects/delete') ?>', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(res => res.json())
        .then(data => {
            window.location.reload();
        })
        .catch(() => {
            // Fallback submit
            const f = document.createElement('form');
            f.method = 'POST';
            f.action = '<?= base_url('projects/delete') ?>';
            f.innerHTML = `
                <input type="hidden" name="id" value="${projectId}">
                <input type="hidden" name="_csrf_token" value="<?= csrf_token() ?>">
            `;
            document.body.appendChild(f);
            f.submit();
        });
    };
    document.getElementById('deleteConfirmModal').classList.remove('hidden');
}
</script>
