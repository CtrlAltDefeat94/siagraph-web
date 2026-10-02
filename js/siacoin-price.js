/* Daily rates are averages of recorded hourly snapshots, never interpolated prices or OHLC data. */
(() => {
    const DAY = 86400000;
    const currencies = ['eur', 'usd', 'cad', 'gbp'];
    const validRate = value => value !== null && value !== '' && Number.isFinite(Number(value)) && Number(value) > 0;
    function observations(rows, currency) {
        const byDate = new Map();
        for (const row of rows) {
            const time = Date.parse(row.date);
            if (Number.isFinite(time)) byDate.set(time, { x: time, y: validRate(row[currency]) ? Number(row[currency]) : null });
        }
        const sorted = [...byDate.values()].sort((a, b) => a.x - b.x);
        const result = [];
        sorted.forEach((point, index) => {
            if (index && point.x - sorted[index - 1].x > DAY) result.push({ x: sorted[index - 1].x + DAY, y: null });
            result.push(point);
        });
        return result;
    }
    function rangeStart(end, months) {
        const date = new Date(end);
        const day = date.getUTCDate();
        date.setUTCDate(1);
        date.setUTCMonth(date.getUTCMonth() - months);
        const lastDay = new Date(Date.UTC(date.getUTCFullYear(), date.getUTCMonth() + 1, 0)).getUTCDate();
        date.setUTCDate(Math.min(day, lastDay));
        return date.getTime();
    }
    if (typeof module !== 'undefined' && module.exports) module.exports = { observations, rangeStart, validRate };
    if (typeof document === 'undefined') return;

    document.addEventListener('DOMContentLoaded', () => {
        const rateBoxes = [...document.querySelectorAll('[data-sc-rate]')];
        if (!rateBoxes.length) return;
        const cookie = document.cookie.split('; ').find(item => item.startsWith('currency='))?.split('=')[1];
        let currency = currencies.includes(cookie) ? cookie : rateBoxes[0].dataset.currency;
        if (!currencies.includes(currency)) currency = 'eur';
        let latest = null;
        let latestFailed = false;
        const selector = document.getElementById('price-currency');
        const money = value => new Intl.NumberFormat(window.APP_LOCALE || 'en-US', {
            style: 'currency', currency: currency.toUpperCase(), currencyDisplay: 'code',
            minimumFractionDigits: 6, maximumFractionDigits: 8
        }).format(value);
        const isoDay = time => new Date(time).toISOString().slice(0, 10);
        function showLatest() {
            rateBoxes.forEach(box => {
                const value = box.querySelector('[data-sc-value]');
                const updated = box.querySelector('[data-sc-updated]');
                value.textContent = latest && validRate(latest[currency]) ? `1 SC = ${money(Number(latest[currency]))}` : latestFailed || latest ? 'Rate unavailable' : 'Loading…';
                const date = latest && Date.parse(latest.date);
                updated.textContent = Number.isFinite(date) ? `Latest recorded: ${new Date(date).toISOString().replace('T', ' ').replace('.000Z', ' UTC')}` : 'Observation time unavailable';
            });
        }
        fetch('/api/v1/exchange_rate', { headers: { Accept: 'application/json' } })
            .then(response => { if (!response.ok) throw new Error('Unavailable'); return response.json(); })
            .then(data => { latest = data; showLatest(); })
            .catch(() => { latestFailed = true; showLatest(); });

        const canvas = document.getElementById('price-chart');
        let rows = [], chart = null, preset = '12';
        const startInput = document.getElementById('price-start');
        const endInput = document.getElementById('price-end');
        const status = document.getElementById('price-status');
        const retry = document.getElementById('price-retry');
        function draw() {
            if (!canvas || !rows.length) return;
            const points = observations(rows, currency);
            const first = points[0].x, last = points[points.length - 1].x;
            if (preset) {
                endInput.value = isoDay(last);
                startInput.value = isoDay(preset === 'all' ? first : Math.max(first, rangeStart(last, Number(preset))));
            }
            const start = Date.parse(startInput.value + 'T00:00:00Z');
            const end = Date.parse(endInput.value + 'T00:00:00Z');
            if (!Number.isFinite(start) || !Number.isFinite(end) || start > end) {
                status.textContent = 'Choose a valid start date on or before the end date.';
                return;
            }
            const visible = points.filter(point => point.x >= start && point.x <= end);
            const values = visible.filter(point => point.y !== null);
            document.querySelectorAll('[data-price-range]').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.priceRange === preset)));
            document.getElementById('price-point').textContent = 'Hover over or tap a point for its exact date and price.';
            status.textContent = values.length ? `${isoDay(start)} – ${isoDay(end)} · ${values.length} daily averages` : 'No exchange-rate observations available for this currency and date range.';
            if (values.length >= 2) {
                const change = (values[values.length - 1].y / values[0].y - 1) * 100;
                status.textContent += ` · Change between available endpoints: ${change > 0 ? '+' : ''}${change.toFixed(2)}%`;
            }
            const tbody = document.getElementById('price-rows');
            tbody.replaceChildren();
            const fragment = document.createDocumentFragment();
            values.forEach(point => {
                const tr = document.createElement('tr');
                for (const text of [isoDay(point.x), money(point.y)]) {
                    const td = document.createElement('td'); td.textContent = text; tr.append(td);
                }
                fragment.append(tr);
            });
            tbody.append(fragment);
            if (!window.Chart) { status.textContent += ' · Chart unavailable; daily prices are available in the table below.'; return; }
            if (chart) chart.destroy();
            chart = new Chart(canvas, {
                type: 'line',
                data: { datasets: [{ label: `${currency.toUpperCase()} per SC`, data: visible, borderColor: '#ed8b76', borderWidth: 2, pointRadius: values.length === 1 ? 3 : 0, pointHitRadius: 14, spanGaps: false, tension: 0 }] },
                options: {
                    responsive: true, maintainAspectRatio: false, animation: false,
                    interaction: { mode: 'nearest', intersect: false },
                    scales: {
                        x: { type: 'linear', min: start, max: end === start ? end + DAY : end, ticks: { maxTicksLimit: 6, callback: value => isoDay(value), color: '#a9b7c9' }, grid: { color: 'rgba(160,180,200,.08)' } },
                        y: { title: { display: true, text: `${currency.toUpperCase()} per SC`, color: '#a9b7c9' }, ticks: { color: '#a9b7c9', callback: value => money(value) }, grid: { color: 'rgba(160,180,200,.08)' } }
                    },
                    plugins: { legend: { display: false }, tooltip: { callbacks: {
                        title: items => items.length ? `${isoDay(items[0].parsed.x)} UTC · daily average` : '',
                        label: item => {
                            const text = `${isoDay(item.parsed.x)} · 1 SC = ${money(item.parsed.y)}`;
                            document.getElementById('price-point').textContent = text;
                            return money(item.parsed.y);
                        }
                    } } }
                }
            });
        }
        async function loadHistory() {
            retry.hidden = true; status.textContent = 'Loading history…';
            try {
                const response = await fetch('/api/v1/daily/exchange_rate', { headers: { Accept: 'application/json' } });
                if (!response.ok) throw new Error('Unavailable');
                const data = await response.json();
                if (!Array.isArray(data)) throw new Error('Invalid history');
                rows = data.filter(row => row && typeof row === 'object' && Number.isFinite(Date.parse(row.date)));
                if (!rows.length) { status.textContent = 'No historical exchange rates available.'; return; }
                draw();
            } catch (_) { status.textContent = 'Exchange-rate history is temporarily unavailable.'; retry.hidden = false; }
        }
        if (canvas) {
            loadHistory();
            retry.addEventListener('click', loadHistory);
            document.querySelectorAll('[data-price-range]').forEach(button => button.addEventListener('click', () => { preset = button.dataset.priceRange; draw(); }));
            document.getElementById('price-dates').addEventListener('submit', event => { event.preventDefault(); preset = null; draw(); });
            selector.value = currency;
            selector.addEventListener('change', () => window.setCurrency(selector.value));
        }
        document.addEventListener('currencyChange', event => {
            currency = currencies.includes(event.detail) ? event.detail : 'eur';
            if (selector) selector.value = currency;
            const footer = document.getElementById('currency-select');
            if (footer) footer.value = currency;
            showLatest(); draw();
        });
    });
})();
