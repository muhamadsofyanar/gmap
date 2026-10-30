(() => {
    const dropdown = document.getElementById('accountDropdown');
    if (!dropdown) return;
    const trigger = dropdown.querySelector('summary');
    dropdown.addEventListener('toggle', () => trigger.setAttribute('aria-expanded', String(dropdown.open)));
    document.addEventListener('click', event => {
        if (!dropdown.contains(event.target)) dropdown.open = false;
    });
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape' && dropdown.open) {
            dropdown.open = false;
            trigger.focus();
        }
    });
    dropdown.addEventListener('focusout', () => {
        requestAnimationFrame(() => { if (!dropdown.contains(document.activeElement)) dropdown.open = false; });
    });
})();
