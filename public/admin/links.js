(() => {
    'use strict';
    const dialog = document.getElementById('link-delete-dialog');
    if (!dialog) return;
    document.querySelectorAll('.delete-short-link').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('delete-link-id').value = button.dataset.id;
            document.getElementById('delete-link-code').textContent = button.dataset.code;
            dialog.showModal();
        });
    });
    dialog.querySelector('[data-cancel-delete]').addEventListener('click', () => dialog.close());
})();
