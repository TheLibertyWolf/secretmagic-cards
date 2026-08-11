(() => {
    'use strict';
    const dialog = document.getElementById('account-delete-dialog');
    if (!dialog) return;
    document.querySelectorAll('.account-delete').forEach(button => button.addEventListener('click', () => {
        document.getElementById('delete-account-id').value = button.dataset.id;
        document.getElementById('delete-account-name').textContent = button.dataset.name;
        dialog.showModal();
    }));
    dialog.querySelector('[data-cancel-account-delete]').addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => { if (event.target === dialog) dialog.close(); });
})();
