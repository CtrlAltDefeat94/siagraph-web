document.addEventListener('DOMContentLoaded', () => {
    const startEl = document.getElementById('startDate');
    const endEl = document.getElementById('endDate');
    if (typeof startDate !== 'undefined' && startEl) {
        startEl.textContent = startDate;
    }
    if (typeof endDate !== 'undefined' && endEl) {
        endEl.textContent = endDate;
    }
});
