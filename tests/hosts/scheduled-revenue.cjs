const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const nodes = {};
let visible, finish, requests = 0;
const context = vm.createContext({
    window: { APP_LOCALE: 'en-US' },
    document: { getElementById: id => nodes[id] ||= { hidden: false, addEventListener() {}, getContext() { return id; } } },
    IntersectionObserver: function (callback) { visible = callback; this.observe = () => {}; this.disconnect = () => {}; },
    Chart: function (ctx, config) { this.config = config; this.destroy = () => {}; },
    fetch: url => { requests++; assert.match(url, /public_key=ed25519%3Aabc/); return new Promise(resolve => { finish = resolve; }); }
});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js/host-scheduled-revenue.js'), 'utf8'), context);
(async () => {
    vm.runInContext("loaded = null; view = new HostScheduledRevenueLoader('ed25519:abc', rows => { loaded = rows; })", context);
    const view = context.view;
    assert.equal(requests, 0, 'No request before the card is visible');
    visible([{ isIntersecting: true }]);
    assert.equal(requests, 1);
    await view.load();
    assert.equal(requests, 1, 'Deduplicate while pending');
    assert.match(nodes.hostScheduledRevenueStatus.textContent, /Loading/);
    finish({ ok: true, json: async () => ({ scheduled_revenue: [{ unlock_date: '2026-10-01', revenue_sc: '2.5' }] }) });
    await new Promise(setImmediate);
    assert.equal(context.loaded[0].revenue_sc, '2.5');
    assert.equal(requests, 1);
    await view.load();
    assert.equal(requests, 1, 'Successful data is never refreshed');
    context.fetch = async () => { requests++; return { ok: false }; };
    vm.runInContext("failedView = new HostScheduledRevenueLoader('ed25519:abc', () => {})", context);
    await context.failedView.load();
    assert.match(nodes.hostScheduledRevenueStatus.textContent, /could not be loaded/);
    const failedRequests = requests;
    await context.failedView.load();
    visible([{ isIntersecting: true }]);
    assert.equal(requests, failedRequests, 'Failed data is not retried by revisiting the card');
    console.log('Anticipated revenue lazy loading checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
