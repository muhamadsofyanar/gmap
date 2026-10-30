    </main>

    <footer class="app-footer"><span>Gmap Prospek <span aria-hidden="true">/</span> Workspace bisnis</span><span>&copy; <?= date('Y') ?> Gmap Prospek</span></footer>

    <!-- Global Toast Notification Container -->
    <div id="toastContainer" class="fixed bottom-5 right-5 z-50 flex flex-col space-y-2 pointer-events-none"></div>

    <!-- WhatsApp B2B Pitch Modal -->
    <div id="waModal" role="dialog" aria-modal="true" aria-labelledby="waModalTitle" tabindex="-1" class="fixed inset-0 z-50 bg-slate-900/60 backdrop-blur-sm hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-lg max-w-lg w-full p-6 shadow-sm border border-gray-100 transform transition-all max-h-[92vh] overflow-y-auto">
            <div class="flex items-center justify-between pb-3 border-b border-gray-100">
                <div class="flex items-center space-x-2.5">
                    <div class="w-8 h-8 rounded-lg bg-emerald-100 text-emerald-600 flex items-center justify-center">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.699c.97.53 1.895.82 2.796.82 3.183 0 5.768-2.587 5.768-5.766.001-3.187-2.575-5.806-5.768-5.806zm0 10.455c-.787 0-1.602-.224-2.316-.648l-.166-.098-1.724.452.46-1.68-.108-.172c-.482-.767-.737-1.571-.736-2.503.001-2.474 2.013-4.487 4.49-4.487 2.473 0 4.487 2.014 4.487 4.49-.001 2.475-2.014 4.488-4.49 4.488z"/>
                        </svg>
                    </div>
                    <div>
                        <h3 id="waModalTitle" class="font-semibold text-gray-900 text-sm">Penawaran Produk &amp; Jasa (B2B)</h3>
                        <p class="text-[11px] text-gray-400">Pilih contoh pesan dan sesuaikan penawaran Anda</p>
                    </div>
                </div>
                <button type="button" aria-label="Tutup pesan WhatsApp" data-click="closeWaModal" class="w-8 h-8 rounded-lg bg-slate-100 hover:bg-slate-200 text-gray-500 flex items-center justify-center transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            <div class="mt-4 space-y-3.5">
                <!-- Target Detail Header -->
                <div class="p-3 bg-slate-50 rounded-lg border border-gray-200/80 text-xs space-y-1">
                    <div class="flex items-center justify-between">
                        <span class="font-semibold text-gray-900 text-sm" id="modalShopName">-</span>
                        <span id="modalWebBadge" class="px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-200">
                            Website Belum Terdeteksi
                        </span>
                    </div>
                    <div class="flex items-center justify-between text-gray-500 text-[11px]">
                        <span id="modalPhone">-</span>
                        <span id="modalCategory" class="text-slate-600 font-semibold">-</span>
                    </div>
                </div>

                <div>
                    <label for="waBusinessPreset" class="block text-xs font-bold text-gray-700 mb-1.5">Bidang penawaran Anda</label>
                    <select id="waBusinessPreset" class="w-full p-2 border border-gray-200 rounded-lg text-xs" data-change="selectWaPitchPreset" data-change-args="[1]">
                        <?php foreach (\App\Services\BusinessPresets::all() as $id => $preset): ?>
                        <option value="<?= e($id) ?>"><?= e($preset['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Sesuaikan identitas dan produk Anda pada pesan. Pilih follow-up jika sudah pernah menghubungi.</p>
                </div>

                <!-- Template Selection Buttons -->
                <div>
                    <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wider mb-1.5">Tahap Pesan:</label>
                    <div class="grid grid-cols-3 gap-1.5 text-[10px] font-bold">
                        <button type="button" data-click="selectWaPitchPreset" data-click-args="[1]" id="btnPreset1" class="p-2 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 hover:bg-emerald-100 transition-all text-center">
                            1. Perkenalan
                        </button>
                        <button type="button" data-click="selectWaPitchPreset" data-click-args="[2]" id="btnPreset2" class="p-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-slate-50 transition-all text-center">
                            2. Diskusi Kebutuhan
                        </button>
                        <button type="button" data-click="selectWaPitchPreset" data-click-args="[3]" id="btnPreset3" class="p-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-slate-50 transition-all text-center">
                            3. Follow-up
                        </button>
                    </div>
                </div>

                <!-- Textarea Editor -->
                <div>
                    <div class="flex items-center justify-between mb-1">
                        <label class="block text-[11px] font-bold text-gray-700 uppercase tracking-wider">Teks Pesan WhatsApp</label>
                        <span class="text-[10px] text-gray-400">Bisa diedit manual sebelum dikirim</span>
                    </div>
                    <textarea aria-label="Pesan penawaran WhatsApp" id="modalWaText" rows="6" class="w-full text-xs p-3 border border-gray-200 rounded-lg focus:ring-2 focus:ring-[#15D8B3] focus:border-[#15D8B3] outline-none text-gray-800 leading-relaxed"></textarea>
                </div>

                <!-- Actions -->
                <div class="flex items-center space-x-2 pt-1">
                    <button data-click="sendWaMessage" class="flex-1 btn-brand py-2.5 px-4 rounded-lg text-xs font-semibold flex items-center justify-center space-x-2 shadow-sm">
                        <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                            <path d="M12.031 6.172c-3.181 0-5.767 2.586-5.768 5.766-.001 1.298.38 2.27 1.019 3.287l-.711 2.598 2.664-.699c.97.53 1.895.82 2.796.82 3.183 0 5.768-2.587 5.768-5.766.001-3.187-2.575-5.806-5.768-5.806z"/>
                        </svg>
                        <span>Buka WhatsApp Sekarang</span>
                    </button>
                    <button type="button" aria-label="Tutup pesan WhatsApp" data-click="closeWaModal" class="py-2.5 px-4 rounded-lg border border-gray-200 text-xs font-semibold text-gray-600 hover:bg-gray-50 transition-colors">
                        Batal
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Frontend Utilities Script -->
    <script nonce="<?= e(csp_nonce()) ?>">
        // Global Toast Notification Helper
        function escapeHtml(value) {
            const node = document.createElement('span');
            node.textContent = String(value ?? '');
            return node.innerHTML;
        }

        function showToast(message, type = 'success') {
            const container = document.getElementById('toastContainer');
            if (!container) return;

            const toast = document.createElement('div');
            const colors = {
                success: 'bg-emerald-900/90 text-white border-emerald-700',
                error: 'bg-rose-900/90 text-white border-rose-700',
                info: 'bg-slate-900/90 text-white border-slate-700'
            };

            toast.className = `px-4 py-3 rounded-lg shadow-sm text-xs font-medium border flex items-center space-x-2 pointer-events-auto transform transition-all duration-300 translate-y-2 opacity-0 ${colors[type] || colors.info}`;
            toast.innerHTML = `
                <svg class="w-4 h-4 text-[#15D8B3] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg>
                <span>${escapeHtml(message)}</span>
            `;

            container.appendChild(toast);

            // Animate in
            requestAnimationFrame(() => {
                toast.classList.remove('translate-y-2', 'opacity-0');
            });

            // Auto dismiss after 3 seconds
            setTimeout(() => {
                toast.classList.add('opacity-0', 'translate-y-2');
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }

        // Copy text to clipboard helper
        function copyToClipboard(text, label = 'Teks') {
            if (!text) return;
            navigator.clipboard.writeText(text).then(() => {
                showToast(`${label} berhasil disalin ke clipboard!`, 'success');
            }).catch(() => {
                // Fallback for older browsers
                const temp = document.createElement('textarea');
                temp.value = text;
                document.body.appendChild(temp);
                temp.select();
                document.execCommand('copy');
                document.body.removeChild(temp);
                showToast(`${label} berhasil disalin!`, 'success');
            });
        }

        // WhatsApp Modal & B2B Pitch Handlers
        let waReturnFocus = null;
        let currentWaTarget = { name: '', category: '', phone: '', wa: '', hasNoWebsite: true, location: '' };

        const businessPitchPresets = <?= json_encode(\App\Services\BusinessPresets::all(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;

        function getNichePitchCopy(shop, category, location, type) {
            const segment = document.getElementById('waBusinessPreset').value;
            const preset = businessPitchPresets[segment] || businessPitchPresets.general;
            return preset.messages[type - 1].replaceAll('{{nama_bisnis}}', () => shop);
        }

        function selectWaPitchPreset(presetNum) {
            // Update button styles
            for (let i = 1; i <= 3; i++) {
                const btn = document.getElementById('btnPreset' + i);
                if (btn) {
                    if (i === presetNum) {
                        btn.className = "p-2 rounded-lg border border-emerald-300 bg-emerald-50 text-emerald-800 font-bold transition-all text-center";
                    } else {
                        btn.className = "p-2 rounded-lg border border-gray-200 bg-white text-gray-700 hover:bg-slate-50 transition-all text-center";
                    }
                }
            }

            const text = getNichePitchCopy(currentWaTarget.name, currentWaTarget.category, currentWaTarget.location, presetNum);
            document.getElementById('modalWaText').value = text;
        }

        function openWaModal(name, category, phone, wa, hasNoWebsite = true, location = '') {
            waReturnFocus = document.activeElement;
            currentWaTarget = { name, category, phone, wa, hasNoWebsite, location };
            
            document.getElementById('modalShopName').textContent = name;
            document.getElementById('modalPhone').textContent = phone ? `${phone} (WA: +${wa})` : 'Nomor tidak tersedia';
            document.getElementById('modalCategory').textContent = category || 'Bisnis';
            
            const badge = document.getElementById('modalWebBadge');
            if (badge) {
                if (hasNoWebsite) {
                    badge.textContent = 'Website Belum Terdeteksi';
                    badge.className = 'px-2 py-0.5 rounded-lg text-[10px] font-bold bg-rose-100 text-rose-700 border border-rose-200';
                } else {
                    badge.textContent = 'Tautan Website Tersedia';
                    badge.className = 'px-2 py-0.5 rounded-lg text-[10px] font-bold bg-slate-100 text-slate-600 border border-slate-200';
                }
            }

            selectWaPitchPreset(1);
            document.getElementById('waModal').classList.remove('hidden');
            document.getElementById('modalWaText').focus();
        }

        function closeWaModal() {
            document.getElementById('waModal').classList.add('hidden');
            waReturnFocus?.focus();
        }

        function sendWaMessage() {
            if (!currentWaTarget.wa) {
                showToast('Nomor WhatsApp target tidak valid.', 'error');
                return;
            }
            const text = document.getElementById('modalWaText').value;
            const url = `https://wa.me/${currentWaTarget.wa}?text=${encodeURIComponent(text)}`;
            window.open(url, '_blank');
            closeWaModal();
        }

        // Client-Side Real-Time Filter for Table (Fallback for pages without custom workspace filter)
        document.addEventListener('DOMContentLoaded', function() {
            const tableSearchInput = document.getElementById('tableQuickSearch');
            if (tableSearchInput && typeof window.applyTableFilters !== 'function') {
                tableSearchInput.addEventListener('input', function() {
                    const q = this.value.toLowerCase().trim();
                    const rows = document.querySelectorAll('#prospectTableBody tr.prospect-row');
                    let visibleCount = 0;

                    rows.forEach(row => {
                        const content = row.innerText.toLowerCase();
                        if (content.includes(q)) {
                            row.style.display = '';
                            visibleCount++;
                        } else {
                            row.style.display = 'none';
                        }
                    });

                    const countIndicator = document.getElementById('visibleRowCount');
                    if (countIndicator) {
                        countIndicator.textContent = visibleCount;
                    }
                });
            }

            // Checkbox Select All Handler
            const selectAllCheckbox = document.getElementById('selectAllProspects');
            if (selectAllCheckbox && typeof window.visibleProspectCheckboxes !== 'function') {
                selectAllCheckbox.addEventListener('change', function() {
                    const checkboxes = document.querySelectorAll('.prospect-checkbox');
                    checkboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
                    updateBulkExportButton();
                });

                document.querySelectorAll('.prospect-checkbox').forEach(cb => {
                    cb.addEventListener('change', updateBulkExportButton);
                });
            }

            function updateBulkExportButton() {
                const checked = document.querySelectorAll('.prospect-checkbox:checked');
                const bulkBtn = document.getElementById('bulkExportBtn');
                const bulkCount = document.getElementById('selectedCount');
                if (bulkBtn && bulkCount) {
                    if (checked.length > 0) {
                        bulkBtn.classList.remove('hidden');
                        bulkCount.textContent = checked.length;
                    } else {
                        bulkBtn.classList.add('hidden');
                    }
                }
            }

            // Bulk Export Selected Handlers
            const bulkBtn = document.getElementById('bulkExportBtn');
            if (bulkBtn && typeof window.selectedVisibleProspects !== 'function') {
                bulkBtn.addEventListener('click', function() {
                    const checked = Array.from(document.querySelectorAll('.prospect-checkbox:checked')).map(cb => cb.value);
                    if (checked.length === 0) return;
                    window.location.href = `<?= base_url('export') ?>?ids=${checked.join(',')}`;
                });
            }
        });
    </script>

    <!-- Fullscreen Scraping Loading Screen (Screen-Blocker) -->
    <div id="scrapingLoadingOverlay" class="fixed inset-0 z-[99999] bg-slate-950/85 backdrop-blur-md hidden flex items-center justify-center p-4 transition-all duration-300 select-none">
        <div class="bg-slate-900/95 border border-slate-700/80 shadow-sm rounded-lg p-6 sm:p-8 max-w-md w-full text-center relative overflow-hidden backdrop-blur-xl animate-scale-up">
            
            <!-- Ambient glowing background lights -->
            <div class="absolute -top-20 -left-20 w-48 h-48 bg-[#15D8B3]/15 rounded-lg blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-20 -right-20 w-48 h-48 bg-teal-500/15 rounded-lg blur-3xl pointer-events-none"></div>

            <!-- Radar / Sonar Scanner Animation -->
            <div class="relative w-24 h-24 mx-auto mb-5 flex items-center justify-center">
                <!-- Outer Pulse Rings -->
                <div class="absolute inset-0 rounded-lg bg-[#15D8B3]/20 " style="animation-duration: 2.2s;"></div>
                <div class="absolute -inset-2 rounded-lg border border-[#15D8B3]/30 "></div>
                <!-- Radar Rotating Dashed Ring -->
                <div class="absolute inset-0 rounded-lg border-2 border-dashed border-[#15D8B3]/50 animate-spin" style="animation-duration: 9s;"></div>
                <!-- Core Glowing Map Pin Icon -->
                <div class="relative w-16 h-16 rounded-lg bg-gradient-to-tr from-slate-900 via-slate-800 to-slate-900 border border-[#15D8B3]/60 flex items-center justify-center shadow-sm shadow-[#15D8B3]/25">
                    <svg class="w-8 h-8 text-[#15D8B3] " fill="none" stroke="currentColor" viewBox="0 0 24 24" style="animation-duration: 2s;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                </div>
            </div>

            <!-- Title & Target Details -->
            <h3 class="text-base sm:text-lg font-semibold text-white tracking-wide">
                Sedang Mengekstrak Data Prospek
            </h3>
            <div id="scrapeTargetInfo" class="text-xs text-slate-300 mt-2 leading-relaxed font-medium">
                Menghubungi live engine Google Maps...
            </div>

            <!-- Indeterminate Progress Bar -->
            <div class="w-full bg-slate-800/90 rounded-lg h-2 mt-5 overflow-hidden border border-slate-700/60 relative">
                <div class="h-full bg-gradient-to-r from-teal-400 via-[#15D8B3] to-emerald-300 rounded-lg w-full animate-indeterminate"></div>
            </div>

            <!-- Live Rotating Status Step -->
            <div class="mt-4 px-3.5 py-2.5 rounded-lg bg-slate-800/80 border border-slate-700/60 flex items-center justify-center space-x-2">
                <span class="w-2 h-2 rounded-lg bg-[#15D8B3]  flex-shrink-0"></span>
                <span id="scrapeStatusText" class="text-xs font-bold text-[#15D8B3] transition-opacity duration-200 truncate">
                    Menghubungi server Google Maps...
                </span>
            </div>

            <!-- Screen Lock Notice -->
            <div class="mt-5 pt-4 border-t border-slate-800/80 text-[11px] text-slate-400 flex items-center justify-center space-x-1.5">
                <svg class="w-4 h-4 text-amber-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <span>Layar dikunci sementara. Mohon jangan refresh atau klik halaman.</span>
            </div>

        </div>
    </div>

    <!-- Custom Application JS -->
    <script src="<?= base_url('assets/js/app.js?v=panel-5') ?>"></script>
<script src="<?= base_url('assets/js/panel.js?v=3') ?>" defer></script>
</body>
</html>
