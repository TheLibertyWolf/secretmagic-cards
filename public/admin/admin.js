(() => {
    'use strict';

    const body = document.body;
    const suit = document.getElementById('suit');
    const rank = document.getElementById('rank');
    const style = document.getElementById('visual-style');
    const directLink = document.getElementById('direct-link');
    const preview = document.getElementById('card-preview');
    const qrRoot = document.getElementById('qrcode');
    const customLimit = document.getElementById('custom-limit');
    let qr = null;

    const copyText = async (value, button) => {
        try {
            await navigator.clipboard.writeText(value);
        } catch (_) {
            const helper = document.createElement('textarea');
            helper.value = value;
            helper.style.position = 'fixed';
            helper.style.opacity = '0';
            document.body.appendChild(helper);
            helper.select();
            document.execCommand('copy');
            helper.remove();
        }
        if (button) {
            const original = button.textContent;
            button.textContent = 'Copié';
            button.classList.add('copy-confirm');
            window.setTimeout(() => {
                button.textContent = original;
                button.classList.remove('copy-confirm');
            }, 1200);
        }
    };

    const updateCard = () => {
        if (!suit || !rank || !style) return;
        const base = body.dataset.baseUrl || window.location.origin;
        const params = new URLSearchParams({c: suit.value, v: rank.value, s: style.value});
        const url = `${base}/?${params.toString()}`;
        directLink.value = url;
        preview.src = url;

        qrRoot.replaceChildren();
        qr = new QRCode(qrRoot, {
            text: url,
            width: 196,
            height: 196,
            colorDark: '#111318',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.M
        });
    };

    [suit, rank, style].forEach(control => control?.addEventListener('change', updateCard));
    updateCard();

    document.querySelectorAll('[name="limit_mode"]').forEach(control => {
        control.addEventListener('change', () => {
            const custom = document.querySelector('[name="limit_mode"]:checked')?.value === 'custom';
            customLimit.disabled = !custom;
            if (custom) customLimit.focus();
        });
    });

    document.querySelectorAll('[data-copy-target]').forEach(button => {
        button.addEventListener('click', () => {
            const target = document.getElementById(button.dataset.copyTarget);
            if (target) copyText(target.value, button);
        });
    });
    document.querySelectorAll('[data-copy-value]').forEach(button => {
        button.addEventListener('click', () => copyText(button.dataset.copyValue, button));
    });
})();
