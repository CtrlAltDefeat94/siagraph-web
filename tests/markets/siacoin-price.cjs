const assert = require('node:assert/strict');
const { observations, rangeStart, validRate } = require('../../js/siacoin-price.js');
const day = date => Date.parse(date + 'T00:00:00Z');
assert.equal(validRate(null), false);
assert.equal(validRate(0), false);
assert.equal(validRate('bad'), false);
assert.equal(validRate(0.00000123), true);
assert.deepEqual(observations([
    { date: '2026-03-03', eur: '0.00001234' },
    { date: 'bad', eur: 3 },
    { date: '2026-03-01', eur: 0.002 },
    { date: '2026-03-04', eur: null, usd: 0.003 }
], 'eur'), [
    { x: day('2026-03-01'), y: 0.002 },
    { x: day('2026-03-02'), y: null },
    { x: day('2026-03-03'), y: 0.00001234 },
    { x: day('2026-03-04'), y: null }
], 'Missing dates and missing currency values must remain gaps');
assert.equal(rangeStart(day('2024-03-31'), 1), day('2024-02-29'));
assert.equal(rangeStart(day('2025-03-31'), 1), day('2025-02-28'));
assert.equal(rangeStart(day('2024-02-29'), 12), day('2023-02-28'));
assert.deepEqual(observations([{ date: '2026-03-01', usd: 0.002 }], 'cad'), [{ x: day('2026-03-01'), y: null }]);
console.log('Siacoin price observation and calendar-range tests passed.');
