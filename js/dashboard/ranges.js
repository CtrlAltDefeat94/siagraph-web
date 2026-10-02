(function (root) {
    const day = 86400000;
    function validDate(value) {
        return /^\d{4}-\d{2}-\d{2}$/.test(value || '') && Number.isFinite(Date.parse(value))
            && new Date(value).toISOString().slice(0, 10) === value;
    }
    function bounds(rows, selection) {
        if (selection.range === 'custom') return { start: selection.from, end: selection.through };
        if (!rows.length) return null;
        const end = rows[rows.length - 1].date;
        if (selection.range === 'all') return { start: rows[0].date, end };
        const start = new Date(end + 'T00:00:00Z');
        if (selection.range === '30d') start.setUTCDate(start.getUTCDate() - 29);
        else {
            const date = start.getUTCDate();
            start.setUTCDate(1);
            start.setUTCMonth(start.getUTCMonth() - (selection.range === '1y' ? 12 : 3));
            const last = new Date(Date.UTC(start.getUTCFullYear(), start.getUTCMonth() + 1, 0)).getUTCDate();
            start.setUTCDate(Math.min(date, last));
        }
        return { start: start.toISOString().slice(0, 10), end };
    }
    // Monthly observations represent whole calendar buckets, including the latest month.
    function monthlyBounds(rows, selection) {
        const selected = bounds(rows, selection);
        if (!selected) return null;
        let start = selected.start.slice(0, 7) + '-01';
        const endMonth = new Date(selected.end.slice(0, 7) + '-01T00:00:00Z');
        if (selection.range !== 'custom' && selection.range !== 'all') {
            const count = selection.range === '1y' ? 12 : selection.range === '3m' ? 3 : 1;
            const first = new Date(endMonth);
            first.setUTCMonth(first.getUTCMonth() - count + 1);
            start = first.toISOString().slice(0, 10);
        }
        endMonth.setUTCMonth(endMonth.getUTCMonth() + 1);
        endMonth.setUTCDate(0);
        return { start, end: endMonth.toISOString().slice(0, 10) };
    }
    function read(search, defaultRange = '3m') {
        const query = new URLSearchParams(search);
        const range = query.get('range') || defaultRange;
        if (range === 'custom') {
            const from = query.get('from'), through = query.get('through');
            if (validDate(from) && validDate(through) && from <= through) return { range, from, through };
            return { range: defaultRange, error: 'Choose valid dates with From on or before Through.' };
        }
        return { range: ['30d', '3m', '1y', 'all'].includes(range) ? range : defaultRange };
    }
    root.DashboardRanges = { validDate, bounds, monthlyBounds, read, day };
    if (typeof module !== 'undefined') module.exports = root.DashboardRanges;
})(typeof window === 'undefined' ? globalThis : window);
