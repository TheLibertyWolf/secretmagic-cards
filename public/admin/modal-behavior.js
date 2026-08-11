(() => {
    'use strict';

    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('click', event => {
            if (event.target !== dialog) return;
            event.preventDefault();
            dialog.classList.remove('modal-static-feedback');
            void dialog.offsetWidth;
            dialog.classList.add('modal-static-feedback');
            window.setTimeout(() => dialog.classList.remove('modal-static-feedback'), 220);
        });
    });
})();
