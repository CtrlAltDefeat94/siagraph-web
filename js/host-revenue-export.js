document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('host-revenue-export-form');
    if (!form) return;
    const key = form.elements.host_public_key;
    const from = form.elements.from;
    const to = form.elements.to;
    const currency = form.elements.currency;
    const download = document.getElementById('revenue-download-btn');
    const raw = document.getElementById('revenue-raw-btn');
    const status = document.getElementById('revenue-status');
    const setStatus = (message, error = false) => {
        status.textContent = message;
        status.classList.toggle('text-danger', error);
    };
    const checkPeriod = () => {
        to.setCustomValidity(from.value && to.value && from.value > to.value ? 'The end date must be on or after the start date.' : '');
    };
    from.addEventListener('input', checkPeriod);
    to.addEventListener('input', checkPeriod);
    key.addEventListener('change', () => { key.value = key.value.trim().toLowerCase(); });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        checkPeriod();
        if (!form.reportValidity()) return;
        const format = event.submitter === raw ? 'json' : 'csv';
        const params = new URLSearchParams({
            host_public_key: key.value.trim().toLowerCase(), from: from.value, to: to.value,
            currency: currency.value, format,
        });
        const url = `${form.getAttribute('action')}?${params}`;
        if (format === 'json') {
            window.open(url, '_blank', 'noopener,noreferrer');
            setStatus('Opened raw data in a new tab.');
            return;
        }
        const label = download.innerHTML;
        download.disabled = raw.disabled = true;
        download.textContent = 'Preparing…';
        form.setAttribute('aria-busy', 'true');
        setStatus('Preparing your revenue export…');
        try {
            const response = await fetch(url, { headers: { Accept: 'text/csv,application/json' } });
            const contentType = response.headers.get('content-type') || '';
            if (!response.ok || !contentType.includes('text/csv')) {
                let message = 'Unable to download the export. Please retry.';
                if (contentType.includes('application/json')) {
                    const payload = await response.json();
                    message = payload.errors?.[0]?.message || message;
                }
                throw new Error(message);
            }
            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const anchor = document.createElement('a');
            anchor.href = objectUrl;
            anchor.download = `host-revenue_${params.get('host_public_key').replace(/^ed25519:/, '')}_${params.get('from')}_${params.get('to')}_${params.get('currency')}.csv`;
            document.body.appendChild(anchor);
            anchor.click();
            anchor.remove();
            setTimeout(() => URL.revokeObjectURL(objectUrl), 2000);
            setStatus('Download started.');
        } catch (error) {
            setStatus(error.message || 'Download failed. Please retry.', true);
        } finally {
            download.disabled = raw.disabled = false;
            download.innerHTML = label;
            form.removeAttribute('aria-busy');
        }
    });
});
