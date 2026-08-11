(() => {
    'use strict';
    const dialog = document.getElementById('about-dialog');
    if (!dialog) return;
    let opener = null;
    document.querySelectorAll('[data-open-about]').forEach(button => button.addEventListener('click', () => {
        opener = button;
        dialog.showModal();
        dialog.querySelector('[data-close-about]')?.focus();
    }));
    dialog.querySelectorAll('[data-close-about]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
    dialog.addEventListener('close', () => opener?.focus());
})();
