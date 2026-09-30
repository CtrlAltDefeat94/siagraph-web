const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
let ready, visible, chart, requests = 0;
const node = () => ({ children: [], appendChild(child) { this.children.push(child); }, replaceChildren() { this.children = []; }, getContext() { return this; } });
const nodes = Object.fromEntries(['hostEstimatedEgressCard', 'hostEstimatedEgressStatus', 'hostEstimatedEgressChart'].map(id => [id, node()]));
nodes.hostEstimatedEgressCard.dataset = { publicKey: 'ed25519:abc' };
const rows = [
    { month: '2025-07', estimated_egress_gb: null, days_with_estimates: 0, days_without_estimates: 31, omitted_intervals: null },
    { month: '2025-09', estimated_egress_gb: '0', days_with_estimates: 1, days_without_estimates: 29, omitted_intervals: '2' },
    { month: '2025-10', estimated_egress_gb: '1250', days_with_estimates: 20, days_without_estimates: 11, omitted_intervals: '3' }
];
const context = vm.createContext({
    window: { APP_LOCALE: 'en-US' },
    document: { addEventListener: (event, callback) => { ready = callback; }, getElementById: id => nodes[id], createElement: node },
    IntersectionObserver: function (callback) { visible = callback; this.observe = () => {}; this.disconnect = () => {}; },
    Chart: function (canvas, config) { chart = config; },
    fetch: async url => { requests++; assert.match(url, /public_key=ed25519%3Aabc/); return { ok: true, json: async () => ({ estimated_egress: rows }) }; }
});
vm.runInContext(fs.readFileSync(path.join(__dirname, '../../js/host-estimated-egress.js'), 'utf8'), context);
(async () => {
    ready();
    assert.equal(requests, 0);
    visible([{ isIntersecting: true }]);
    await new Promise(setImmediate);
    assert.deepEqual(Array.from(chart.data.labels), ['2025-07', '2025-08', '2025-09', '2025-10']);
    assert.deepEqual(Array.from(chart.data.datasets[0].data), [null, null, 0, 1250]);
    assert.equal(chart.options.plugins.tooltip.callbacks.label({ raw: 1250 }), 'Estimated egress: 1,250 GB');
    assert.equal(chart.options.plugins.tooltip.callbacks.afterLabel, undefined);
    visible([{ isIntersecting: true }]);
    assert.equal(requests, 1);
    rows.length = 0;
    ready(); visible([{ isIntersecting: true }]);
    await new Promise(setImmediate);
    assert.match(nodes.hostEstimatedEgressStatus.textContent, /No estimated egress/);
    context.fetch = async () => ({ ok: false });
    ready(); visible([{ isIntersecting: true }]);
    await new Promise(setImmediate);
    assert.match(nodes.hostEstimatedEgressStatus.textContent, /could not be loaded/);
    console.log('Estimated egress gaps, zero totals, egress-only tooltips, lazy loading, empty and error checks passed.');
})().catch(error => { console.error(error); process.exitCode = 1; });
