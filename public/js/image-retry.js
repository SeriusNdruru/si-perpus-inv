(() => {
    const maximumRetries = 2;

    const retryImage = (image) => {
        const currentRetries = Number(image.dataset.imageRetryCount || 0);

        if (currentRetries >= maximumRetries || !image.currentSrc && !image.src) {
            return;
        }

        image.dataset.imageRetryCount = String(currentRetries + 1);
        const originalSource = image.dataset.imageOriginalSrc || image.currentSrc || image.src;
        image.dataset.imageOriginalSrc = originalSource;

        window.setTimeout(() => {
            try {
                const retryUrl = new URL(originalSource, window.location.href);
                retryUrl.searchParams.set('_retry', `${Date.now()}-${currentRetries + 1}`);
                image.src = retryUrl.toString();
            } catch (error) {
                image.src = originalSource;
            }
        }, 700 * (currentRetries + 1));
    };

    document.addEventListener('error', (event) => {
        const image = event.target;

        if (!(image instanceof HTMLImageElement) || !image.hasAttribute('data-image-retry')) {
            return;
        }

        retryImage(image);
    }, true);

    document.addEventListener('load', (event) => {
        const image = event.target;

        if (image instanceof HTMLImageElement && image.hasAttribute('data-image-retry')) {
            image.dataset.imageRetryCount = '0';
        }
    }, true);
})();
