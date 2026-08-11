(() => {
    'use strict';
    const wizard = document.getElementById('nfc-wizard');
    const createForm = document.getElementById('create-tag-form');
    const programPanel = document.getElementById('program-panel');
    const title = document.getElementById('wizard-title');
    const archiveDialog = document.getElementById('archive-dialog');
    const editDialog = document.getElementById('edit-card-dialog');
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

    const filterCardOptions = (suit, rank, style) => {
        const raw = style?.selectedOptions[0]?.dataset.cards || '*';
        if (!suit || !rank) return;
        if (raw === '*') {
            [...suit.options, ...rank.options].forEach(option => { option.hidden = false; option.disabled = false; });
            return;
        }
        const allowed = new Set(raw.split(',').filter(Boolean));
        [...suit.options].forEach(option => {
            const valid = [...allowed].some(card => card.startsWith(`${option.value}:`));
            option.hidden = !valid;
            option.disabled = !valid;
        });
        if (suit.selectedOptions[0]?.disabled) suit.value = [...suit.options].find(option => !option.disabled)?.value || '';
        [...rank.options].forEach(option => {
            const valid = allowed.has(`${suit.value}:${option.value}`);
            option.hidden = !valid;
            option.disabled = !valid;
        });
        if (rank.selectedOptions[0]?.disabled) rank.value = [...rank.options].find(option => !option.disabled)?.value || '';
    };

    const editSuit = document.getElementById('edit-suit');
    const editRank = document.getElementById('edit-rank');
    const editStyle = document.getElementById('edit-style');
    document.querySelectorAll('.edit-profile-card').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('edit-profile-id').value = button.dataset.id || '';
            document.getElementById('edit-profile-name').textContent = button.dataset.nickname || '';
            editStyle.value = button.dataset.style || '';
            if (!editStyle.value) editStyle.selectedIndex = 0;
            editSuit.value = button.dataset.suit || '';
            editRank.value = button.dataset.rank || '';
            filterCardOptions(editSuit, editRank, editStyle);
            editDialog.showModal();
        });
    });
    editStyle?.addEventListener('change', () => filterCardOptions(editSuit, editRank, editStyle));
    editSuit?.addEventListener('change', () => filterCardOptions(editSuit, editRank, editStyle));
    editDialog?.querySelectorAll('[data-close-edit]').forEach(button => button.addEventListener('click', () => editDialog.close()));

    document.querySelectorAll('.archive-profile').forEach(button => {
        button.addEventListener('click', () => {
            document.getElementById('archive-profile-id').value = button.dataset.id;
            document.getElementById('archive-profile-name').textContent = button.dataset.nickname;
            archiveDialog.showModal();
        });
    });
    archiveDialog?.querySelector('[data-close-archive]')?.addEventListener('click', () => archiveDialog.close());

    const nfcSuit = document.getElementById('nfc-suit');
    const nfcRank = document.getElementById('nfc-rank');
    const nfcStyle = document.getElementById('nfc-style');
    const filterNfcCards = () => filterCardOptions(nfcSuit, nfcRank, nfcStyle);
    nfcStyle?.addEventListener('change', filterNfcCards);
    nfcSuit?.addEventListener('change', filterNfcCards);
    filterNfcCards();
})();
