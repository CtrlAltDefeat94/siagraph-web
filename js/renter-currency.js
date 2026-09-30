/* Match the site currency cookie; use date-specific rates for history. */
(() => {
    const allowed = ['sc', 'eur', 'usd', 'cad', 'gbp', 'btc', 'cny', 'eth', 'jpy', 'rub'];
    const cookie = typeof getCookie === 'function' ? getCookie('currency') : document.cookie.split('; ').find(row => row.startsWith('currency='))?.split('=')[1];
    let selected = allowed.includes(String(cookie || '').toLowerCase()) ? String(cookie).toLowerCase() : 'eur';
    let pending, latest = null;
    const rates = new Map();
    function ready() {
        if (!pending) pending = (async () => {
            const requestedCurrency = selected;
            if (selected === 'sc') return;
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 10000);
            try {
                const [history, current] = await Promise.allSettled([
                    fetch('/api/v1/daily/exchange_rate.php', { cache: 'no-store', signal: controller.signal })
                        .then(response => response.ok ? response.json() : null),
                    fetch('/api/v1/exchange_rate.php', { cache: 'no-store', signal: controller.signal })
                        .then(response => response.ok ? response.json() : null)
                ]);
                if (selected !== requestedCurrency) return;
                const rows = history.status === 'fulfilled' ? history.value : null;
                for (const row of Array.isArray(rows) ? rows : []) {
                    const date = String(row.date || '').slice(0, 10), value = Number(row[selected]);
                    if (!/^\d{4}-\d{2}-\d{2}$/.test(date) || !Number.isFinite(value) || value <= 0) continue;
                    rates.set(date, value);
                }
                const spot = current.status === 'fulfilled' ? Number(current.value?.[selected]) : NaN;
                latest = Number.isFinite(spot) && spot > 0 ? spot : null;
            } catch (_) { /* Render an explicit missing-rate state. */ } finally { clearTimeout(timeout); }
        })();
        return pending;
    }
    function rate(date) {
        if (date) return rates.get(String(date).slice(0, 10)) ?? null;
        const spot = Number(window.globalSpotRates?.[selected]);
        return Number.isFinite(spot) && spot > 0 ? spot : latest;
    }
    function format(value, units, date) {
        if (value === null || value === undefined) return 'Unavailable';
        if (selected === 'sc') return window.renterFormat.format(value, 'money', units);
        const exchange = rate(date);
        if (exchange === null) return date ? `${selected.toUpperCase()} unavailable` : `${window.renterFormat.format(value, 'money', units)} (${selected.toUpperCase()} rate unavailable)`;
        return window.renterFormat.fiat(value, units, exchange, selected);
    }
    function plot(value, units, date) {
        const amount = window.renterFormat.plot(value, 'money', units);
        if (amount === null || selected === 'sc') return amount;
        const exchange = rate(date); return exchange === null ? null : amount * exchange;
    }
    window.renterCurrency = { ready, format, plot, rate, get code() { return selected.toUpperCase(); } };
    document.addEventListener('currencyChange', async event => {
        const next = String(event.detail || '').toLowerCase();
        if (!allowed.includes(next)) return;
        selected = next; pending = null; latest = null; rates.clear();
        await ready();
        document.dispatchEvent(new Event('renter:currencyready'));
    });
})();
