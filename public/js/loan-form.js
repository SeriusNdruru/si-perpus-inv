(() => {
    const form = document.getElementById('loan-form');
    if (!form) return;

    const config = window.loanFormConfig || {};
    const maxActiveLoans = Number(config.maxActiveLoans || 3);
    const memberSelect = document.getElementById('member_id');
    const searchInput = document.getElementById('asset-search');
    const choices = Array.from(document.querySelectorAll('.asset-choice'));
    const checkboxes = choices.map((choice) => choice.querySelector('input[type="checkbox"]'));
    const countElement = document.getElementById('selected-asset-count');
    const limitElement = document.getElementById('selected-asset-limit');
    const memberMessage = document.getElementById('member-capacity-message');
    const noResult = document.getElementById('asset-picker-no-result');
    const submitButton = document.getElementById('loan-submit-button');

    const selectedOption = () => memberSelect?.options[memberSelect.selectedIndex] || null;

    const remainingCapacity = () => {
        const option = selectedOption();
        if (!option || !option.value) return 0;
        const activeCount = Number(option.dataset.activeCount || 0);
        return Math.max(maxActiveLoans - activeCount, 0);
    };

    const selectedCheckboxes = () => checkboxes.filter((checkbox) => checkbox.checked);

    const updateSelection = (changedCheckbox = null) => {
        const option = selectedOption();
        const capacity = remainingCapacity();
        let selected = selectedCheckboxes();

        if (changedCheckbox?.checked && selected.length > capacity) {
            changedCheckbox.checked = false;
            selected = selectedCheckboxes();
            window.alert(`Anggota hanya memiliki sisa kuota ${capacity} eksemplar.`);
        }

        countElement.textContent = String(selected.length);

        if (!option || !option.value) {
            limitElement.textContent = 'Pilih anggota untuk melihat sisa kuota.';
            memberMessage.textContent = `Batas peminjaman aktif: ${maxActiveLoans} eksemplar per anggota.`;
        } else {
            const activeCount = Number(option.dataset.activeCount || 0);
            const overdueCount = Number(option.dataset.overdueCount || 0);
            limitElement.textContent = `Sisa kuota anggota: ${capacity} eksemplar.`;
            memberMessage.textContent = overdueCount > 0
                ? `Anggota memiliki ${overdueCount} eksemplar terlambat dan tidak dapat meminjam.`
                : `Saat ini ${activeCount} dari ${maxActiveLoans} kuota aktif telah digunakan.`;
        }

        checkboxes.forEach((checkbox) => {
            checkbox.disabled = !option?.value || (!checkbox.checked && selected.length >= capacity);
        });

        if (submitButton) {
            submitButton.disabled = !option?.value || selected.length === 0 || selected.length > capacity;
        }
    };

    const filterChoices = () => {
        const query = (searchInput?.value || '').trim().toLowerCase();
        let visibleCount = 0;

        choices.forEach((choice) => {
            const matches = query === '' || (choice.dataset.search || '').includes(query);
            choice.hidden = !matches;
            if (matches) visibleCount += 1;
        });

        if (noResult) noResult.hidden = visibleCount !== 0;
    };

    memberSelect?.addEventListener('change', () => updateSelection());
    searchInput?.addEventListener('input', filterChoices);
    checkboxes.forEach((checkbox) => {
        checkbox.addEventListener('change', () => updateSelection(checkbox));
    });

    form.addEventListener('submit', (event) => {
        const selected = selectedCheckboxes();
        if (!memberSelect?.value || selected.length === 0) {
            event.preventDefault();
            window.alert('Pilih anggota dan minimal satu eksemplar buku.');
        }
    });

    filterChoices();
    updateSelection();
})();
