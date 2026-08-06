(() => {
    const modal = document.getElementById('member-photo-preview-modal');
    const modalImage = document.getElementById('member-photo-preview-image');
    const modalCaption = document.getElementById('member-photo-preview-caption');
    const closeButton = modal?.querySelector('[data-member-photo-preview-close]');

    if (!modal || !modalImage || !closeButton) {
        return;
    }

    let lastTrigger = null;

    const getPreviewSource = (trigger) => {
        const configuredSource = trigger.getAttribute('data-preview-src');
        if (configuredSource) {
            return configuredSource;
        }

        const sourceImage = trigger.querySelector('img:not([hidden])');
        return sourceImage?.currentSrc || sourceImage?.src || '';
    };

    const openPreview = (trigger) => {
        const source = getPreviewSource(trigger);
        if (!source) {
            return;
        }

        const sourceImage = trigger.querySelector('img:not([hidden])');
        modalImage.src = source;
        modalImage.alt = sourceImage?.alt || 'Pratinjau foto profil';
        modalCaption.textContent = sourceImage?.alt || 'Foto profil siswa';
        lastTrigger = trigger;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('member-photo-preview-open');
        closeButton.focus();
    };

    const closePreview = () => {
        if (modal.hidden) {
            return;
        }

        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('member-photo-preview-open');
        modalImage.removeAttribute('src');
        if (lastTrigger instanceof HTMLElement) {
            lastTrigger.focus();
        }
        lastTrigger = null;
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-member-photo-preview]');
        if (trigger) {
            event.preventDefault();
            openPreview(trigger);
            return;
        }

        if (event.target.closest('[data-member-photo-preview-close]') || event.target === modal) {
            closePreview();
        }
    });

    document.addEventListener('keydown', (event) => {
        const trigger = event.target.closest?.('[data-member-photo-preview]');
        if (trigger && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            openPreview(trigger);
            return;
        }

        if (event.key === 'Escape' && !modal.hidden) {
            closePreview();
        }
    });
})();
