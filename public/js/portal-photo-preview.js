(() => {
    const modal = document.querySelector('[data-photo-modal]');

    if (!modal) {
        return;
    }

    const dialog = modal.querySelector('[data-photo-modal-dialog]');
    const image = modal.querySelector('[data-photo-modal-image]');
    const title = modal.querySelector('[data-photo-modal-title]');
    const closeButtons = modal.querySelectorAll('[data-photo-modal-close]');
    let lastTrigger = null;

    const closeModal = () => {
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('portal-modal-open');
        image.removeAttribute('src');
        image.alt = '';

        if (lastTrigger) {
            lastTrigger.focus();
        }
    };

    const openModal = (trigger) => {
        const source = trigger.dataset.photoSrc;

        if (!source) {
            return;
        }

        const itemName = trigger.dataset.photoTitle || 'Foto barang';
        lastTrigger = trigger;
        image.src = source;
        image.alt = `Foto ${itemName}`;
        title.textContent = itemName;
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('portal-modal-open');
        dialog.focus();
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-photo-preview]');

        if (trigger) {
            openModal(trigger);
        }
    });

    closeButtons.forEach((button) => button.addEventListener('click', closeModal));

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !modal.hidden) {
            closeModal();
        }
    });
})();
