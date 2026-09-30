/* Presentation-only conversions. Exact API values are never mutated. */
(() => {
    const locale = () => (window.APP_LOCALE || 'en-US').replace('_', '-');
    function decimal(value) {
        const match = String(value).match(/^(\d+)(?:\.(\d+))?$/);
        if (!match) return null;
        return { numerator: BigInt(match[1] + (match[2] || '')), denominator: 10n ** BigInt((match[2] || '').length) };
    }
    function scaled(n, d, places = 2) {
        const factor = 10n ** BigInt(places), rounded = (n * factor + d / 2n) / d;
        const whole = (rounded / factor).toLocaleString(locale());
        const fraction = (rounded % factor).toString().padStart(places, '0').replace(/0+$/, '');
        const separator = new Intl.NumberFormat(locale()).formatToParts(1.1).find(p => p.type === 'decimal').value;
        return whole + (fraction ? separator + fraction : '');
    }
    function moneyUnit(units) { return !units?.money || units.money === 'stored units' ? 'hastings' : units.money.toLowerCase(); }
    function durationUnit(units) { return !units?.average_contract_duration || units.average_contract_duration === 'stored units' ? 'blocks' : units.average_contract_duration.toLowerCase(); }
    function durationSeconds(units) { return { blocks: 600n, days: 86400n, hours: 3600n, seconds: 1n }[durationUnit(units)]; }
    function sc(value, units) {
        const v = decimal(value); if (!v) return null;
        const unit = moneyUnit(units);
        if (unit === 'hastings') return { numerator: v.numerator, denominator: v.denominator * 10n ** 24n };
        if (unit === 'sc') return v;
        return null;
    }
    function format(value, type, units = {}) {
        if (value === null || value === undefined) return 'Unavailable';
        const v = decimal(value); if (!v) return 'Unavailable';
        if (type === 'bytes') {
            const labels = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB']; let index = 0, divisor = v.denominator;
            while (v.numerator >= divisor * 1000n && index < labels.length - 1) { divisor *= 1000n; index++; }
            return `${scaled(v.numerator, divisor)} ${labels[index]}`;
        }
        if (type === 'money') {
            const amount = sc(value, units); if (!amount) return `${scaled(v.numerator, v.denominator)} ${units.money}`;
            if (amount.numerator > 0n && amount.numerator * 10000n < amount.denominator) return '<0.0001 SC';
            return `${scaled(amount.numerator, amount.denominator, 4)} SC`;
        }
        if (type === 'duration') {
            const seconds = durationSeconds(units);
            if (!seconds) return `${scaled(v.numerator, v.denominator)} ${units.average_contract_duration}`;
            const n = v.numerator * seconds;
            for (const [size, label] of [[2592000n, 'months'], [86400n, 'days'], [3600n, 'hours'], [60n, 'minutes'], [1n, 'seconds']]) {
                if (n >= size * v.denominator || size === 1n) { const amount = scaled(n, v.denominator * size); return `${amount} ${amount === '1' ? label.slice(0, -1) : label}`; }
            }
        }
        return scaled(v.numerator, v.denominator);
    }
    function exact(value, type, units = {}) {
        if (value === null || value === undefined) return 'Unavailable';
        const suffix = type === 'bytes' ? 'bytes' : type === 'money' ? moneyUnit(units) : type === 'duration' ? durationUnit(units) : '';
        return `${value}${suffix ? ' ' + suffix : ''}`;
    }
    function plot(value, type, units = {}) {
        if (value === null || value === undefined) return null;
        const v = decimal(value); if (!v) return null;
        if (type === 'money') { const amount = sc(value, units); return amount ? Number(amount.numerator) / Number(amount.denominator) : Number(value); }
        if (type === 'duration') { const seconds = durationSeconds(units); return seconds ? Number(v.numerator) / Number(v.denominator) * Number(seconds) / 86400 : Number(value); }
        return Number(value);
    }
    function fiat(value, units, rate, currency) {
        const amount = sc(value, units);
        const exchange = decimal(Number(rate).toFixed(20));
        if (!amount || !exchange || exchange.numerator <= 0n) return 'Unavailable';
        const n = amount.numerator * exchange.numerator, d = amount.denominator * exchange.denominator;
        if (n > 0n && n * 100n < d) return `${currency.toUpperCase()} <0.01`;
        return `${currency.toUpperCase()} ${scaled(n, d)}`;
    }
    window.renterFormat = { format, exact, plot, moneyUnit, durationUnit, durationSeconds, fiat };
})();
