// A source owns its response shape and availability; a failed source cannot hide its peers.
window.DashboardSources = {
    async json(url) {
        const response = await fetch(url);
        if (!response.ok) throw new Error('History request failed');
        return response.json();
    },
    async load(source) {
        if (source.kind?.startsWith('host-')) return window.DashboardHostSources.load(source);
        if (source.kind === 'storage-ath') {
            const data = (await this.json(source.url))?.utilized_storage;
            if (!data || typeof data !== 'object') throw new Error('Invalid ATH response');
            return { rows: data.latest_date ? [{ ...data, date: data.latest_date }] : [], warnings: [] };
        }
        if (source.kind === 'storage-forecast') return this.storageForecast(source);
        if (source.kind === 'network-revenue') return this.revenue(source);
        let rows = await this.json(typeof source === 'string' ? source : source.url);
        if (source.kind === 'record') rows = rows && typeof rows === 'object' && !Array.isArray(rows) ? [rows] : null;
        if (!Array.isArray(rows)) throw new Error('Invalid history response');
        return { rows, warnings: [] };
    },
    async storageForecast(source) {
        const history = await this.json(source.url);
        if (!Array.isArray(history)) throw new Error('Invalid storage history');
        const valid = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value)) && Number(value) >= 0;
        const rows = history.filter(row => row && window.DashboardRanges.validDate(String(row.date).slice(0, 10)))
            .map(row => ({ ...row, date: String(row.date).slice(0, 7) + '-01' })).sort((a, b) => a.date.localeCompare(b.date)).slice(-5);
        const last = rows.at(-1), first = rows[0];
        if (rows.length < 2) return { rows, warnings: ['At least two monthly observations are needed for a projection.'] };
        const monthIndex = date => Number(date.slice(0, 4)) * 12 + Number(date.slice(5, 7));
        const months = monthIndex(last.date) - monthIndex(first.date);
        if (months <= 0) return { rows, warnings: ['At least two distinct months are needed for a projection.'] };
        const observations = [...rows];
        const warnings = [];
        for (const [field, suffix] of [['utilized_storage', 'utilized'], ['total_storage', 'total']]) {
            if (!observations.every(row => valid(row[field]))) { warnings.push('Insufficient observations to project ' + field.replaceAll('_', ' ') + '.'); continue; }
            const start = Number(first[field]), end = Number(last[field]);
            const step = (end - start) / months;
            const factor = start > 0 ? (end / start) ** (1 / months) : null;
            last['predicted_' + suffix] = end;
            last['exponential_' + suffix] = end > 0 ? end : null;
            for (let i = 1; i <= 24; i++) {
                const date = new Date(last.date + 'T00:00:00Z'); date.setUTCMonth(date.getUTCMonth() + i);
                const key = date.toISOString().slice(0, 10);
                let row = rows.find(item => item.date === key);
                if (!row) { row = { date: key }; rows.push(row); }
                row['predicted_' + suffix] = Math.max(0, end + step * i);
                const exponential = factor === null ? null : end * factor ** i;
                row['exponential_' + suffix] = Number.isFinite(exponential) && exponential > 0 ? exponential : null;
            }
        }
        return { rows, anchorDate: last.date, warnings };
    },
    async revenue(source) {
        const result = await Promise.allSettled([
            this.json(source.history), this.json(source.forecast), this.json(source.rates),
        ]);
        const warnings = [], months = new Map();
        const history = result[0].status === 'fulfilled' && Array.isArray(result[0].value) ? result[0].value : null;
        const forecast = result[1].status === 'fulfilled' && Array.isArray(result[1].value?.scheduled_revenue) ? result[1].value : null;
        if (!history) warnings.push('Earned revenue could not be loaded.');
        if (!forecast) warnings.push('Anticipated revenue could not be loaded.');
        if (!history && !forecast) throw new Error(warnings.join(' '));
        for (const row of history || []) {
            const month = String(row.date || '').slice(0, 7);
            if (/^\d{4}-(0[1-9]|1[0-2])$/.test(month)) months.set(month, { ...row, date: month + '-01' });
        }
        const anchorDate = [...months.keys()].sort().at(-1);
        const rates = result[2].status === 'fulfilled' ? result[2].value?.actual?.coin_price || {} : {};
        // Forecast API's as_of establishes what is unresolved; historical selection does not reclassify payouts.
        const asOfMonth = String(forecast?.as_of || '').slice(0, 7);
        for (const row of forecast?.scheduled_revenue || []) {
            const month = String(row.unlock_date || '').slice(0, 7);
            if (!/^\d{4}-(0[1-9]|1[0-2])$/.test(month) || (asOfMonth && month < asOfMonth)) continue;
            if (row.revenue_sc == null || row.revenue_sc === '' || !Number.isFinite(Number(row.revenue_sc))) continue;
            const entry = months.get(month) || { date: month + '-01' };
            const sc = (entry.anticipated_revenue?.sc || 0) + Number(row.revenue_sc);
            entry.anticipated_revenue = { sc, eur: rates.eur > 0 ? sc * rates.eur : null, usd: rates.usd > 0 ? sc * rates.usd : null };
            months.set(month, entry);
        }
        if (forecast && !forecast.scheduled_revenue.length) warnings.push('No anticipated revenue for unresolved contracts.');
        const keys = [...months.keys()].sort();
        // Preserve missing buckets as gaps, never as earned or anticipated zero revenue.
        if (keys.length) {
            const cursor = new Date(keys[0] + '-01T00:00:00Z');
            while (cursor.toISOString().slice(0, 7) <= keys.at(-1)) {
                const month = cursor.toISOString().slice(0, 7);
                if (!months.has(month)) months.set(month, { date: month + '-01' });
                cursor.setUTCMonth(cursor.getUTCMonth() + 1);
            }
        }
        return { rows: [...months.values()], anchorDate: anchorDate ? anchorDate + '-01' : null, warnings };
    },
};
