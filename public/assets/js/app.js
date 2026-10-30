/**
 * Application JavaScript Logic
 * Gmap Prospek
 */

document.addEventListener('DOMContentLoaded', function() {
    const scraperForm = document.getElementById('scraperForm');
    const searchSubmitBtn = document.getElementById('searchSubmitBtn');
    const overlay = document.getElementById('scrapingLoadingOverlay');
    const statusTextEl = document.getElementById('scrapeStatusText');
    const targetInfoEl = document.getElementById('scrapeTargetInfo');

    // A button disabled by the server (lead quota used up) must stay disabled.
    if (scraperForm && searchSubmitBtn && !searchSubmitBtn.disabled) {
        const originalButton = searchSubmitBtn.innerHTML;
        let statusInterval;
        window.addEventListener('pageshow', () => {
            clearInterval(statusInterval);
            searchSubmitBtn.disabled = false;
            searchSubmitBtn.removeAttribute('aria-busy');
            searchSubmitBtn.innerHTML = originalButton;
            searchSubmitBtn.classList.remove('opacity-80', 'cursor-not-allowed');
            overlay?.classList.add('hidden');
            document.body.style.overflow = '';
            window.removeEventListener('keydown', blockKeyEvents, true);
        });
        scraperForm.addEventListener('submit', function(e) {
            const keywordInput = document.getElementById('keyword');
            const locationInput = document.getElementById('location');
            const limitSelect = document.getElementById('limit');
            const nameInput = document.getElementById('name');

            const keyword = keywordInput ? keywordInput.value.trim() : '';
            const location = locationInput ? locationInput.value.trim() : '';
            const limit = limitSelect ? limitSelect.value : '20';
            const campaignName = nameInput && nameInput.value.trim() ? nameInput.value.trim() : `Proyek ${keyword} - ${location}`;

            // If required inputs are empty, allow browser native validation to show tooltip
            if (!keyword || !location) {
                return;
            }

            // 1. Update button state
            searchSubmitBtn.disabled = true;
            searchSubmitBtn.setAttribute('aria-busy', 'true');
            searchSubmitBtn.innerHTML = `
                <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-slate-900" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span>Mencari prospek bisnis...</span>
            `;
            searchSubmitBtn.classList.add('opacity-80', 'cursor-not-allowed');

            // 2. Activate Fullscreen Screen-Blocker Overlay
            if (overlay) {
                if (targetInfoEl) {
                    targetInfoEl.innerHTML = `
                        <div class="font-semibold text-white text-xs mb-0.5">${escapeHtml(campaignName)}</div>
                        <div class="text-[11px] text-slate-400">Target: "${escapeHtml(keyword)}" &bull; Area: "${escapeHtml(location)}" (${escapeHtml(limit)} Prospek)</div>
                    `;
                }

                overlay.classList.remove('hidden');
                document.body.style.overflow = 'hidden';

                // Prevent any keyboard navigation or interaction during scraping
                window.addEventListener('keydown', blockKeyEvents, true);

                // Rotate scraping status messages smoothly
                const steps = [
                    "🚀 Menginisialisasi pencarian Google Maps...",
                    "🔍 Memindai direktori bisnis riil Google Maps...",
                    "📞 Mengekstrak nomor telepon & kontak WhatsApp...",
                    "🌐 Membaca tautan website yang tersedia...",
                    "💾 Menyusun & mengisolasi prospek ke workspace proyek...",
                    "Menyaring bisnis yang memiliki nomor kontak..."
                ];

                let stepIndex = 0;
                if (statusTextEl) {
                    statusTextEl.textContent = steps[0];
                    statusInterval = setInterval(function() {
                        stepIndex = (stepIndex + 1) % steps.length;
                        statusTextEl.style.opacity = '0';
                        setTimeout(function() {
                            statusTextEl.textContent = steps[stepIndex];
                            statusTextEl.style.opacity = '1';
                        }, 200);
                    }, 2600);
                }
            }
        });
    }

    function blockKeyEvents(e) {
        if (overlay && !overlay.classList.contains('hidden')) {
            // Block Escape, Tab, Enter, etc.
            if (e.key === 'Escape' || e.key === 'Tab') {
                e.preventDefault();
                e.stopPropagation();
            }
        }
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
