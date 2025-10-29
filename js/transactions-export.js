document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('tx-export-form');
    if (!form) return;

    const readCookie = (name) => {
        const cookieString = document.cookie || '';
        const pairs = cookieString.split(';');
        for (const pair of pairs) {
            const trimmed = pair.trim();
            if (!trimmed) continue;
            if (trimmed.toLowerCase().startsWith(name.toLowerCase() + '=')) {
                return trimmed.substring(name.length + 1);
            }
        }
        return null;
    };

    const addressInput = document.getElementById('tx-address');
    const dateInput = document.getElementById('tx-date');
    const currencySelect = document.getElementById('tx-currency');
    const downloadBtn = document.getElementById('tx-download-btn');
    const viewRawBtn = document.getElementById('tx-view-raw-btn');
    const statusEl = document.getElementById('tx-status');

    // Prefill the date with January 1st of the current year by default
    if (dateInput && !dateInput.value) {
        const now = new Date();
        const defaultDate = new Date(Date.UTC(now.getUTCFullYear(), 0, 1))
            .toISOString()
            .slice(0, 10);
        dateInput.value = defaultDate;
    }

    const updateStatus = (message, isError = false) => {
        if (!statusEl) return;
        statusEl.textContent = message;
        statusEl.classList.remove('text-danger', 'text-success', 'text-info', 'text-secondary');

        if (!message) {
            statusEl.classList.add('text-secondary');
        } else if (isError) {
            statusEl.classList.add('text-danger');
        } else {
            statusEl.classList.add('text-info');
        }
    };

    const buildQuery = (format = 'csv') => {
        const params = new URLSearchParams();
        if (addressInput && addressInput.value.trim()) {
            params.set('address', addressInput.value.trim());
        }
        if (dateInput && dateInput.value) {
            params.set('date', dateInput.value);
        }
        if (currencySelect && currencySelect.value) {
            params.set('currency', currencySelect.value);
        }
        if (format) {
            params.set('format', format);
        }
        return params;
    };

    if (currencySelect) {
        const cookieCurrency = readCookie('currency');
        const defaultCurrency = (cookieCurrency || 'usd').toLowerCase();
        const supportedValues = Array.from(currencySelect.options).map(opt => opt.value.toLowerCase());
        if (supportedValues.includes(defaultCurrency)) {
            currencySelect.value = defaultCurrency;
        }
        currencySelect.addEventListener('change', () => {
            try {
                document.cookie =
                    `currency=${currencySelect.value}; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/`;
            } catch (err) {
                // ignore cookie write errors
            }
        });
    }

    const validate = () => {
        if (!form.reportValidity) {
            return !!(addressInput.value.trim() && dateInput.value);
        }
        return form.reportValidity();
    };

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        updateStatus('');

        if (!validate()) {
            updateStatus('Please provide a valid address and start date.', true);
            return;
        }

        const params = buildQuery('csv');
        const url = `/api/v1/transactions.php?${params.toString()}`;

        try {
            downloadBtn.disabled = true;
            downloadBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Preparing...';
            updateStatus('Requesting CSV export…');

            const response = await fetch(url, {
                headers: {
                    Accept: 'text/csv,application/json',
                },
            });

            const contentType = response.headers.get('content-type') || '';
            if (!response.ok) {
                if (contentType.includes('application/json')) {
                    const payload = await response.json();
                    throw new Error(payload.error || 'Failed to download CSV.');
                }
                const text = await response.text();
                throw new Error(text || 'Failed to download CSV.');
            }

            if (contentType.includes('application/json')) {
                const payload = await response.json();
                throw new Error(payload.error || 'Failed to download CSV.');
            }

            const blob = await response.blob();
            const filename = `transactions_${addressInput.value.slice(0, 12)}_${(dateInput.value || '').replace(/-/g, '') || 'export'}.csv`;
            const objectUrl = URL.createObjectURL(blob);

            const anchor = document.createElement('a');
            anchor.href = objectUrl;
            anchor.download = filename;
            document.body.appendChild(anchor);
            anchor.click();
            document.body.removeChild(anchor);
            setTimeout(() => URL.revokeObjectURL(objectUrl), 2000);

            updateStatus('Download started.', false);
        } catch (err) {
            console.error(err);
            updateStatus(err.message || 'Download failed.', true);
        } finally {
            downloadBtn.disabled = false;
            downloadBtn.innerHTML = '<i class="bi bi-download me-1"></i>Download CSV';
        }
    });

    viewRawBtn?.addEventListener('click', () => {
        updateStatus('');

        if (!validate()) {
            updateStatus('Provide a valid address and date to view raw data.', true);
            return;
        }

        const params = buildQuery('json');
        const absoluteUrl = `${window.location.origin}/api/v1/transactions.php?${params.toString()}`;
        window.open(absoluteUrl, '_blank', 'noopener,noreferrer');
        updateStatus('Opened raw data in a new tab.', false);
    });
});
