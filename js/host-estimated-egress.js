/* Monthly totals retain unknown values as gaps. */
function hostEstimatedEgressMonths(rows) {
    if (!rows.length) return [];
    const months = new Map(rows.map(row => [row.month, row]));
    const keys = [...months.keys()].sort();
    const cursor = new Date(`${keys[0]}-01T00:00:00Z`);
    const result = [];
    while (cursor.toISOString().slice(0, 7) <= keys[keys.length - 1]) {
        const month = cursor.toISOString().slice(0, 7);
        result.push(months.get(month) || {
            month, estimated_egress_gb: null
        });
        cursor.setUTCMonth(cursor.getUTCMonth() + 1);
    }
    return result;
}

document.addEventListener('DOMContentLoaded', () => {
    const card = document.getElementById('hostEstimatedEgressCard');
    if (!card) return;
    const status = document.getElementById('hostEstimatedEgressStatus');
    const format = value => value == null ? 'Unavailable' : Number(value).toLocaleString(window.APP_LOCALE, { maximumFractionDigits: 2 });
    let attempted = false;
    async function load() {
        if (attempted) return;
        attempted = true;
        status.textContent = 'Loading estimated egress…';
        try {
            const response = await fetch('/api/v1/host_estimated_egress?public_key=' + encodeURIComponent(card.dataset.publicKey));
            if (!response.ok) throw new Error('Request failed');
            const payload = await response.json();
            if (!Array.isArray(payload.estimated_egress)) throw new Error('Invalid response');
            const rows = hostEstimatedEgressMonths(payload.estimated_egress);
            const hasEstimates = rows.some(row => row.estimated_egress_gb != null);
            status.textContent = hasEstimates ? '' : 'No estimated egress is available for this host.';
            if (!rows.length) return;
            new Chart(document.getElementById('hostEstimatedEgressChart').getContext('2d'), {
                type: 'bar',
                data: {
                    labels: rows.map(row => row.month),
                    datasets: [{
                        label: 'Estimated egress',
                        data: rows.map(row => row.estimated_egress_gb == null ? null : Number(row.estimated_egress_gb)),
                        backgroundColor: '#38bdf8'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        x: { title: { display: true, text: 'Month (UTC)' }, ticks: { color: '#cbd5e1' } },
                        y: { beginAtZero: true, title: { display: true, text: 'Estimated egress (GB)' }, ticks: { color: '#cbd5e1' } }
                    },
                    plugins: {
                        legend: { display: false },
                        tooltip: { callbacks: {
                            label: context => `Estimated egress: ${format(context.raw)} GB`
                        } }
                    }
                }
            });
        } catch (error) {
            status.textContent = 'Estimated egress could not be loaded.';
        }
    }
    if (typeof IntersectionObserver === 'function') {
        const observer = new IntersectionObserver(entries => {
            if (entries.some(entry => entry.isIntersecting)) {
                observer.disconnect();
                load();
            }
        });
        observer.observe(card);
    } else {
        const loadVisible = () => { if (card.getClientRects().length) load(); };
        document.addEventListener('change', loadVisible);
        window.addEventListener('hashchange', loadVisible);
        loadVisible();
    }
});
