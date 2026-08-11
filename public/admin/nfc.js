(() => {
    'use strict';
    const wizard = document.getElementById('nfc-wizard');
    const createForm = document.getElementById('create-tag-form');
    const programPanel = document.getElementById('program-panel');
    const title = document.getElementById('wizard-title');
    const deleteDialog = document.getElementById('delete-dialog');
    const backButton = document.getElementById('wizard-back');
    const nextButton = document.getElementById('wizard-next');
    const submitButton = document.getElementById('wizard-submit');
    const doneButton = document.getElementById('wizard-done');
    let step = 1;

    const showCreateStep = (nextStep) => {
        step = nextStep;
        createForm.hidden = false;
        programPanel.hidden = true;
        title.textContent = 'Ajouter une puce';
        createForm.querySelectorAll('[data-step]').forEach(panel => {
            const active = Number(panel.dataset.step) === step;
            panel.hidden = !active;
            panel.classList.toggle('current', active);
        });
        createForm.querySelectorAll('.wizard-progress span').forEach((dot, index) => dot.classList.toggle('current', index + 1 === step));
        backButton.hidden = step === 1;
        nextButton.hidden = step !== 1;
        submitButton.hidden = step !== 2;
        doneButton.hidden = true;
        document.querySelector('.dialog-body').scrollTop = 0;
    };

    const showProgram = (button) => {
        createForm.hidden = true;
        programPanel.hidden = false;
        const nickname = button?.dataset.nickname || document.getElementById('program-nickname').textContent;
        title.textContent = 'Programmer la puce';
        document.getElementById('program-nickname').textContent = nickname;
        if (button) {
            document.getElementById('sdm-url').value = button.dataset.url || '';
            document.getElementById('sdm-key').value = button.dataset.key || '';
        }
        backButton.hidden = true;
        nextButton.hidden = true;
        submitButton.hidden = true;
        doneButton.hidden = false;
        document.querySelector('.dialog-body').scrollTop = 0;
        if (!wizard.open) wizard.showModal();
    };

    document.getElementById('add-tag')?.addEventListener('click', () => {
        createForm.reset();
        showCreateStep(1);
        wizard.showModal();
        window.setTimeout(() => createForm.elements.nickname?.focus(), 80);
    });
    document.querySelectorAll('.program-profile').forEach(button => button.addEventListener('click', () => showProgram(button)));

    nextButton?.addEventListener('click', () => {
        const required = [...createForm.querySelector('[data-step="1"]').querySelectorAll('[required]')];
        if (!required.every(field => field.reportValidity())) return;
        showCreateStep(2);
    });
    backButton?.addEventListener('click', () => showCreateStep(1));

    wizard?.querySelectorAll('[data-close-dialog]').forEach(button => button.addEventListener('click', () => wizard.close()));
    wizard?.addEventListener('click', event => { if (event.target === wizard) wizard.close(); });
    if (wizard?.dataset.autoOpen === 'program') {
        showProgram(null);
        const clean = new URL(window.location.href);
        clean.searchParams.delete('setup');
        window.history.replaceState({}, '', clean);
    }

    document.querySelectorAll('.tag-more').forEach(button => {
        button.addEventListener('click', event => {
            event.stopPropagation();
            const menu = document.getElementById(button.getAttribute('aria-controls'));
            document.querySelectorAll('.tag-action-menu').forEach(other => { if (other !== menu) other.hidden = true; });
            menu.hidden = !menu.hidden;
            button.setAttribute('aria-expanded', String(!menu.hidden));
        });
    });
    document.addEventListener('click', () => document.querySelectorAll('.tag-action-menu').forEach(menu => { menu.hidden = true; }));
    document.querySelectorAll('.tag-action-menu').forEach(menu => menu.addEventListener('click', event => event.stopPropagation()));

    document.querySelectorAll('.delete-profile').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('delete-profile-id').value = button.dataset.id;
            document.getElementById('delete-profile-name').textContent = button.dataset.nickname;
            deleteDialog.showModal();
        });
    });
    deleteDialog?.querySelector('[data-close-delete]')?.addEventListener('click', () => deleteDialog.close());
    deleteDialog?.addEventListener('click', event => { if (event.target === deleteDialog) deleteDialog.close(); });
})();
