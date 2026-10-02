const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const now = new Date();
const month = offset => new Date(Date.UTC(now.getUTCFullYear(), now.getUTCMonth() + offset, 1)).toISOString().slice(0, 10);
async function run({ failure = false, empty = false, rateFailure = false, historyFailure = false } = {}) {
    const status = {};
    let forecastCalls = 0;
    const context = vm.createContext({
        window: {}, console, Date,
        getComputedStyle: () => ({ getPropertyValue: () => '#fff' }),
        Chart: function (ctx, config) { this.data = config.data; this.options = config.options; this.update = () => {}; },
        document: { documentElement: {}, getElementById: id => id === 'networkRevenueStatus' ? status : { getContext: () => ({}) } },
        formatSC: value => value + ' SC',
        fetchWithCache: async url => {
            if (url === '/history') {
                if (historyFailure) throw Error('history');
                return [{ date: month(-1), contract_revenue: 10 }, { date: month(0), contract_revenue: 3 }];
            }
            if (rateFailure) throw Error('rates');
            return { actual: { coin_price: { eur: 2, usd: 4 } } };
        },
        fetch: async url => {
            forecastCalls++;
            assert.equal(url, '/api/v1/network_anticipated_revenue.php');
            return { ok: !failure, json: async () => ({ scheduled_revenue: empty ? [] : [
                { unlock_date: month(-1), revenue_sc: '999' },
                { unlock_date: month(0), revenue_sc: '5' },
                { unlock_date: month(2), revenue_sc: '7.5' }
            ] }) };
        }
    });
    vm.runInContext(fs.readFileSync('js/network-revenue.js', 'utf8') + '\n' + fs.readFileSync('js/graph-renderer.js', 'utf8') + `
        chart = window.createNetworkRevenueChart({ canvasId: 'test', jsonUrl: '/history',
            datasets: [{ key: 'contract_revenue', borderColor: 'orange' },
                { key: 'anticipated_revenue_sc', borderColor: 'yellow', fiatUnit: '__CURRENCY__', scUnit: 'SC', fiatUnitDivisor: 1, scUnitDivisor: 1 }],
            dateKey: 'date', charttype: 'bar', currency: 'eur', useFiat: true, defaultrangeinmonths: 6 });
    `, context);
    await new Promise(setImmediate);
    const chart = context.chart;
    if (failure) {
        assert.equal(chart.monthlyData.length, 2);
        assert.match(status.textContent, /Anticipated revenue could not/);
    } else if (empty) {
        assert.equal(chart.monthlyData.length, 2);
        assert.match(status.textContent, /No anticipated revenue/);
    } else {
        const current = chart.monthlyData.findIndex(row => row.date === month(0));
        assert.equal(chart.datasetsConfig[1].data[current], rateFailure ? null : 10);
        assert.equal(chart.datasetsConfig[0].data[current], historyFailure ? null : 3);
        assert.equal(chart.monthlyData.at(-2).date, month(1), 'Calendar gap preserved');
        assert.equal(chart.datasetsConfig[0].data.at(-1), null, 'Future earned revenue remains null');
        assert.equal(chart.endDateIndex, chart.monthlyData.length - 1);
        if (rateFailure) assert.match(status.textContent, /exchange rate unavailable/);
        if (historyFailure) assert.match(status.textContent, /Earned revenue could not/);
        const tooltip = chart.chart.options.plugins.tooltip.callbacks.label({datasetIndex: 1, dataIndex: current - chart.startDateIndex, dataset: chart.datasetsConfig[1], parsed: { y: 10 }});
        assert.match(tooltip, /5(?:\.00)? SC/);
        chart.setCurrency('usd');
        assert.equal(chart.datasetsConfig[1].data.at(-1), rateFailure ? null : 30);
        chart.setCurrency('sc');
        assert.equal(chart.datasetsConfig[1].data.at(-1), 7.5, 'SC is not divided by Hastings again');
        assert.doesNotMatch(status.textContent, /exchange rate unavailable/);
        assert.equal(forecastCalls, 1, 'Currency changes reuse forecast');
    }
}
(async () => {
    await run();
    await run({ failure: true });
    await run({ empty: true });
    await run({ rateFailure: true });
    await run({ historyFailure: true });
    console.log('Network anticipated revenue chart checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
