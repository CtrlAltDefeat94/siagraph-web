// Host API adapters retain daily flows, month-end snapshots, and missing observations.
window.DashboardHostSources = {
    requests: new Map(),
    json(url) {
        if (!this.requests.has(url)) {
            const request = window.DashboardSources.json(url).catch(error => { this.requests.delete(url); throw error; });
            this.requests.set(url, request);
        }
        return this.requests.get(url);
    },
    number(value) {
        if (value == null || String(value).trim() === '') return null;
        const n = Number(value);
        return Number.isFinite(n) ? n : null;
    },
    sc(value) {
        const raw = String(value ?? '').trim();
        if (!/^\d+$/.test(raw)) return null;
        const n = BigInt(raw), base = 1000000000000000000000000n;
        const result = Number(n / base) + Number(n % base) / 1e24;
        return Number.isFinite(result) ? result : null;
    },
    async rates() {
        const [history, current] = await Promise.allSettled([
            this.json('/api/v1/daily/exchange_rate'), this.json('/api/v1/daily/compare_metrics'),
        ]);
        return {
            history: history.status === 'fulfilled' && Array.isArray(history.value) ? history.value.slice().sort((a,b) => String(a.date).localeCompare(String(b.date))) : [],
            current: current.status === 'fulfilled' ? current.value?.actual?.coin_price || {} : {},
        };
    },
    money(value, date, rates, mode = 'daily') {
        const result = { sc: value };
        for (const currency of ['eur', 'usd', 'cad', 'gbp']) {
            let rate = null;
            if (mode !== 'current') for (const row of rates.history) {
                const day = String(row.date).slice(0, 10);
                const matches = mode === 'monthly' ? day.slice(0, 7) === date.slice(0, 7) : day <= date;
                if (matches && this.number(row[currency]) > 0) rate = Number(row[currency]);
            }
            if (!(rate > 0)) rate = this.number(rates.current[currency]);
            result[currency] = value !== null && rate > 0 ? value * rate : null;
        }
        return result;
    },
    monthly(rows, rates = null) {
        const dailyRates = new Map((rates?.history || []).map(row => [String(row.date).slice(0, 10), row]));
        const months = new Map();
        for (const row of rows) {
            const date = row.date.slice(0, 7) + '-01';
            if (!months.has(date)) months.set(date, { date });
            const bucket = months.get(date);
            for (const [field, key, money] of [
                ['revenue', 'earned', true], ['burned_funds', 'burned', true],
                ['successful_contracts', 'successful', false], ['renewed_contracts', 'renewed', false], ['failed_contracts', 'failed', false],
            ]) {
                const value = money ? this.sc(row[field]) : this.number(row[field]);
                if (value !== null) bucket[key] = (bucket[key] ?? 0) + value;
            }
            if (rates) {
                const value = this.sc(row.revenue);
                bucket.earnedMoney ??= {sc: null, eur: null, usd: null, cad: null, gbp: null};
                if (value !== null) {
                    const first = bucket.earnedMoney.sc === null;
                    bucket.earnedMoney.sc = (bucket.earnedMoney.sc ?? 0) + value;
                    for (const currency of ['eur', 'usd', 'cad', 'gbp']) {
                        const rate = this.number(dailyRates.get(row.date)?.[currency]);
                        const converted = value === 0 ? 0 : rate > 0 ? value * rate : null;
                        const previous = bucket.earnedMoney[currency];
                        bucket.earnedMoney[currency] = converted === null || (!first && previous === null)
                            ? null : (previous ?? 0) + converted;
                    }
                }
            }
            for (const [field, key, money] of [['locked_collateral', 'locked', true], ['risked_collateral', 'risked', true], ['active_contracts', 'active', false]]) {
                const value = money ? this.sc(row[field]) : this.number(row[field]);
                if (value !== null) bucket[key] = value;
            }
        }
        return [...months.values()];
    },
    fillMonths(rows) {
        const map = new Map(rows.map(row => [row.date, row]));
        const dates = [...map.keys()].sort();
        if (!dates.length) return [];
        const cursor = new Date(dates[0] + 'T00:00:00Z');
        while (cursor.toISOString().slice(0,10) <= dates.at(-1)) {
            const date = cursor.toISOString().slice(0,10);
            if (!map.has(date)) map.set(date, {date});
            cursor.setUTCMonth(cursor.getUTCMonth() + 1);
        }
        return [...map.values()].sort((a,b)=>a.date.localeCompare(b.date));
    },
    async load(source) {
        if (source.kind === 'host-egress') {
            const data = await this.json(source.url);
            if (!Array.isArray(data.estimated_egress)) throw new Error('Invalid egress response');
            const rows = data.estimated_egress.filter(row => window.DashboardRanges.validDate(row.month + '-01')).map(row => ({date: row.month + '-01', egress: this.number(row.estimated_egress_gb)}));
            return {rows: this.fillMonths(rows), warnings: []};
        }
        // History and forecast failures are independent; earned data remains usable.
        const [history, scheduled, rates] = await Promise.allSettled([
            this.json(source.url),
            source.scheduled ? this.json(source.scheduled) : Promise.resolve(null),
            source.kind === 'host-contracts' ? Promise.resolve(null) : this.rates(),
        ]);
        const warnings = [];
        const payload = history.status === 'fulfilled' ? history.value : null;
        let daily = Array.isArray(payload?.hosts) ? payload.hosts : null;
        const historyAvailable = daily !== null;
        if (!historyAvailable) this.requests.delete(source.url);
        if (!daily && !source.scheduled) throw new Error('Host history unavailable');
        if (!daily) warnings.push('Host history could not be loaded.');
        daily = (daily || []).filter(row => row && window.DashboardRanges.validDate(String(row.date).slice(0,10)))
            .map(row=>({...row,date:String(row.date).slice(0,10)})).sort((a,b)=>a.date.localeCompare(b.date));
        const rateData = rates.status === 'fulfilled' && rates.value ? rates.value : {history:[],current:{}};
        if (source.kind === 'host-daily') {
            return {rows: daily.map(row => {
                const result = {...row};
                for (const [field, multiplier] of [['storage_price',4320],['upload_price',1],['download_price',1]]) {
                    const value = this.number(row[field]?.sc ?? row[field]);
                    result[field] = this.money(value === null ? null : value / 1e12 * multiplier, row.date, rateData);
                }
                return result;
            }), warnings};
        }
        let rows = this.monthly(daily, source.kind === 'host-contracts' ? null : rateData);
        if (source.kind === 'host-contracts') return {rows:this.fillMonths(rows),warnings};
        rows = rows.map(row => ({...row,
            earned:row.earnedMoney,
            burned:this.money(row.burned == null ? null : -Math.abs(row.burned),row.date,rateData,'monthly'),
            locked:this.money(row.locked ?? null,row.date,rateData,'monthly'),
            risked:this.money(row.risked ?? null,row.date,rateData,'monthly'),
        }));
        const anchorDate = rows.at(-1)?.date;
        if (source.scheduled) {
            const forecast = scheduled.status === 'fulfilled' && Array.isArray(scheduled.value?.scheduled_revenue) ? scheduled.value.scheduled_revenue : null;
            if (!forecast) {
                this.requests.delete(source.scheduled);
                warnings.push('Anticipated revenue could not be loaded.');
            }
            else if (!forecast.length) warnings.push('No anticipated revenue for unresolved contracts.');
            if (!historyAvailable && !forecast) throw new Error('Revenue history unavailable');
            for (const row of forecast || []) {
                const date = String(row.unlock_date).slice(0,7) + '-01';
                const value = this.number(row.revenue_sc);
                if (!window.DashboardRanges.validDate(date) || value === null) continue;
                let point = rows.find(item=>item.date===date);
                if (!point) { point={date}; rows.push(point); }
                const sc = (point.anticipated?.sc ?? 0) + value;
                point.anticipated = this.money(sc,date,rateData,'current');
            }
        }
        return {rows:this.fillMonths(rows),anchorDate,warnings,retryable:warnings.some(message=>message.includes('could not be loaded'))};
    },
};
