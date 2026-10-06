const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const root = path.resolve(__dirname, '../..');
const host = fs.readFileSync(path.join(root, 'host', 'host.php'), 'utf8');
const tabListeners = {}, windowListeners = {};
const radios = ['overview', 'contracts', 'economics', 'benchmarks', 'charts'].map(name => ({
    id: `host-tab-${name}-radio`, checked: false,
    getAttribute: attribute => attribute === 'for' ? `host-tab-${name}-radio` : null,
    setAttribute: () => {},
    addEventListener: (event, callback) => { tabListeners[name] = callback; }
}));
const tabs = ['overview', 'contracts', 'economics', 'benchmarks', 'history'].map(name => ({
    getAttribute: attribute => attribute === 'for' ? `host-tab-${name === 'history' ? 'charts' : name}-radio` : null,
    setAttribute: () => {},
    addEventListener: () => {},
    focus: () => {}
}));
const routing = vm.createContext({
    window: {
        location: { hash: '#charts', pathname: '/host', search: '?id=10' },
        history: { replaceState: (_, __, url) => { routing.lastUrl = url; } },
        addEventListener: (event, callback) => { windowListeners[event] = callback; }
    },
    document: { querySelectorAll: selector => selector.includes('.host-tab[role=') ? tabs : radios, getElementById: id => radios.find(r => r.id === id) }
});
vm.runInContext(host.slice(host.indexOf('   function setupHostTabHashRouting('), host.indexOf('   function setupBenchmarksTab(')), routing);
routing.setupHostTabHashRouting();
assert.equal(radios[4].checked, true);
tabListeners.charts();
assert.equal(routing.lastUrl, '/host?id=10#history');
radios[4].checked = false; routing.window.location.hash = '#history'; windowListeners.hashchange();
assert.equal(radios[4].checked, true);
console.log('History tab aliases and canonical navigation passed.');


// Chart rendering now lives in the shared framework; adapter and browser checks
// cover first-valid points, native values, and anticipated revenue.
require('./dashboard-sources.cjs');
