(() => {
    'use strict';

    const form = document.getElementById('item-form');

    if (!form) {
        return;
    }

    const itemType = document.getElementById('item_type');
    const itemCode = document.getElementById('item_code');
    const trackingType = document.getElementById('tracking_type');
    const trackingTypeDisplay = document.getElementById('tracking_type_display');
    const quantity = document.getElementById('quantity');
    const trackingHelp = document.getElementById('tracking-help');
    const quantityHelp = document.getElementById('quantity-help');
    const bookNotice = document.getElementById('book-notice');
    let nextItemCodes = {};

    const trackingByItemType = {
        book: 'asset',
        equipment: 'asset',
        electronic: 'asset',
        furniture: 'asset',
        consumable: 'quantity',
        other: 'asset',
    };

    const trackingLabels = {
        asset: 'Per Aset',
        quantity: 'Berdasarkan Jumlah',
    };

    if (itemCode?.dataset.codeMap) {
        try {
            nextItemCodes = JSON.parse(itemCode.dataset.codeMap);
        } catch (error) {
            nextItemCodes = {};
        }
    }

    const syncFields = () => {
        const selectedItemType = itemType?.value ?? '';
        const selectedTrackingType = trackingByItemType[selectedItemType] ?? '';
        const isBook = selectedItemType === 'book';
        const isAsset = selectedTrackingType === 'asset';

        if (itemCode) {
            itemCode.value = nextItemCodes[selectedItemType] ?? '';
        }

        if (trackingType) {
            trackingType.value = selectedTrackingType;
        }

        if (trackingTypeDisplay) {
            trackingTypeDisplay.value = trackingLabels[selectedTrackingType] ?? '';
        }

        if (quantity) {
            quantity.step = isAsset || selectedTrackingType === '' ? '1' : '0.01';
            quantity.min = isAsset || selectedTrackingType === '' ? '1' : '0.01';
        }

        if (trackingHelp) {
            trackingHelp.textContent = selectedTrackingType === ''
                ? 'Metode pencatatan ditentukan otomatis berdasarkan jenis barang.'
                : isBook
                    ? 'Buku otomatis dicatat per eksemplar agar setiap buku memiliki barcode dan status sendiri.'
                    : isAsset
                        ? 'Jenis ini otomatis dicatat per aset agar setiap unit memiliki kode inventaris sendiri.'
                        : 'Barang habis pakai otomatis dicatat berdasarkan jumlah stok.';
        }

        if (quantityHelp) {
            quantityHelp.textContent = selectedTrackingType === 'quantity'
                ? 'Masukkan saldo stok awal. Nilai desimal dapat digunakan jika satuannya mendukung.'
                : 'Masukkan jumlah unit fisik. Setiap unit akan memperoleh kode aset.';
        }

        if (bookNotice) {
            bookNotice.hidden = !isBook;
        }
    };

    itemType?.addEventListener('change', syncFields);
    syncFields();
})();
