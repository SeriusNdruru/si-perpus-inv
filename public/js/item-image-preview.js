document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-image-input]').forEach((input) => {
        input.addEventListener('change', () => {
            const file = input.files?.[0];
            const wrapper = input.closest('.item-photo-upload');
            const image = wrapper?.querySelector('[data-image-element]');
            const placeholder = wrapper?.querySelector('[data-image-placeholder]');

            if (!file || !image) {
                return;
            }

            const reader = new FileReader();
            reader.addEventListener('load', () => {
                image.src = String(reader.result);
                image.hidden = false;
                if (placeholder) {
                    placeholder.hidden = true;
                }
            });
            reader.readAsDataURL(file);
        });
    });
});
