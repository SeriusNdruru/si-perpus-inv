(() => {
    const selectAll = document.querySelector('#select-all-assets');
    const checkboxes = Array.from(document.querySelectorAll('.js-asset-checkbox:not(:disabled)'));
    const submitButton = document.querySelector('#bulk-submit');
    const selectedCount = document.querySelector('#selected-count');
    const bulkForm = document.querySelector('#bulk-assignment-form');

    if (!selectAll || !submitButton || !selectedCount || !bulkForm) {
        return;
    }

    const refreshState = () => {
        const checked = checkboxes.filter((checkbox) => checkbox.checked).length;
        selectedCount.textContent = String(checked);
        submitButton.disabled = checked === 0;
        selectAll.checked = checkboxes.length > 0 && checked === checkboxes.length;
        selectAll.indeterminate = checked > 0 && checked < checkboxes.length;
    };

    selectAll.addEventListener('change', () => {
        checkboxes.forEach((checkbox) => {
            checkbox.checked = selectAll.checked;
        });
        refreshState();
    });

    checkboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshState));

    bulkForm.addEventListener('submit', (event) => {
        const checked = checkboxes.filter((checkbox) => checkbox.checked).length;

        if (checked === 0 || !window.confirm(`Tempatkan ${checked} eksemplar ke rak yang dipilih?`)) {
            event.preventDefault();
        }
    });

    refreshState();
})();
