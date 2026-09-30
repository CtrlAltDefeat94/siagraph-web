const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../host.php'), 'utf8');
let calls = 0;
const context = vm.createContext({
    window: { hostHistoricalRates: { '2025-02-08': { eur: 0.0036 } } },
    exchangeRate: null,
    console,
    fetchWithCache: async url => {
        assert.equal(url, '/api/v1/daily/compare_metrics');
        calls++;
        return { actual: { coin_price: { eur: 0.0006, usd: 0.0007 } } };
    }
});
vm.runInContext(source.slice(source.indexOf('   async function fetchCurrentRate('), source.indexOf('   // Call the initMap')), context);
(async () => {
    await context.fetchCurrentRate('eur');
    assert.equal(context.window.hostExchangeRate, 0.0006, 'Current price must override historical price even when history exists');
    await context.fetchCurrentRate('usd');
    assert.equal(context.window.hostExchangeRate, 0.0007);
    await context.fetchCurrentRate('sc');
    assert.equal(context.window.hostExchangeRate, 1);
    assert.equal(calls, 2);
    context.fetchWithCache = async () => ({ error: 'Unavailable' });
    await context.fetchCurrentRate('eur');
    assert.equal(context.window.hostExchangeRate, null, 'Do not silently substitute a stale historical price');
    console.log('Host current exchange-rate checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
