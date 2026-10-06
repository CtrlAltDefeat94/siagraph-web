document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-dashboard]').forEach(root => {
        const config = JSON.parse(root.querySelector('[data-dashboard-config]').textContent);
        if (config.persistRange === false) {
            const url = new URL(location.href);
            ['range', 'from', 'through'].forEach(key => url.searchParams.delete(config.id ? config.id + '_' + key : key));
            if (url.href !== location.href) history.replaceState(null, '', url);
        }
        let started = false;
        const start = () => {
            if (started || !root.getClientRects().length) return;
            started = true;
            initializeDashboard(root);
        };
        if (!config.lazy) { started = true; initializeDashboard(root); return; }
        if (typeof IntersectionObserver === 'function') {
            const observer = new IntersectionObserver(entries => {
                if (entries.some(entry => entry.isIntersecting)) { start(); if (started) observer.disconnect(); }
            });
            observer.observe(root);
        }
        document.addEventListener('change', start);
        window.addEventListener('hashchange', start);
        start();
    });
});

async function initializeDashboard(root) {
    const config = JSON.parse(root.querySelector('[data-dashboard-config]').textContent);
    const ranges = window.DashboardRanges;
    const locale = document.documentElement.lang || 'en';
    const number = new Intl.NumberFormat(locale, { maximumFractionDigits: 3 });
    const dateFormat = new Intl.DateTimeFormat(locale, { year: 'numeric', month: 'short', day: 'numeric', timeZone: 'UTC' });
    const hasValue = value => value !== null && value !== undefined && value !== '' && Number.isFinite(Number(value));
    const buttons = [...root.querySelectorAll('[data-range]')];
    const form = root.querySelector('[data-custom-range]');
    const rangeStatus = root.querySelector('[data-range-status]');
    const interactive = config.rangeControls !== false;
    const rangeKey = key => config.id ? config.id + '_' + key : key;
    const readSelection = () => {
        if (config.persistRange === false) return { range: config.defaultRange };
        const query = new URLSearchParams(location.search), scoped = new URLSearchParams();
        ['range', 'from', 'through'].forEach(key => { if (query.has(rangeKey(key))) scoped.set(key, query.get(rangeKey(key))); });
        return ranges.read(scoped.toString(), config.defaultRange);
    };
    let selection = interactive ? readSelection() : { range: config.defaultRange };
    let rows = [], ready = false, currency = config.currency;
    if (config.followCurrency) {
        const savedCurrency = document.cookie.match(/(?:^|; )currency=([^;]+)/)?.[1];
        if (['sc', 'eur', 'usd', 'cad', 'gbp'].includes(savedCurrency)) currency = savedCurrency;
    }
    const states = config.sections.flatMap(section => (section.charts || []).map((chart, index) => ({
        ...chart,
        panel: root.querySelector(`[data-dashboard-chart="${chart.id}"]`),
        xLabels: section.xLabels !== 'last' || index === section.charts.length - 1,
        graph: null,
    })));

    function rangeMessage(message = '') {
        if (!rangeStatus) return;
        rangeStatus.textContent = message;
        rangeStatus.hidden = !message;
    }
    function controls() {
        if (!interactive) return;
        buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.range === selection.range)));
        form.hidden = selection.range !== 'custom';
        buttons.find(button => button.dataset.range === 'custom').setAttribute('aria-expanded', String(!form.hidden));
        if (selection.range === 'custom') {
            form.elements.from.value = selection.from;
            form.elements.through.value = selection.through;
        }
    }
    function saveRange() {
        if (config.persistRange === false) return;
        const url = new URL(location.href);
        url.searchParams.set(rangeKey('range'), selection.range);
        ['from', 'through'].forEach(key => {
            if (selection.range === 'custom') url.searchParams.set(rangeKey(key), selection[key]);
            else url.searchParams.delete(rangeKey(key));
        });
        history.replaceState(null, '', url);
    }
    buttons.forEach(button => button.addEventListener('click', () => {
        if (button.dataset.range === 'custom') {
            form.hidden = false;
            button.setAttribute('aria-expanded', 'true');
            const bounds = ranges.bounds(rows, selection);
            if (!form.elements.from.value && bounds) form.elements.from.value = bounds.start;
            if (!form.elements.through.value && bounds) form.elements.through.value = bounds.end;
            form.elements.from.focus();
            return;
        }
        selection = { range: button.dataset.range };
        form.elements.through.setCustomValidity('');
        rangeMessage();
        controls(); saveRange(); refresh();
    }));
    form?.addEventListener('input', () => { form.elements.through.setCustomValidity(''); rangeMessage(); });
    form?.addEventListener('submit', event => {
        event.preventDefault();
        const from = form.elements.from.value, through = form.elements.through.value;
        if (!ranges.validDate(from) || !ranges.validDate(through) || from > through) {
            const message = 'Choose valid dates with From on or before Through.';
            form.elements.through.setCustomValidity(message);
            rangeMessage(message); form.reportValidity(); return;
        }
        selection = { range: 'custom', from, through };
        rangeMessage(); controls(); saveRange(); refresh();
    });
    window.addEventListener('popstate', () => {
        if (!interactive || config.persistRange === false) return;
        selection = readSelection();
        rangeMessage(selection.error); controls(); refresh();
    });
    controls(); rangeMessage(selection.error);

    function format(value, key) {
        const type = config.metrics[key].type;
        if (type === 'date') return ranges.validDate(value) ? dateFormat.format(new Date(value + 'T00:00:00Z')) : 'N/A';
        if (!hasValue(value)) return 'N/A';
        if (type === 'price') return new Intl.NumberFormat(locale, { style: 'currency', currency: currency.toUpperCase(), minimumFractionDigits: 6, maximumFractionDigits: 8 }).format(value);
        if (type === 'money' && currency !== 'sc') return new Intl.NumberFormat(locale, {
            style: 'currency', currency: currency.toUpperCase(), maximumFractionDigits: config.metrics[key].decimals ?? 0,
        }).format(value);
        if (type === 'bytes') {
            const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
            const power = value === 0 ? 0 : Math.min(units.length - 1, Math.max(0, Math.floor(Math.log10(Math.abs(value)) / 3)));
            return number.format(value / (1000 ** power)) + ' ' + units[power];
        }
        if (type === 'ratio') return number.format(value * 100) + '%';
        return number.format(value) + (type === 'sc' || type === 'money' ? ' SC' : '');
    }
    function status(state, message) {
        state.panel.querySelector('.dashboard-canvas').hidden = !!message;
        const node = state.panel.querySelector('[data-chart-status]');
        node.hidden = !message; node.textContent = message;
        const retry = state.panel.querySelector('[data-chart-retry]');
        if (retry) retry.hidden = !state.error && !state.retryable;
    }
    function tooltip(state) {
        const chart = state.graph.chart;
        const popup = document.createElement('div');
        popup.className = 'dashboard-tooltip'; popup.hidden = true;
        state.panel.querySelector('.dashboard-canvas').append(popup);
        state.popup = popup;
        chart.options.interaction = { mode: 'index', axis: 'x', intersect: false };
        chart.options.plugins.tooltip.enabled = false;
        chart.options.plugins.tooltip.external = ({ tooltip: model }) => {
            if (!model.opacity || !model.dataPoints?.length) { popup.hidden = true; return; }
            const date = chart.data.labels[model.dataPoints[0].dataIndex];
            const row = state.rows.find(item => item.date === date);
            const heading = document.createElement('div');
            heading.className = 'dashboard-tooltip__date';
            heading.textContent = dateFormat.format(new Date(date + 'T00:00:00Z'));
            popup.replaceChildren(heading);
            const tooltipMetrics = state.tooltipMetrics || Object.keys(config.metrics);
            const includesOtherCharts = tooltipMetrics.some(key => !state.metrics.includes(key));
            tooltipMetrics.forEach(key => {
                const metric = config.metrics[key];
                const line = document.createElement('div');
                line.className = 'dashboard-tooltip__row';
                if (includesOtherCharts && state.metrics.includes(key)) line.classList.add('is-hovered');
                const label = document.createElement('span'), value = document.createElement('span');
                label.textContent = metric.label; value.textContent = format(row?.[key], key);
                if (metric.showNative && currency !== 'sc' && hasValue(row?.native?.[key])) value.textContent += ' (' + number.format(row.native[key]) + ' SC)';
                line.append(label, value); popup.append(line);
            });
            popup.hidden = false;
            popup.style.left = `${Math.max(0, Math.min(model.caretX + 12, chart.width - popup.offsetWidth))}px`;
            popup.style.top = `${Math.max(0, Math.min(model.caretY, chart.height - popup.offsetHeight))}px`;
        };
    }
    function renderChart(state, selected) {
        const rows = state.rows;
        if (state.popup) state.popup.hidden = true;
        if (state.graph) {
            state.graph.chart.setActiveElements([]);
            state.graph.chart.tooltip.setActiveElements([], { x: 0, y: 0 });
        }
        // Trim only the leading zero history; preserve zeros after the series begins.
        const firstValid = rows.findIndex(row => state.metrics.some(key => hasValue(row[key])));
        const firstNonzero = rows.findIndex(row => state.metrics.some(key => hasValue(row[key]) && row[key] !== 0));
        const first = state.startAt === 'first-valid' || firstNonzero < 0 ? firstValid : firstNonzero;
        const visible = selected.filter(row => first >= 0 && row.date >= rows[first].date);
        if (!visible.some(row => state.metrics.some(key => hasValue(row[key])))) {
            status(state, rows.length ? 'No data for this period.' : 'No history available.'); return;
        }
        status(state, '');
        if (!state.graph) {
            const firstMetric = config.metrics[state.metrics[0]];
            state.graph = new GraphRenderer({
                canvasId: state.panel.querySelector('canvas').id,
                jsonData: rows,
                datasets: state.metrics.map(key => {
                    const metric = config.metrics[key];
                    return {
                        key, label: metric.label, backgroundColor: metric.backgroundColor,
                        borderColor: metric.borderColor, unitDivisor: 1,
                        unit: metric.type === 'money' ? currency.toUpperCase() : metric.type === 'sc' ? 'SC' : null,
                        decimalPlaces: metric.type === 'price' ? 8 : metric.type === 'ratio' ? 6 : 0, startAtZero: state.beginAtZero !== false,
                    };
                }),
                dateKey: 'date', charttype: state.type || 'line', interval: 'month',
                rangeslider: false, displaylegend: state.metrics.length > 1, displayYAxis: false,
                defaultrangeinmonths: 3, maintainAspectRatio: false, useFiat: false,
                currency: 'sc', unitType: firstMetric.type === 'ratio' ? 'scientific' : firstMetric.type === 'bytes' ? 'bytes' : null,
                stacked: state.stacked === true, yScale: state.yScale || 'linear',
            });
            state.graph.datasetsConfig.forEach((dataset, index) => {
                if (config.metrics[state.metrics[index]].chartType) dataset.type = config.metrics[state.metrics[index]].chartType;
                dataset.borderDash = config.metrics[state.metrics[index]].projected ? [5, 5] : [];
            });
            if (['money', 'sc', 'price'].includes(firstMetric.type)) {
                state.graph.chart.options.scales.y.ticks.callback = value => format(value, state.metrics[0]);
            }
            tooltip(state);
        }
        const graph = state.graph;
        graph.startDateIndex = rows.indexOf(visible[0]);
        graph.endDateIndex = rows.indexOf(visible[visible.length - 1]);
        const x = graph.chart.options.scales.x;
        x.min = Date.parse(visible[0].date);
        x.max = Math.max(Date.parse(visible[visible.length - 1].date), x.min + ranges.day);
        x.offset = false; x.ticks.display = state.xLabels;
        x.time.unit = state.interval || ((x.max - x.min) / ranges.day <= 45 ? 'day' : 'month');
        graph.updateChart(graph.startDateIndex, graph.endDateIndex);
        graph.chart.resize();
    }
    function refresh() {
        if (!ready || !root.getClientRects().length) return;
        const bounds = ranges.bounds(rows, selection);
        const selected = bounds ? rows.filter(row => row.date >= bounds.start && row.date <= bounds.end) : [];
        root.querySelectorAll('[data-metric]').forEach(node => {
            const key = node.dataset.metric;
            const group = node.closest('[data-dashboard-kpis]');
            const kpis = JSON.parse(group.dataset.dashboardKpis);
            if (kpis.mode === 'latest') {
                const latest = (kpis.source ? sources[kpis.source]?.rows : rows)?.at(-1);
                node.textContent = format(latest?.[key], key);
                node.title = latest ? 'Snapshot: ' + (latest.timestamp || latest.date) : '';
                const stamp = group.querySelector('[data-snapshot-date]');
                if (stamp) stamp.textContent = latest ? 'Latest recorded: ' + (latest.timestamp || latest.date).replace('T', ' ').replace(/Z$/, '') + ' UTC · independent of chart range' : 'Latest snapshot unavailable.';
            } else {
                const values = selected.map(row => row[key]).filter(hasValue);
                node.textContent = format(values.length ? values.reduce((sum, value) => sum + value, 0) : null, key);
                node.title = values.length && values.length < selected.length ? 'Total of available records in the selected period' : '';
            }
        });
        states.forEach(state => {
            const table = state.panel.querySelector('[data-chart-rows]');
            if (table) table.replaceChildren();
            if (state.error) { status(state, 'History unavailable.'); return; }
            const chartBounds = state.interval === 'month' ? ranges.monthlyBounds(rows, selection) : bounds;
            const start = selection.range === 'all' ? state.rows[0]?.date : chartBounds?.start;
            const end = selection.range === 'all' || (state.includeForecast && selection.range !== 'custom') ? state.rows.at(-1)?.date : chartBounds?.end;
            const visible = chartBounds ? state.rows.filter(row => row.date >= start && row.date <= end) : [];
            if (table) visible.forEach(row => {
                const tr = document.createElement('tr');
                [row.date, ...state.metrics.map(key => format(row[key], key))].forEach(value => {
                    const td = document.createElement('td'); td.textContent = value; tr.append(td);
                });
                table.append(tr);
            });
            try { renderChart(state, visible); }
            catch (error) { console.error('Dashboard chart failed', state.id, error); status(state, 'Chart unavailable.'); }
        });
    }
    const definitions = config.sources || { default: config.source };
    const sources = {};
    async function load() {
        const loaded = await Promise.allSettled(Object.values(definitions).map(source => window.DashboardSources.load(source)));
        Object.keys(definitions).forEach((key, index) => {
            const result = loaded[index];
            if (result.status === 'rejected') { sources[key] = { rows: [], error: result.reason }; return; }
            sources[key] = { ...result.value, rows: result.value.rows
                .filter(row => row && ranges.validDate(String(row.date).slice(0, 10)))
                .map(row => ({ ...row, timestamp: String(row.date), date: String(row.date).slice(0, 10) })).sort((a, b) => a.date.localeCompare(b.date)) };
        });
        for (const [key, source] of Object.entries(sources)) {
            if (definitions[key].gaps === 'day') {
                const filled = [];
                source.rows.forEach(row => {
                    const previous = filled.at(-1);
                    if (previous && Date.parse(row.date) - Date.parse(previous.date) > ranges.day) {
                        filled.push({date: new Date(Date.parse(previous.date) + ranges.day).toISOString().slice(0, 10)});
                    }
                    filled.push(row);
                });
                source.rows = filled;
            }
            source.raw = source.rows;
        }
        await normalize();
    }
    const valueAt = (row, key, metric) => {
        const value = row[metric.field || key];
        return metric.bucket ? value?.sc : value;
    };
    async function normalize() {
        try {
            const raw = Object.values(sources).flatMap(source => source.raw);
            const moneyKeys = Object.keys(config.metrics).filter(key => config.metrics[key].type === 'money');
            if (moneyKeys.length && currency !== 'sc') {
                if (moneyKeys.some(key => !config.metrics[key].bucket) && typeof ensureGlobalCurrencyRates === 'function') await ensureGlobalCurrencyRates();
                const hasRates = raw.every(row => moneyKeys.every(key => {
                    const metric = config.metrics[key];
                    if (!hasValue(valueAt(row, key, metric))) return true;
                    if (metric.bucket) return hasValue(row[metric.field || key]?.[currency]);
                    return window.currencyDisplay?.resolveRateForEntryDate(row.date, currency, null) > 0;
                }));
                if (!hasRates) currency = 'sc';
            }
            for (const source of Object.values(sources)) {
                source.rows = source.raw.map(row => {
                    const normalized = { date: row.date, timestamp: row.timestamp, native: {} };
                    Object.entries(config.metrics).forEach(([key, metric]) => {
                        const rawValue = valueAt(row, key, metric);
                        let value = hasValue(rawValue) ? Number(rawValue) / (metric.divisor || 1) : null;
                        if (metric.type === 'price') value = hasValue(row[currency]) && Number(row[currency]) > 0 ? Number(row[currency]) : null;
                        if (metric.type === 'ratio') {
                            const numerator = metric.numerator.map(field => row[field]);
                            const denominator = row[metric.denominator];
                            value = numerator.every(hasValue) && hasValue(denominator) && Number(denominator) > 0
                                ? numerator.reduce((sum, v) => sum + Number(v), 0) / Number(denominator) : null;
                        }
                        if (metric.type === 'money') normalized.native[key] = value;
                        if (metric.type === 'money' && currency !== 'sc' && value !== null) {
                            value = metric.bucket ? Number(row[metric.field || key][currency])
                                : value * window.currencyDisplay.resolveRateForEntryDate(row.date, currency, null);
                        }
                        normalized[key] = metric.type === 'date' ? (ranges.validDate(String(rawValue).slice(0, 10)) ? String(rawValue).slice(0, 10) : null) : Number.isFinite(value) ? value : null;
                    });
                    return normalized;
                });
            }
            const anchor = sources[config.anchorSource || 'default'] || Object.values(sources)[0];
            const fallback = Object.values(sources).find(source => source.rows.length) || anchor;
            const chosen = anchor.rows.length ? anchor : fallback;
            rows = chosen.anchorDate ? chosen.rows.filter(row => row.date <= chosen.anchorDate) : chosen.rows;
            states.forEach(state => {
                const source = sources[state.source || 'default'];
                if (state.graph) { state.graph.chart.destroy(); state.graph = null; state.popup?.remove(); state.popup = null; }
                state.rows = source?.rows || [];
                state.error = source?.error;
                state.retryable = source?.retryable;
                const warning = state.panel.querySelector('[data-chart-warning]');
                if (warning) {
                    warning.textContent = (source?.warnings || []).join(' ');
                    warning.hidden = !warning.textContent;
                }
            });
            ready = true; refresh();
        } catch (error) {
            console.error('Dashboard data failed', error);
            root.querySelectorAll('[data-metric]').forEach(node => { node.textContent = 'N/A'; });
            states.forEach(state => status(state, 'History unavailable.'));
        }
    }
    const selector = root.querySelector('[data-dashboard-currency]');
    if (selector) {
        selector.addEventListener('change', () => {
            if (typeof window.setCurrency === 'function') window.setCurrency(selector.value);
            else document.dispatchEvent(new CustomEvent('currencyChange', { detail: selector.value }));
        });
    }
    if (selector || config.followCurrency) document.addEventListener('currencyChange', event => {
        currency = (config.currencies || ['sc', 'eur', 'usd', 'cad', 'gbp']).includes(event.detail) ? event.detail : config.currency;
        if (selector) selector.value = currency;
        if (ready) normalize();
    });
    const resizeVisible = () => setTimeout(() => {
        if (root.getClientRects().length) {
            refresh();
            states.forEach(state => { state.graph?.chart.stop(); state.graph?.chart.resize(); });
        }
    }, 0);
    document.addEventListener('change', resizeVisible);
    window.addEventListener('hashchange', resizeVisible);
    if (typeof ResizeObserver === 'function') {
        let width = 0;
        new ResizeObserver(entries => {
            const next = entries[0].contentRect.width;
            if (next > 0 && next !== width) {
                width = next;
                states.forEach(state => state.graph?.chart.resize());
            } else if (next === 0) width = 0;
        }).observe(root);
    }
    states.forEach(state => state.panel.querySelector('[data-chart-retry]')?.addEventListener('click', async event => {
        event.target.disabled = true;
        try { await load(); } finally { event.target.disabled = false; }
    }));
    states.forEach(state => state.panel.querySelector('[data-chart-mode]')?.addEventListener('change', event => {
        Object.assign(state, state.modes[Number(event.target.value)]);
        if (state.graph) { state.graph.chart.destroy(); state.graph = null; state.popup?.remove(); state.popup = null; }
        refresh();
    }));
    await load();
}
