(() => {
    document.querySelectorAll('[data-message-scroll]').forEach(panel => {
        panel.scrollTop = panel.scrollHeight;
    });
})();
