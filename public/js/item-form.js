(() => {
    'use strict';

    const form = document.getElementById('item-form');

    if (!form) {
        return;
    }

    const itemType = document.getElementById('item_type');
    const trackingType = document.getElementById('tracking_type');
    const quantity = document.getElementById('quantity');
    const trackingHelp = document.getElementById('tracking-help');
    const quantityHelp = document.getElementById('quantity-help');
    const bookNotice = document.getElementById('book-notice');

    const syncFields = () => {
        const isBook = itemType.value === 'book';
        const isAsset = isBook || trackingType.value === 'asset';
        const quantityOption = trackingType.querySelector('option[value="quantity"]');

        if (isBook) {
            trackingType.value = 'asset';
        }

        if (quantityOption) {
            quantityOption.disabled = isBook;
        }

        quantity.step = isAsset ? '1' : '0.01';
        quantity.min = isAsset ? '1' : '0.01';

        trackingHelp.textContent = isBook
            ? 'Buku selalu dicatat per eksemplar agar setiap buku memiliki barcode dan status sendiri.'
            : isAsset
                ? 'Per aset digunakan jika setiap unit mempunyai kode inventaris sendiri.'
                : 'Berdasarkan jumlah digunakan untuk barang yang tidak perlu diberi kode per unit.';

        quantityHelp.textContent = isAsset
            ? 'Masukkan jumlah unit fisik. Setiap unit akan memperoleh kode aset.'
            : 'Masukkan saldo stok awal. Nilai desimal dapat digunakan jika satuannya mendukung.';

        bookNotice.hidden = !isBook;
    };

    itemType.addEventListener('change', syncFields);
    trackingType.addEventListener('change', syncFields);
    syncFields();
})();
