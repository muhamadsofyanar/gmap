<!-- Dedicated Project Workspace -->
<div class="space-y-6 project-detail-workspace">

    <!-- Top Breadcrumb & Project Header -->
    <div class="bg-white p-6 rounded-lg border border-gray-200 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
            <div class="flex items-center space-x-2 text-xs font-semibold text-gray-500 mb-1">
                <a href="<?= base_url('projects') ?>" class="hover:text-gray-900 flex items-center space-x-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                    <span>Daftar Proyek</span>
                </a>
                <span>/</span>
                <span class="text-gray-900 font-bold">Workspace Proyek</span>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-xl font-semibold text-gray-900"><?= e($project['name']) ?></h1>
            </div>
            <p class="text-xs text-gray-400 mt-1">Dibuat pada <?= date('d F Y, H:i', strtotime($project['created_at'])) ?></p>
        </div>

        <!-- Project Actions -->
        <div class="project-card-actions flex items-center space-x-2">
            <a href="<?= base_url('projects/export/' . $project['id']) ?>" 
               class="btn-brand py-2 px-4 rounded-lg text-xs font-semibold flex items-center space-x-1.5 shadow-sm">
                <svg class="w-4 h-4 text-slate-900" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                </svg>
                <span>Export Excel / CSV</span>
            </a>

            <!-- Delete Project Button (Custom Modal) -->
            <button type="button" 
                    data-click="deleteProject" data-click-args="<?= e(json_encode([(int)$project['id'], $project['name']])) ?>"
                    class="p-2 rounded-lg text-rose-600 hover:bg-rose-50 border border-rose-200 text-xs font-bold transition-colors" 
                    title="Hapus Proyek">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </button>
        </div>
    </div>

    <?php $duplicateGroups = array_filter($duplicates ?? []); if ($duplicateGroups): ?>
    <details class="crm-panel crm-detail-card"><summary>Prospek juga ditemukan di proyek lain (<?= count($duplicateGroups) ?> bisnis)</summary><p class="crm-footnote">Tinjau riwayat sebelum menghubungi ulang. Kecocokan nomor kontak perlu dikonfirmasi; data tidak digabung otomatis. Ditampilkan maksimal 10 kecocokan per prospek.</p>
        <?php foreach ($duplicateGroups as $sourceId=>$matches): ?><article class="sales-item"><a class="crm-text-button" href="#leadRow-<?= (int)$sourceId ?>">Lihat prospek pada tabel</a><?php foreach ($matches as $match): ?><p><a class="crm-text-button" href="<?= e(base_url((int)$match['in_crm'] ? 'crm/detail?id='.(int)$match['id'] : 'projects/viewProject/'.(int)$match['project_id'].'#leadRow-'.(int)$match['id'])) ?>"><?= e($match['shop_name'].' / '.$match['project_name']) ?></a> &middot; <?= e($match['reason']) ?><small class="sales-small">Status: <?= e(\App\Models\CrmModel::STAGES[$match['lead_status']]['label']??$match['lead_status']) ?> | Aktivitas terakhir: <?= e($match['last_activity']??'Belum tercatat') ?></small></p><?php endforeach; ?></article><?php endforeach; ?>
    </details>
    <?php endif; ?>

    <!-- Leads Data Table Card -->
    <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
        
        <!-- Toolbar: Search, Website Filter Tabs & Counters -->
        <?php
            $totalLeadsCount = count($leads);
            $noWebCount = 0;
            foreach ($leads as $l) {
                if (empty($l['website'])) $noWebCount++;
            }
            $hasWebCount = $totalLeadsCount - $noWebCount;
            $waCounts = ['active' => 0, 'inactive' => 0, 'unchecked' => 0];
            foreach ($leads as $l) {
                $ws = $l['wa_status'] ?? '';
                $waCounts[in_array($ws, ['active', 'inactive'], true) ? $ws : 'unchecked']++;
            }
        ?>
        <div class="project-table-toolbar border-b border-gray-200 bg-slate-50/50">
            <!-- Baris 1: judul & aksi data -->
            <div class="project-toolbar-head">
                <div class="min-w-0">
                    <h3 class="text-sm font-semibold text-gray-900">Daftar prospek</h3>
                    <p class="text-[11px] text-gray-500 mt-0.5">
                        <span id="visibleLeadCount"><?= $totalLeadsCount ?></span> dari <?= $totalLeadsCount ?> prospek ditampilkan
                        &middot; <span id="waActiveSummary"><?= $waCounts['active'] ?></span> WA aktif
                    </p>
                </div>
                <div class="project-toolbar-actions">
                    <!-- Tombol Sync Rating Realtime -->
                    <button type="button" id="syncRatingBtn" data-click="syncProjectRatingRealtime"
                            class="project-toolbar-btn bg-white hover:bg-amber-50 text-amber-800 border-amber-200"
                            title="Tarik data rating & ulasan Google Maps terbaru secara realtime">
                        <svg id="syncRatingIcon" class="w-3.5 h-3.5 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                        </svg>
                        <span id="syncRatingBtnText">Perbarui rating</span>
                    </button>

                    <!-- Cek WhatsApp aktif: hanya prospek yang dicentang -->
                    <button type="button" id="checkWaBtn" data-click="checkWhatsappStatus"
                            class="project-toolbar-btn bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600"
                            title="Cek apakah nomor prospek yang dicentang terdaftar di WhatsApp">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M17.47 14.38c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.48-.89-.79-1.49-1.77-1.66-2.07-.17-.3-.02-.46.13-.61.13-.13.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.08-.15-.67-1.61-.92-2.21-.24-.58-.49-.5-.67-.51h-.57c-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.48s1.07 2.88 1.21 3.08c.15.2 2.1 3.2 5.08 4.49.71.31 1.26.49 1.69.63.71.23 1.36.2 1.87.12.57-.09 1.76-.72 2.01-1.41.25-.7.25-1.29.17-1.41-.07-.13-.27-.2-.57-.35zM12.04 21.5h-.01a9.4 9.4 0 01-4.8-1.31l-.34-.2-3.57.94.95-3.48-.22-.36a9.43 9.43 0 01-1.45-5.03c0-5.21 4.24-9.45 9.45-9.45a9.39 9.39 0 016.68 2.77 9.39 9.39 0 012.76 6.69c0 5.21-4.24 9.44-9.45 9.44zm8.04-17.48A11.3 11.3 0 0012.04.7C5.77.7.67 5.8.67 12.06c0 2 .52 3.96 1.52 5.68L.57 23.3l5.7-1.5a11.33 11.33 0 005.43 1.38h.01c6.26 0 11.36-5.1 11.36-11.36 0-3.04-1.18-5.89-3.33-8.04z"/></svg>
                        <span id="checkWaBtnText">Cek WhatsApp</span>
                    </button>
                </div>
            </div>

            <!-- Baris 2: filter, urutan & pencarian -->
            <div class="project-toolbar-filters">
                <select id="websiteFilter" aria-label="Filter status website" data-change="filterByWebsite" data-change-value class="project-toolbar-control">
                    <option value="all">Semua website (<?= $totalLeadsCount ?>)</option>
                    <option value="no_web">Web belum terdeteksi (<?= $noWebCount ?>)</option>
                    <option value="has_web">Ada website (<?= $hasWebCount ?>)</option>
                </select>
                <select id="waFilter" aria-label="Filter status WhatsApp" data-change="filterByWhatsapp" data-change-value class="project-toolbar-control">
                    <option value="all">Semua status WA</option>
                    <option value="active" data-label="WA aktif">WA aktif (<?= $waCounts['active'] ?>)</option>
                    <option value="inactive" data-label="WA tidak aktif">WA tidak aktif (<?= $waCounts['inactive'] ?>)</option>
                    <option value="unchecked" data-label="WA belum dicek">WA belum dicek (<?= $waCounts['unchecked'] ?>)</option>
                </select>
                <select id="tableSortSelect" aria-label="Urutkan prospek" data-change="applySortFromSelect" data-change-value class="project-toolbar-control">
                    <option value="rating_desc" selected>Rating tertinggi</option>
                    <option value="rating_asc">Rating terendah</option>
                    <option value="reviews_desc">Ulasan terbanyak</option>
                    <option value="reviews_asc">Ulasan tersedikit</option>
                    <option value="name_asc">Nama (A - Z)</option>
                    <option value="name_desc">Nama (Z - A)</option>
                    <option value="default">Urutan default</option>
                </select>

                <!-- Instant Search Box -->
                <div class="project-toolbar-search relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-gray-400">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                    </div>
                    <input type="text" id="tableQuickSearch" aria-label="Cari dalam daftar prospek"
                           placeholder="Cari nama, alamat, no hp..."
                           class="project-toolbar-control w-full pl-9">
                </div>
            </div>

            <!-- Baris 3: aksi untuk prospek terpilih (muncul saat ada checklist) -->
            <div id="bulkActionBar" class="project-bulk-bar" hidden>
                <span class="text-xs font-semibold text-emerald-900"><span id="selectedCountBar">0</span> prospek dipilih</span>
                <div class="project-toolbar-actions">
                    <button type="button" id="bulkExportBtn" class="project-toolbar-btn bg-white hover:bg-slate-100 text-slate-800 border-slate-300">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        <span>Export (<span id="selectedCount">0</span>)</span>
                    </button>
                    <button type="button" id="bulkMoveToCrmBtn" data-click="moveSelectedToCrm"
                            class="project-toolbar-btn bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                        </svg>
                        <span>Pindahkan ke CRM (<span id="selectedCountCrm">0</span>)</span>
                    </button>
                    <button type="button" data-click="clearProspectSelection" class="text-xs font-semibold text-gray-500 hover:text-gray-800 px-2 py-1.5">Batal pilih</button>
                </div>
            </div>
        </div>

        <!-- Leads Table -->
        <div class="overflow-x-auto custom-scrollbar">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="bg-slate-50/80 text-gray-600 font-bold uppercase tracking-wider text-[11px] border-b border-gray-200">
                        <th class="py-3 px-4 w-10 text-center">
                            <input type="checkbox" id="selectAllProspects" aria-label="Pilih semua prospek yang ditampilkan" class="w-4 h-4 text-[#15D8B3] rounded border-gray-300 focus:ring-[#15D8B3]">
                        </th>
                        <th class="py-3 px-4 min-w-[175px] cursor-pointer select-none group hover:bg-slate-100 transition-colors" data-click="toggleSortHeader" data-click-args="[&quot;name&quot;]" title="Klik untuk mengurutkan Nama Toko">
                            <div class="flex items-center space-x-1.5">
                                <span>Nama Bisnis</span>
                                <span id="sortHeaderIcon-name" class="text-gray-400 group-hover:text-gray-700">
                                    <svg class="w-3 h-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="prospect-phone-cell py-3 px-4">No. Telepon / WA</th>
                        <th class="py-3 px-4 min-w-[100px] cursor-pointer select-none group hover:bg-slate-100 transition-colors" data-click="toggleSortHeader" data-click-args="[&quot;rating&quot;]" title="Klik untuk mengurutkan Rating (Tertinggi / Terendah)">
                            <div class="flex items-center space-x-1.5">
                                <span class="text-gray-900 font-semibold">Rating</span>
                                <span id="sortHeaderIcon-rating" class="text-amber-500 font-bold">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                </span>
                            </div>
                        </th>
                        <th class="prospect-website-cell py-3 px-4">Website</th>
                        <th class="py-3 px-4 min-w-[150px] whitespace-nowrap">Google Maps</th>
                        <th class="py-3 px-4 w-16 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody id="prospectTableBody" class="divide-y divide-gray-200/80 bg-white">
                    <?php if (empty($leads)): ?>
                    <tr id="emptyRow">
                        <td colspan="7" class="py-12 text-center text-gray-500">
                            Tidak ada data prospek dalam proyek ini.
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($leads as $idx => $p): ?>
                        <tr class="prospect-row hover:bg-slate-50/70 transition-colors duration-100 " 
                            id="leadRow-<?= $p['id'] ?>"
                            data-id="<?= $p['id'] ?>"
                            data-rating="<?= (float)($p['rating'] ?? 0) ?>"
                            data-reviews="<?= (int)($p['reviews_count'] ?? 0) ?>"
                            data-name="<?= htmlspecialchars(strtolower($p['shop_name'] ?? '')) ?>"
                            data-address="<?= e(strtolower($p['address'] ?? '')) ?>"
                            data-has-website="<?= empty($p['website']) ? '0' : '1' ?>"
                            data-has-phone="<?= empty($p['phone']) && empty($p['whatsapp']) ? '0' : '1' ?>"
                            data-wa-status="<?= e($p['wa_status'] ?? '') ?>">
                            <!-- Checkbox -->
                            <td class="py-3 px-4 text-center">
                                <input type="checkbox" value="<?= e($p['id']) ?>" aria-label="Pilih <?= e($p['shop_name']) ?>" class="prospect-checkbox w-4 h-4 text-[#15D8B3] rounded border-gray-300 focus:ring-[#15D8B3]">
                            </td>

                            <!-- Nama Toko -->
                            <td class="py-3 px-4">
                                <div class="font-semibold text-gray-900 text-xs leading-snug" title="<?= e($p['address'] ?? '') ?>">
                                    <?= e($p['shop_name']) ?>
                                </div>
                            </td>

                            <!-- No HP / Telepon -->
                            <td class="prospect-phone-cell py-3 px-4">
                                <?php if (!empty($p['phone'])): ?>
                                <div class="prospect-phone flex items-center space-x-2">
                                    <div class="prospect-phone-number font-mono font-semibold text-gray-900 text-xs">
                                        <?= e(format_phone_display($p['phone'])) ?>
                                    </div>
                                    <span id="waBadge-<?= $p['id'] ?>" class="wa-status-badge"></span>
                                </div>
                                <?php else: ?>
                                <span class="text-gray-400 text-xs">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Rating -->
                            <td class="py-3 px-4" id="ratingCell-<?= $p['id'] ?>">
                                <div class="flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5 fill-current <?= (float)$p['rating'] > 0 ? 'text-amber-500' : 'text-gray-300' ?>" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    <span class="font-semibold text-gray-900 rating-value"><?= number_format((float)$p['rating'], 1) ?></span>
                                    <span class="text-gray-400 text-[10px] reviews-value">(<?= (int)$p['reviews_count'] ?>)</span>
                                </div>
                            </td>

                            <!-- Website URL / Status -->
                            <td class="prospect-website-cell py-3 px-4">
                                <?php if (!empty($p['website'])): ?>
                                <a href="<?= e($p['website']) ?>" target="_blank" rel="noopener noreferrer" 
                                   class="text-cyan-700 hover:text-cyan-900 font-semibold text-[11px] inline-flex items-center space-x-1 hover:underline group">
                                    <span class="truncate max-w-[120px]" title="<?= e($p['website']) ?>">
                                        <?= e(parse_url($p['website'], PHP_URL_HOST) ?? $p['website']) ?>
                                    </span>
                                    <svg class="w-3 h-3 flex-shrink-0 text-cyan-500 group-hover:text-cyan-700 transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                    </svg>
                                </a>
                                <?php else: ?>
                                <span class="prospect-website-status inline-flex items-center space-x-1 px-2 py-0.5 rounded-lg bg-rose-100 text-rose-800 text-[10px] font-semibold border border-rose-300">
                                    <span class="w-1.5 h-1.5 rounded-lg bg-rose-600 "></span>
                                    <span>Web belum terdeteksi</span>
                                </span>
                                <?php endif; ?>
                            </td>

                            <!-- Google Maps Link -->
                            <td class="py-3 px-4 whitespace-nowrap">
                                <?php if (!empty($p['gmap_url'])): ?>
                                <a href="<?= e($p['gmap_url']) ?>" target="_blank" rel="noopener noreferrer"
                                   class="inline-flex items-center space-x-1.5 text-[11px] font-bold text-gray-700 hover:text-gray-900 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 transition-colors whitespace-nowrap">
                                    <svg class="w-3.5 h-3.5 text-red-500 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/>
                                    </svg>
                                    <span class="whitespace-nowrap">Buka Maps</span>
                                </a>
                                <?php else: ?>
                                <span class="text-gray-400">-</span>
                                <?php endif; ?>
                            </td>

                            <!-- Aksi -->
                            <td class="py-3 px-4 text-center whitespace-nowrap">
                                <div class="flex items-center justify-center space-x-1">
                                    <span id="crmSlot-<?= $p['id'] ?>" class="inline-flex items-center justify-center">
                                        <?php if (!empty($p['in_crm'])): ?>
                                        <span class="lead-crm-done" title="Prospek ini sudah ada di CRM">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                            </svg>
                                            Di CRM
                                        </span>
                                        <?php else: ?>
                                        <button type="button" id="singleCrmBtn-<?= $p['id'] ?>"
                                                data-click="moveSingleToCrm" data-click-args="<?= e(json_encode([(int)$p['id'], $p['shop_name']])) ?>"
                                                class="lead-crm-add"
                                                title="Pindahkan prospek ini ke CRM">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4"/>
                                            </svg>
                                            CRM
                                        </button>
                                        <?php endif; ?>
                                    </span>
                                    <button type="button" 
                                            data-click="deleteLead" data-click-args="<?= e(json_encode([(int)$p['id'], $p['shop_name']])) ?>"
                                            class="lead-delete"
                                            title="Hapus prospek dari proyek" aria-label="Hapus <?= e($p['shop_name']) ?> dari proyek">
                                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

    </div>

</div>

<!-- Custom Styled Confirmation Modal (Kebal dari blokir browser dialog) -->
<div id="deleteConfirmModal" class="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm hidden flex items-center justify-center p-4">
    <div class="bg-white rounded-lg max-w-md w-full p-6 shadow-sm border border-gray-100 transform transition-all animate-scale-up">
        <div class="flex items-center space-x-3 mb-3">
            <div class="w-10 h-10 rounded-lg bg-rose-100 text-rose-600 flex items-center justify-center flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
            </div>
            <div>
                <h3 class="font-semibold text-gray-900 text-base" id="delModalTitle">Konfirmasi Hapus</h3>
                <p class="text-xs text-gray-500 mt-0.5" id="delModalSubtitle">Tindakan ini permanen.</p>
            </div>
        </div>

        <div class="p-3.5 bg-slate-50 rounded-lg border border-slate-200 text-xs text-gray-700 mb-5 leading-relaxed font-medium" id="delModalMessage">
            Apakah Anda yakin ingin menghapus data ini?
        </div>

        <div class="flex items-center justify-end space-x-2.5">
            <button type="button" data-click="closeDeleteModal" class="px-4 py-2 rounded-lg text-xs font-bold text-gray-600 hover:bg-slate-100 transition-colors">
                Batal
            </button>
            <button type="button" id="confirmDeleteActionBtn" class="px-4 py-2 rounded-lg text-xs font-bold bg-rose-600 hover:bg-rose-700 text-white shadow-sm transition-colors flex items-center space-x-1.5">
                <span id="delBtnText">Ya, Hapus Sekarang</span>
            </button>
        </div>
    </div>
</div>

<!-- Interactive Scripts: Instant Search, Copy Toast, Bulk Selection, Custom Delete Actions -->
<script nonce="<?= e(csp_nonce()) ?>">
// 1. Instant Client-Side Table Filter & Website Status Tab Filter
let currentWebsiteFilter = 'all';
let currentWaFilter = 'all';
let currentSortKey = 'rating';
let currentSortDir = 'desc'; // 'desc', 'asc', 'default'

function applyTableFilters() {
    const term = (document.getElementById('tableQuickSearch')?.value || '').toLowerCase().trim();
    const rows = document.querySelectorAll('#prospectTableBody tr.prospect-row');
    let visible = 0;

    rows.forEach(row => {
        // Address column is hidden; keep it searchable through the row attribute.
        const text = (row.innerText + ' ' + (row.getAttribute('data-address') || '')).toLowerCase();
        const hasWeb = row.getAttribute('data-has-website'); // '1' or '0'
        const waStatus = row.getAttribute('data-wa-status') || '';

        let matchFilter = true;
        if (currentWebsiteFilter === 'no_web' && hasWeb !== '0') {
            matchFilter = false;
        } else if (currentWebsiteFilter === 'has_web' && hasWeb !== '1') {
            matchFilter = false;
        }
        if (currentWaFilter === 'active' && waStatus !== 'active') {
            matchFilter = false;
        } else if (currentWaFilter === 'inactive' && waStatus !== 'inactive') {
            matchFilter = false;
        } else if (currentWaFilter === 'unchecked' && (waStatus === 'active' || waStatus === 'inactive')) {
            matchFilter = false;
        }

        const matchSearch = !term || text.includes(term);

        if (matchFilter && matchSearch) {
            row.style.display = '';
            visible++;
        } else {
            row.style.display = 'none';
            const checkbox = row.querySelector('.prospect-checkbox');
            if (checkbox) checkbox.checked = false;
        }
    });

    let emptyRow = document.getElementById('noResultsFilteredRow');
    const tbody = document.getElementById('prospectTableBody');
    if (visible === 0 && tbody) {
        if (!emptyRow) {
            emptyRow = document.createElement('tr');
            emptyRow.id = 'noResultsFilteredRow';
            emptyRow.innerHTML = '<td colspan="7" class="py-12 text-center text-gray-400 text-xs font-medium">Tidak ada data prospek yang sesuai kriteria filter.</td>';
            tbody.appendChild(emptyRow);
        }
    } else if (emptyRow) {
        emptyRow.remove();
    }
    const visibleCounter = document.getElementById('visibleLeadCount');
    if (visibleCounter) visibleCounter.innerText = visible;
    updateSelectionState();
}

function filterByWebsite(type) {
    currentWebsiteFilter = type;
    document.getElementById('websiteFilter').value = type;
    applyTableFilters();
}

// 2. Interactive Column Sorting (Rating, Reviews, Name, Default)
function sortTableRows(sortKey, sortDir) {
    currentSortKey = sortKey;
    currentSortDir = sortDir;

    const tbody = document.getElementById('prospectTableBody');
    if (!tbody) return;

    const rows = Array.from(tbody.querySelectorAll('tr.prospect-row'));
    if (rows.length === 0) return;

    rows.sort((a, b) => {
        if (sortKey === 'rating') {
            const rA = parseFloat(a.getAttribute('data-rating') || 0);
            const rB = parseFloat(b.getAttribute('data-rating') || 0);
            const vA = parseInt(a.getAttribute('data-reviews') || 0);
            const vB = parseInt(b.getAttribute('data-reviews') || 0);

            if (sortDir === 'asc') {
                if (rA !== rB) return rA - rB;
                return vA - vB;
            } else {
                if (rA !== rB) return rB - rA;
                return vB - vA;
            }
        } else if (sortKey === 'reviews') {
            const vA = parseInt(a.getAttribute('data-reviews') || 0);
            const vB = parseInt(b.getAttribute('data-reviews') || 0);
            const rA = parseFloat(a.getAttribute('data-rating') || 0);
            const rB = parseFloat(b.getAttribute('data-rating') || 0);

            if (sortDir === 'asc') {
                if (vA !== vB) return vA - vB;
                return rA - rB;
            } else {
                if (vA !== vB) return vB - vA;
                return rB - rA;
            }
        } else if (sortKey === 'name') {
            const nA = (a.getAttribute('data-name') || '').toLowerCase();
            const nB = (b.getAttribute('data-name') || '').toLowerCase();
            return sortDir === 'asc' ? nA.localeCompare(nB) : nB.localeCompare(nA);
        } else {
            // default ID order
            const idA = parseInt(a.getAttribute('data-id') || 0);
            const idB = parseInt(b.getAttribute('data-id') || 0);
            return idA - idB;
        }
    });

    // Re-append rows in sorted order smoothly
    rows.forEach(r => tbody.appendChild(r));

    updateSortIcons();
    applyTableFilters();
}

function updateSortIcons() {
    const iconRating = document.getElementById('sortHeaderIcon-rating');
    const iconName = document.getElementById('sortHeaderIcon-name');
    const select = document.getElementById('tableSortSelect');

    if (iconRating) {
        if (currentSortKey === 'rating') {
            iconRating.className = 'text-amber-500 font-bold';
            iconRating.innerHTML = currentSortDir === 'asc'
                ? '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>'
                : '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>';
        } else {
            iconRating.className = 'text-gray-400 group-hover:text-gray-700';
            iconRating.innerHTML = '<svg class="w-3 h-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>';
        }
    }

    if (iconName) {
        if (currentSortKey === 'name') {
            iconName.className = 'text-[#15D8B3] font-bold';
            iconName.innerHTML = currentSortDir === 'asc'
                ? '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"/></svg>'
                : '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>';
        } else {
            iconName.className = 'text-gray-400 group-hover:text-gray-700';
            iconName.innerHTML = '<svg class="w-3 h-3 opacity-40" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/></svg>';
        }
    }

    if (select) {
        if (currentSortKey === 'rating' && currentSortDir === 'desc') select.value = 'rating_desc';
        else if (currentSortKey === 'rating' && currentSortDir === 'asc') select.value = 'rating_asc';
        else if (currentSortKey === 'reviews' && currentSortDir === 'desc') select.value = 'reviews_desc';
        else if (currentSortKey === 'reviews' && currentSortDir === 'asc') select.value = 'reviews_asc';
        else if (currentSortKey === 'name' && currentSortDir === 'asc') select.value = 'name_asc';
        else if (currentSortKey === 'name' && currentSortDir === 'desc') select.value = 'name_desc';
        else if (currentSortKey === 'default') select.value = 'default';
    }
}

function toggleSortHeader(col) {
    if (currentSortKey === col) {
        const nextDir = currentSortDir === 'desc' ? 'asc' : (currentSortDir === 'asc' ? 'default' : 'desc');
        if (nextDir === 'default') {
            sortTableRows('default', 'asc');
        } else {
            sortTableRows(col, nextDir);
        }
    } else {
        sortTableRows(col, 'desc');
    }
}

function applySortFromSelect(val) {
    switch(val) {
        case 'rating_desc': sortTableRows('rating', 'desc'); break;
        case 'rating_asc': sortTableRows('rating', 'asc'); break;
        case 'reviews_desc': sortTableRows('reviews', 'desc'); break;
        case 'reviews_asc': sortTableRows('reviews', 'asc'); break;
        case 'name_asc': sortTableRows('name', 'asc'); break;
        case 'name_desc': sortTableRows('name', 'desc'); break;
        default: sortTableRows('default', 'asc'); break;
    }
}

// 3. Sync Realtime Ratings & Reviews from Google Maps
async function syncProjectRatingRealtime() {
    const btn = document.getElementById('syncRatingBtn');
    const icon = document.getElementById('syncRatingIcon');
    const text = document.getElementById('syncRatingBtnText');

    if (btn) btn.disabled = true;
    if (icon) icon.classList.add('animate-spin');
    if (text) text.textContent = 'Memperbarui...';

    const fd = new FormData();
    fd.append('project_id', <?= (int)$project['id'] ?>);
    fd.append('_csrf_token', '<?= csrf_token() ?>');

    try {
        const res = await fetch('<?= base_url('projects/syncRatingRealtime') ?>', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            // Update table row ratings in DOM
            if (data.updated_leads && Array.isArray(data.updated_leads)) {
                data.updated_leads.forEach(item => {
                    const row = document.getElementById(`leadRow-${item.id}`);
                    if (row) {
                        row.setAttribute('data-rating', item.rating);
                        row.setAttribute('data-reviews', item.reviews_count);

                        const cell = document.getElementById(`ratingCell-${item.id}`);
                        if (cell) {
                            const starColor = item.rating > 0 ? 'text-amber-500' : 'text-gray-300';
                            cell.innerHTML = `
                                <div class="flex items-center space-x-1">
                                    <svg class="w-3.5 h-3.5 fill-current ${starColor}" viewBox="0 0 20 20">
                                        <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                    </svg>
                                    <span class="font-semibold text-gray-900 rating-value">${item.rating_display}</span>
                                    <span class="text-gray-400 text-[10px] reviews-value">(${item.reviews_count})</span>
                                </div>
                            `;
                        }
                    }
                });
            }

            // Re-sort table with currently active sort
            sortTableRows(currentSortKey, currentSortDir);
            showFloatingToast(data.message || 'Rating berhasil diperbarui secara realtime!');
        } else {
            showFloatingToast(data.message || 'Gagal menyinkronkan rating.', true);
        }
    } catch (err) {
        console.error(err);
        showFloatingToast('Terjadi kesalahan jaringan saat menyinkronkan rating.', true);
    } finally {
        if (btn) btn.disabled = false;
        if (icon) icon.classList.remove('animate-spin');
        if (text) text.textContent = 'Perbarui rating';
    }
}

// 4. WhatsApp active check (via the configured WhatsApp gateway)
// Status icon shown right next to the phone number: [color classes, svg inner markup, tooltip]
const WA_BADGES = {
    active: ['text-emerald-600', '<circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M7.5 12.5l3 3 6-6.5" fill="none" stroke="#fff" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>', 'WhatsApp aktif'],
    inactive: ['text-rose-600','<circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M9 9l6 6m0-6l-6 6" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>', 'WhatsApp tidak aktif'],
    unknown: ['text-amber-500', '<circle cx="12" cy="12" r="10" fill="currentColor"/><path d="M9.6 9.3a2.5 2.5 0 014.8.9c0 1.7-2.4 2.1-2.4 3.6M12 17h.01" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/>', 'Status WhatsApp belum pasti'],
    checking: ['text-cyan-500 animate-spin', '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="3" stroke-opacity=".25"/><path d="M21 12a9 9 0 00-9-9" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round"/>', 'Mengecek WhatsApp...']
};

function renderWaBadge(leadId, status) {
    const slot = document.getElementById(`waBadge-${leadId}`);
    if (!slot) return;
    const badge = WA_BADGES[status];
    slot.innerHTML = badge
        ? `<svg class="w-4 h-4 ${badge[0]}" viewBox="0 0 24 24" role="img" aria-label="${badge[2]}"><title>${badge[2]}</title>${badge[1]}</svg>`
        : '';
}

function updateWaCounts() {
    const counts = { active: 0, inactive: 0, unchecked: 0 };
    document.querySelectorAll('#prospectTableBody tr.prospect-row').forEach(row => {
        const s = row.getAttribute('data-wa-status') || '';
        counts[s === 'active' || s === 'inactive' ? s : 'unchecked']++;
    });
    document.querySelectorAll('#waFilter option[data-label]').forEach(opt => {
        opt.textContent = `${opt.getAttribute('data-label')} (${counts[opt.value]})`;
    });
    const summary = document.getElementById('waActiveSummary');
    if (summary) summary.innerText = counts.active;
}

function filterByWhatsapp(type) {
    currentWaFilter = type;
    document.getElementById('waFilter').value = type;
    applyTableFilters();
}

let waCheckRunning = false;
// Only the rows the user checked are sent to the gateway.
async function checkWhatsappStatus() {
    if (waCheckRunning) return;
    const checked = selectedVisibleProspects();
    if (checked.length === 0) {
        showFloatingToast('Centang prospek yang ingin dicek WhatsApp-nya terlebih dahulu.', true);
        return;
    }
    const ids = checked
        .filter(cb => cb.closest('.prospect-row')?.getAttribute('data-has-phone') === '1')
        .map(cb => parseInt(cb.value)).filter(id => id > 0);
    if (ids.length === 0) {
        showFloatingToast('Prospek yang dicentang tidak memiliki nomor telepon.', true);
        return;
    }

    waCheckRunning = true;
    const btn = document.getElementById('checkWaBtn');
    const text = document.getElementById('checkWaBtnText');
    if (btn) btn.disabled = true;

    const tally = { active: 0, inactive: 0, unknown: 0 };
    let done = 0;
    let failure = null;
    const batchSize = 5;

    for (let i = 0; i < ids.length && !failure; i += batchSize) {
        const batch = ids.slice(i, i + batchSize);
        batch.forEach(id => renderWaBadge(id, 'checking'));
        if (text) text.textContent = `Mengecek ${done}/${ids.length}...`;

        const fd = new FormData();
        batch.forEach(id => fd.append('ids[]', id));
        fd.append('project_id', <?= (int)$project['id'] ?>);
        fd.append('_csrf_token', '<?= csrf_token() ?>');

        let data = null;
        try {
            const res = await fetch('<?= base_url('projects/checkWhatsapp') ?>', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            data = await res.json();
        } catch (err) {
            console.error(err);
            failure = 'Terjadi gangguan jaringan saat mengecek WhatsApp.';
        }

        const handled = new Set();
        (data?.results || []).forEach(item => {
            handled.add(item.id);
            if (item.status === 'no_phone') return;
            const row = document.getElementById(`leadRow-${item.id}`);
            if (row) row.setAttribute('data-wa-status', item.status);
            renderWaBadge(item.id, item.status);
            if (tally[item.status] !== undefined) tally[item.status]++;
        });
        // Restore badges for leads the server did not get to.
        batch.filter(id => !handled.has(id)).forEach(id => {
            renderWaBadge(id, document.getElementById(`leadRow-${id}`)?.getAttribute('data-wa-status') || '');
        });
        done += handled.size;
        if (data && !data.success) failure = data.message || 'Gagal mengecek status WhatsApp.';
    }

    waCheckRunning = false;
    if (btn) btn.disabled = false;
    if (text) text.textContent = 'Cek WhatsApp';
    updateWaCounts();
    applyTableFilters();

    const summary = `${tally.active} aktif, ${tally.inactive} tidak aktif, ${tally.unknown} belum pasti`;
    if (failure) {
        showFloatingToast(`${failure} (${done}/${ids.length} selesai: ${summary})`, true);
    } else {
        showFloatingToast(`Cek WhatsApp selesai: ${summary}.`);
    }
}

document.querySelectorAll('#prospectTableBody tr.prospect-row').forEach(row => {
    renderWaBadge(row.getAttribute('data-id'), row.getAttribute('data-wa-status') || '');
});

window.filterByWhatsapp = filterByWhatsapp;
window.checkWhatsappStatus = checkWhatsappStatus;
window.filterByWebsite = filterByWebsite;
window.applyTableFilters = applyTableFilters;
window.sortTableRows = sortTableRows;
window.toggleSortHeader = toggleSortHeader;
window.applySortFromSelect = applySortFromSelect;
window.syncProjectRatingRealtime = syncProjectRatingRealtime;

document.getElementById('tableQuickSearch')?.addEventListener('input', applyTableFilters);
updateSortIcons();

// 2. Select All Checkboxes & CRM Action Handlers
const selectAllCheckbox = document.getElementById('selectAllProspects');
const bulkExportBtn = document.getElementById('bulkExportBtn');
const bulkMoveToCrmBtn = document.getElementById('bulkMoveToCrmBtn');
function visibleProspectCheckboxes() {
    return Array.from(document.querySelectorAll('#prospectTableBody .prospect-checkbox'))
        .filter(cb => !cb.disabled && cb.closest('.prospect-row')?.style.display !== 'none');
}

function selectedVisibleProspects() {
    return visibleProspectCheckboxes().filter(cb => cb.checked);
}

function updateSelectionState() {
    const checked = selectedVisibleProspects();
    const count = checked.length;
    const selectedCountSpan = document.getElementById('selectedCount');
    const selectedCountCrmSpan = document.getElementById('selectedCountCrm');
    if (selectedCountSpan) selectedCountSpan.innerText = count;
    if (selectedCountCrmSpan) selectedCountCrmSpan.innerText = count;
    const visibleCount = visibleProspectCheckboxes().length;
    if (selectAllCheckbox) {
        selectAllCheckbox.checked = visibleCount > 0 && count === visibleCount;
        selectAllCheckbox.indeterminate = count > 0 && count < visibleCount;
        selectAllCheckbox.disabled = visibleCount === 0;
    }

    const bar = document.getElementById('bulkActionBar');
    const barCount = document.getElementById('selectedCountBar');
    if (barCount) barCount.innerText = count;
    if (bar) bar.hidden = count === 0;
    const waText = document.getElementById('checkWaBtnText');
    if (waText && !waCheckRunning) waText.textContent = count > 0 ? `Cek WhatsApp (${count})` : 'Cek WhatsApp';
}

function clearProspectSelection() {
    document.querySelectorAll('#prospectTableBody .prospect-checkbox').forEach(cb => cb.checked = false);
    updateSelectionState();
}
window.clearProspectSelection = clearProspectSelection;

if (selectAllCheckbox) {
    selectAllCheckbox.addEventListener('change', function() {
        const checkboxes = visibleProspectCheckboxes();
        checkboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
        updateSelectionState();
    });
}

document.querySelectorAll('.prospect-checkbox').forEach(cb => {
    cb.addEventListener('change', updateSelectionState);
});

updateSelectionState();

// Helper to display checkmark icon when lead is moved to CRM
function markLeadAsInCrm(leadId) {
    const slot = document.getElementById(`crmSlot-${leadId}`);
    if (slot) {
        slot.innerHTML = `
            <span class="lead-crm-done animate-scale-up" title="Prospek ini sudah ada di CRM">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                Di CRM
            </span>
        `;
    }
}

// Move Selected Checked Leads to CRM
async function moveSelectedToCrm() {
    const checked = selectedVisibleProspects();
    const ids = Array.from(checked).map(c => parseInt(c.value)).filter(id => id > 0);
    if (ids.length === 0) {
        showFloatingToast('Pilih setidaknya 1 prospek dengan checklist.', true);
        return;
    }

    const btn = document.getElementById('bulkMoveToCrmBtn');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '<span>Memindahkan ke CRM...</span>';
    }

    const fd = new FormData();
    ids.forEach(id => fd.append('ids[]', id));
    fd.append('project_id', <?= (int)$project['id'] ?>);
    fd.append('_csrf_token', '<?= csrf_token() ?>');

    try {
        const res = await fetch('<?= base_url('projects/moveToCrm') ?>', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            // Update UI status: replace + button with checklist icon
            ids.forEach(id => {
                markLeadAsInCrm(id);
            });

            // Uncheck
            checked.forEach(c => c.checked = false);
            if (selectAllCheckbox) selectAllCheckbox.checked = false;
            updateSelectionState();

            showFloatingToastWithLink(`${data.count} prospek berhasil dipindahkan ke CRM!`, '<?= base_url('crm?project_id=' . (int)$project['id']) ?>', 'Buka CRM');
        } else {
            showFloatingToast(data.message || 'Gagal memindahkan data ke CRM.', true);
        }
    } catch (err) {
        console.error(err);
        showFloatingToast('Terjadi gangguan jaringan saat memindahkan ke CRM.', true);
    } finally {
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = `
                <svg class="w-3.5 h-3.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
                </svg>
                <span>Pindahkan ke CRM (<span id="selectedCountCrm">0</span>)</span>
            `;
            updateSelectionState();
        }
    }
}

// Move Single Lead to CRM
async function moveSingleToCrm(leadId, shopName) {
    const fd = new FormData();
    fd.append('ids[]', leadId);
    fd.append('project_id', <?= (int)$project['id'] ?>);
    fd.append('_csrf_token', '<?= csrf_token() ?>');

    try {
        const res = await fetch('<?= base_url('projects/moveToCrm') ?>', {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await res.json();
        if (data.success) {
            markLeadAsInCrm(leadId);
            showFloatingToastWithLink(`"${shopName}" berhasil dipindahkan ke CRM!`, '<?= base_url('crm?project_id=' . (int)$project['id']) ?>', 'Buka CRM');
        } else {
            showFloatingToast(data.message || 'Gagal memindahkan ke CRM.', true);
        }
    } catch (err) {
        console.error(err);
        showFloatingToast('Terjadi gangguan jaringan saat memindahkan ke CRM.', true);
    }
}

window.moveSelectedToCrm = moveSelectedToCrm;
window.moveSingleToCrm = moveSingleToCrm;

// 3. Bulk Export Action
if (bulkExportBtn) {
    bulkExportBtn.addEventListener('click', function() {
        const checked = selectedVisibleProspects();
        const ids = Array.from(checked).map(c => c.value);
        if (ids.length === 0) return;

        window.location.href = '<?= base_url('projects/export/' . $project['id']) ?>?ids=' + ids.join(',');
    });
}

// 4. Copy to Clipboard with floating toast
function copyToClipboard(text, label = 'Teks') {
    if (!text) return;
    navigator.clipboard.writeText(text).then(() => {
        showFloatingToast(`Berhasil disalin: ${label}`);
    }).catch(err => {
        showFloatingToast(`Gagal menyalin: ${err}`, true);
    });
}

function showFloatingToast(msg, isError = false) {
    const toast = document.createElement('div');
    toast.className = `fixed bottom-5 right-5 z-50 px-4 py-2.5 rounded-lg text-xs font-bold text-white shadow-sm transition-all transform translate-y-2 opacity-0 flex items-center space-x-2 ${isError ? 'bg-rose-600' : 'bg-slate-900'}`;
    toast.innerHTML = `
        <svg class="w-4 h-4 flex-shrink-0 ${isError ? 'text-rose-200' : 'text-[#15D8B3]'}" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="${isError ? 'M6 18L18 6M6 6l12 12' : 'M5 13l4 4L19 7'}"/>
        </svg>
        <span>${escapeHtml(msg)}</span>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    }, 50);

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 2500);
}

function showFloatingToastWithLink(msg, linkUrl, linkText) {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-5 right-5 z-50 px-4 py-3 rounded-lg text-xs font-bold text-white shadow-sm transition-all transform translate-y-2 opacity-0 flex items-center space-x-3 bg-slate-900 border border-slate-700';
    toast.innerHTML = `
        <div class="w-6 h-6 rounded-lg bg-emerald-500/20 text-[#15D8B3] flex items-center justify-center flex-shrink-0">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
            </svg>
        </div>
        <div class="flex items-center space-x-3">
            <span>${escapeHtml(msg)}</span>
            <a href="${escapeHtml(linkUrl)}" class="px-3 py-1 rounded-lg bg-emerald-600 hover:bg-emerald-500 text-white font-semibold text-[11px] transition-colors flex items-center space-x-1 shadow-sm">
                <span>${escapeHtml(linkText)}</span>
                <span>&rarr;</span>
            </a>
        </div>
    `;
    document.body.appendChild(toast);
    
    setTimeout(() => {
        toast.classList.remove('translate-y-2', 'opacity-0');
    }, 50);

    setTimeout(() => {
        toast.classList.add('opacity-0', 'translate-y-2');
        setTimeout(() => toast.remove(), 300);
    }, 4500);
}

// 5. Custom Modal & Delete Handlers
let onConfirmDeleteCallback = null;

function openDeleteModal(title, subtitle, message, callback) {
    document.getElementById('delModalTitle').textContent = title;
    document.getElementById('delModalSubtitle').textContent = subtitle;
    document.getElementById('delModalMessage').innerHTML = message;
    onConfirmDeleteCallback = callback;
    document.getElementById('deleteConfirmModal').classList.remove('hidden');
}

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

// Delete Single Lead from Project
function deleteLead(leadId, shopName) {
    openDeleteModal(
        'Hapus Data Prospek',
        'Menghapus baris data dari proyek',
        `Apakah Anda yakin ingin menghapus data <strong>"${escapeHtml(shopName)}"</strong>? Data ini akan dihapus dari proyek ini.`,
        function() {
            const btn = document.getElementById('confirmDeleteActionBtn');
            btn.disabled = true;
            btn.innerHTML = '<span>Menghapus...</span>';

            const fd = new FormData();
            fd.append('lead_id', leadId);
            fd.append('project_id', <?= (int)$project['id'] ?>);
            fd.append('_csrf_token', '<?= csrf_token() ?>');

            fetch('<?= base_url('projects/deleteLead') ?>', {
                method: 'POST',
                body: fd,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(res => res.json())
            .then(data => {
                closeDeleteModal();
                btn.disabled = false;
                btn.innerHTML = '<span>Ya, Hapus Sekarang</span>';

                if (data.success) {
                    const row = document.getElementById(`leadRow-${leadId}`);
                    if (row) {
                        row.style.transition = 'all 0.3s ease';
                        row.style.opacity = '0';
                        row.style.transform = 'scale(0.95)';
                        setTimeout(() => {
                            row.remove();
                            const remaining = document.querySelectorAll('.prospect-row').length;
                            const counter = document.getElementById('visibleRowCount');
                            if (counter) counter.innerText = remaining;
                            if (remaining === 0) {
                                location.reload();
                            }
                        }, 300);
                    }
                    showFloatingToast('Prospek berhasil dihapus.');
                } else {
                    showFloatingToast(data.message || 'Gagal menghapus data.', true);
                }
            })
            .catch(() => {
                closeDeleteModal();
                btn.disabled = false;
                btn.innerHTML = '<span>Ya, Hapus Sekarang</span>';
                showFloatingToast('Terjadi gangguan jaringan saat menghapus.', true);
            });
        }
    );
}

// Delete Entire Project
function deleteProject(projectId, projectName) {
    openDeleteModal(
        'Hapus Seluruh Proyek',
        'Penghapusan permanen proyek dan seluruh prospek',
        `Apakah Anda yakin ingin menghapus proyek <strong>"${escapeHtml(projectName)}"</strong>?<br><br>Seluruh data hasil scraping di dalam proyek ini akan dihapus bersih secara permanen dari database.`,
        function() {
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
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    window.location.href = '<?= base_url('projects') ?>';
                }
            })
            .catch(() => {
                // Fallback standard submit
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
        }
    );
}
</script>
