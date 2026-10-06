/* Stored metrics remain exact strings; Number conversion is confined to chart coordinates. */
(() => {
    'use strict';
    const el = id => document.getElementById(id);
    const node = (tag, text, cls) => { const n = document.createElement(tag); if (text !== undefined) n.textContent = text; if (cls) n.className = cls; return n; };
    const fields = {
        contracted_filesize: ['Contracted storage', 'bytes'], active_contracts: ['Active contracts', 'count'], active_hosts: ['Active hosts', 'count'],
        refundable_allowance: ['Refundable allowance', 'money'], host_revenue_committed: ['Committed host revenue', 'money'],
        active_renewed_contracts: ['Active renewed contracts', 'count'], average_contract_duration: ['Average contract duration', 'duration'],
        expiring_within_1_day: ['Expiring within 1 day', 'count'], expiring_within_1_week: ['Expiring within 1 week', 'count'], expiring_within_30_days: ['Expiring within 30 days', 'count'],
        contracts_formed: ['Contracts formed', 'count'], contract_revisions: ['Contract revisions', 'count'], contracts_resolved_storage_proof: ['Resolved by storage proof', 'count'],
        contracts_resolved_expiration: ['Resolved by expiration', 'count'], contracts_resolved_renewal: ['Resolved by renewal', 'count'], bytes_uploaded: ['Bytes uploaded', 'bytes'],
        bytes_removed: ['Bytes removed', 'bytes'], spending: ['Spending', 'money'], funds_returned: ['Funds returned', 'money'], renewal_funds_rolled: ['Renewal funds rolled', 'money'], additional_renewal_funds: ['Additional renewal funds', 'money']
    };
    let units = {};
    function format(value, type) { return type === 'money' ? window.renterCurrency.format(value, units) : window.renterFormat.format(value, type, units); }
    function displayed(value, type) {
        const span = node('span', format(value, type));
        span.title = window.renterFormat.exact(value, type, units);
        return span;
    }
    async function api(path, params = {}) {
        const response = await fetch(`/api/v2/renters/${path}.php?${new URLSearchParams(params)}`, { cache: 'no-store' });
        const payload = await response.json();
        if (!response.ok || payload.errors?.length) { const error = new Error(payload.errors?.[0]?.message || 'Unable to load renter information.'); error.code = payload.errors?.[0]?.code; error.details = payload.errors?.[0]?.details; throw error; }
        return payload;
    }
    function status(id, text, error = false) { el(id).textContent = text; el(id).classList.toggle('renter-error', error); }
    function link(address, full = false) {
        const a = node('a', full ? address : `${address.slice(0, 12)}…${address.slice(-8)}`); a.href = `/renter?address=${encodeURIComponent(address)}`; a.title = address; return a;
    }
    function copy(value) {
        const b = node('button', '⧉', 'renter-copy'); b.type = 'button'; b.title = `Copy ${value}`; b.setAttribute('aria-label', `Copy ${value}`);
        b.onclick = async () => { try { await navigator.clipboard.writeText(value); b.textContent = '✓'; } catch (_) { b.textContent = '!'; } }; return b;
    }
    function table(target, headings, rows) {
        el(target).replaceChildren(...rows.map(row => { const tr = node('tr'); row.forEach(value => { const td = node('td'); td.append(value instanceof Node ? value : node('span', value)); tr.append(td); }); return tr; }));
    }
    function metrics(target, data, names) {
        el(target).replaceChildren();
        names.forEach(key => { if (!(key in data)) return; const [label, type] = fields[key] || [key.replaceAll('_', ' '), 'count']; const dl = node('dl', undefined, 'renter-metric');
            const value = node('dd', format(data[key], type));
            value.title = window.renterFormat.exact(data[key], type, units);
            dl.append(node('dt', label), value); el(target).append(dl); });
    }
    async function directory() {
        let page = 1, request = 0;
        async function load() {
            const token = ++request; status('renterStatus', 'Loading renters…'); el('renterPrevious').disabled = el('renterNext').disabled = true;
            try {
                const { data, meta } = await api('index', { active: '1', sort: 'contracted_filesize', direction: 'desc', page }); if (token !== request) return;
                table('renterResults', ['Renter wallet address', 'Contracted storage', 'Active contracts', 'Active hosts', 'Last activity'], data.items.map(r => {
                    const identity = node('span', undefined, 'renter-identity'); identity.append(link(r.renter_wallet_address, true));
                    return [identity, displayed(r.contracted_filesize, 'bytes'), format(r.active_contracts), format(r.active_hosts), r.last_active];
                }));
                el('renterTable').classList.remove('table-loading');
                const total = meta.totals?.renter_count;
                status('renterStatus', data.items.length ? `Showing ${data.items.length}${total !== undefined ? ` of ${Number(total).toLocaleString()}` : ' renters'}` : 'No renters available.');
                el('renterPage').textContent = `Page ${page}`; el('renterPrevious').disabled = page === 1; el('renterNext').disabled = !data.pagination.has_more;
            } catch (e) { if (token === request) { el('renterResults').replaceChildren(); status('renterStatus', e.message, true); } }
        }
        el('renterPrevious').onclick = () => { page--; load(); }; el('renterNext').onclick = () => { page++; load(); };
        load();
    }
    function profileTabs() {
        const tabs = [...document.querySelectorAll('[data-renter-tab]')];
        function activate(name, focus = false, updateUrl = true) {
            if (name === 'history' && tabs.some(tab => tab.dataset.renterTab === 'charts')) name = 'charts';
            const selected = tabs.find(tab => tab.dataset.renterTab === name) || tabs[0];
            tabs.forEach(tab => {
                const active = tab === selected;
                tab.setAttribute('aria-selected', String(active));
                tab.tabIndex = active ? 0 : -1;
                el(`renter-panel-${tab.dataset.renterTab}`).hidden = !active;
            });
            if (focus) selected.focus();
            if (updateUrl) history.replaceState(null, '', `${location.pathname}${location.search}#${selected.dataset.renterTab === 'charts' ? 'history' : selected.dataset.renterTab}`);
            document.dispatchEvent(new Event('renter:tabchange'));
        }
        tabs.forEach((tab, index) => {
            tab.onclick = () => activate(tab.dataset.renterTab);
            tab.onkeydown = event => {
                let next;
                if (event.key === 'ArrowRight') next = (index + 1) % tabs.length;
                if (event.key === 'ArrowLeft') next = (index - 1 + tabs.length) % tabs.length;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = tabs.length - 1;
                if (next !== undefined) { event.preventDefault(); activate(tabs[next].dataset.renterTab, true); }
            };
        });
        document.querySelectorAll('[data-renter-jump]').forEach(button => {
            button.onclick = () => {
                activate(button.dataset.renterJump, true);
            };
        });
        window.addEventListener('hashchange', () => activate(location.hash.slice(1), false, false));
        activate(location.hash.slice(1), false, false);
    }
    async function detail() {
        const query = new URLSearchParams(location.search); let address = query.get('address') || '';
        const publicKey = query.get('public_key');
        if (!address && !publicKey) { status('renterStatus', 'Enter a wallet address or public key in the renter directory.', true); return; }
        let response;
        try {
            response = await api('details', { ...(address ? { address } : {}), ...(publicKey ? { public_key: publicKey } : {}) });
        } catch (error) {
            if (error.code !== 'ambiguous_key') throw error;
            async function candidates(page = 1, initial = null) {
                const data = initial || (await api('resolve', { public_key: publicKey, page })).data;
                el('renterCandidates').replaceChildren(); status('renterStatus', error.message);
                data.items.forEach(item => {
                    const p = node('p', undefined, 'renter-address'), a = link(item.renter_wallet_address, true);
                    a.href = `/renter?${new URLSearchParams({ public_key: publicKey, address: item.renter_wallet_address })}${location.hash}`;
                    p.append(a); el('renterCandidates').append(p);
                });
                for (const [label, next, enabled] of [['Previous', page - 1, page > 1], ['Next', page + 1, data.pagination?.has_more]]) if (enabled) {
                    const button = node('button', label); button.onclick = () => candidates(next).catch(e => status('renterStatus', e.message, true)); el('renterCandidates').append(button);
                }
            }
            await candidates(1, error.details); return;
        }
        const { data: renter, meta } = response; address = renter.renter_wallet_address; units = meta.units;
        const browse = el('renterBrowseContracts');
        if (browse) { browse.href = `/renter_contracts?address=${encodeURIComponent(address)}`; browse.hidden = false; }
        // Keep the caller's public_key URL. Every subsequent query uses the resolved wallet.
        const identity = node('p', undefined, 'renter-address'); identity.append(node('span', 'Wallet', 'renter-wallet-label'), node('span', address, 'renter-wallet-value'), copy(address)); el('renterIdentity').append(identity);
        el('renterDetail').hidden = false;
        status('renterStatus', 'Contract statistics for this wallet.');
        const active = BigInt(renter.active_contracts) > 0n;
        el('renterChips').append(node('span', active ? 'Active contracts' : 'No active contracts', `renter-chip${active ? ' is-active' : ''}`), node('span', 'Wallet identity', 'renter-chip'));
        const explorerLink = el('renterExplorerLink');
        explorerLink.href = `/address/${encodeURIComponent(address)}`; explorerLink.hidden = false;
        document.title = `SiaGraph - Renter ${address.slice(0, 12)}…`;
        status('renterFreshness', `Updated ${renter.updated_at} · Block ${renter.updated_height}. Snapshot completeness is not yet verified.`);
        metrics('renterMetrics', renter, ['contracted_filesize', 'active_contracts', 'active_hosts']);
        metrics('renterOverviewContracts', renter, ['active_contracts', 'active_hosts']);
        metrics('renterOverviewFunds', renter, ['refundable_allowance', 'host_revenue_committed']);
        metrics('renterContractMetrics', renter, ['active_contracts', 'active_renewed_contracts', 'average_contract_duration']);
        metrics('renterExpirationMetrics', renter, ['expiring_within_1_day', 'expiring_within_1_week', 'expiring_within_30_days']);
        metrics('renterEconomicMetrics', renter, ['refundable_allowance', 'host_revenue_committed']);
        const observations = node('div', undefined, 'renter-observations');
        for (const [label, date, height] of [['First seen', renter.first_seen, renter.first_seen_height], ['Last active', renter.last_active, renter.last_active_height]]) {
            const d = node('div'); d.append(node('strong', label), node('p', date)); const a = node('a', `Block ${height}`); a.href = `/height/${encodeURIComponent(height)}`; d.append(a); observations.append(d);
        }
        el('renterObservations').replaceChildren(observations);
        historyPanel(address);
        profileTabs();
        window.activeContracts?.mount(document.querySelector('[data-active-contracts]'), address);
    }
    function historyPanel(address) {
        // The same range is fetched once even when several groups request it together.
        const requests = new Map();
        function history(start, end, all) {
            const key = all ? 'all' : `${start}:${end}`;
            if (!requests.has(key)) {
                const request = (async () => {
                    if (!all) return api('daily', { address, start, end });
                    const rows = []; let after = '', meta;
                    do {
                        const response = await api('daily', { address, all: '1', ...(after ? { after } : {}) });
                        rows.push(...response.data); meta = response.meta;
                        const next = meta.pagination?.has_more ? meta.pagination.next_after : '';
                        if (next && (!/^\d{4}-\d{2}-\d{2}$/.test(next) || next <= after)) throw new Error('Invalid history continuation.');
                        after = next;
                    } while (after);
                    return { data: rows, meta };
                })().catch(error => { requests.delete(key); throw error; });
                requests.set(key, request);
                // Reuse this page's result; only a different date range needs data.
            }
            return requests.get(key);
        }
        document.querySelectorAll('[data-history-group]').forEach(group => {
            const form = group.querySelector('[data-history-form]');
            const statusNode = group.querySelector('[data-history-status]');
            const cards = [...group.querySelectorAll('[data-history-fields]')];
            const buttons = [...group.querySelectorAll('[data-days]')];
            const charts = new Map();
            let requestId = 0, rows = [], loadedStart = '', loadedEnd = '', groupUnits = {}, all = false;
            function dates(days) {
                const end = new Date(), start = new Date(); start.setUTCDate(end.getUTCDate() - days + 1);
                form.elements.start.value = start.toISOString().slice(0, 10); form.elements.end.value = end.toISOString().slice(0, 10);
            }
            function selected(days) { buttons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.days === days))); }
            function draw() {
                const byDate = new Map(rows.map(row => [row.date, row])); const dates = [];
                if (rows.length) {
                    for (let day = new Date(`${loadedStart}T00:00:00Z`); day.toISOString().slice(0, 10) <= loadedEnd; day.setUTCDate(day.getUTCDate() + 1)) dates.push(day.toISOString().slice(0, 10));
                }
                cards.forEach(card => {
                    charts.get(card)?.destroy(); charts.delete(card);
                    const names = JSON.parse(card.dataset.historyFields), type = fields[names[0]][1];
                    const chartType = card.dataset.chartType === 'bar' ? 'bar' : 'line';
                    const chartLabel = name => name === 'active_hosts' && card.id === 'renter-history-contracts-active' ? 'Unique hosts' : fields[name][0];
                    const chartStatus = card.querySelector('[data-chart-status]');
                    const wrap = card.querySelector('[data-chart-wrap]');
                    wrap.hidden = true;
                    const hasValues = rows.some(row => names.some(name => row[name] !== null && row[name] !== undefined));
                    chartStatus.textContent = !rows.length ? 'No history available for this range.' : !hasValues ? 'These values are not available yet.' : '';
                    if (!hasValues) return;
                    if (!window.Chart) { chartStatus.textContent = 'Chart unavailable. Please reload the page.'; return; }
                    wrap.hidden = false;
                    const colors = ['#e87a62', '#5cbdb3', '#a595e3'];
                    const datasets = names.map((name, index) => ({
                        label: chartLabel(name), data: dates.map(date => type === 'money' ? window.renterCurrency.plot(byDate.get(date)?.[name], groupUnits, date) : window.renterFormat.plot(byDate.get(date)?.[name], type, groupUnits)),
                        borderColor: colors[index % colors.length], backgroundColor: colors[index % colors.length], spanGaps: false, pointRadius: 0, pointHoverRadius: 4, pointHitRadius: 8, borderWidth: chartType === 'bar' ? 0 : 2,
                    }));
                    const axisUnit = type === 'money' ? window.renterCurrency.code : type === 'duration' ? (window.renterFormat.durationSeconds(groupUnits) ? 'Days' : groupUnits.average_contract_duration) : type === 'bytes' ? 'Storage' : 'Count';
                    if (type === 'money' && window.renterCurrency.code !== 'SC' && rows.some(row => names.some(name => row[name] != null) && window.renterCurrency.rate(row.date) === null)) chartStatus.textContent = 'Exchange rates are unavailable for some dates; those values remain gaps.';
                    const chart = new Chart(card.querySelector('canvas'), { type: chartType, data: { labels: dates, datasets }, options: {
                        responsive: true, maintainAspectRatio: false, interaction: { mode: 'index', intersect: false },
                        scales: { y: { beginAtZero: true, title: { display: true, text: axisUnit }, ticks: { precision: type === 'count' ? 0 : undefined, callback: value => {
                            if (type === 'bytes') return window.renterFormat.format(String(Math.max(0, Math.round(value))), 'bytes');
                            return new Intl.NumberFormat((window.APP_LOCALE || 'en-US').replace('_', '-'), { notation: 'compact', maximumFractionDigits: 2 }).format(value);
                        } } }, x: { ticks: { maxTicksLimit: 8, callback: value => {
                            const date = dates[value];
                            return date ? new Date(`${date}T00:00:00Z`).toLocaleDateString((window.APP_LOCALE || 'en-US').replace('_', '-'), { day: 'numeric', month: 'short', ...(dates.length > 365 ? { year: '2-digit' } : {}), timeZone: 'UTC' }) : '';
                        } } } },
                        plugins: { legend: { display: names.length > 1 }, tooltip: { callbacks: { label: context => {
                            const name = names[context.datasetIndex], raw = byDate.get(dates[context.dataIndex])?.[name];
                            return `${chartLabel(name)}: ${type === 'money' ? window.renterCurrency.format(raw, groupUnits, dates[context.dataIndex]) : window.renterFormat.format(raw, type, groupUnits)}`;
                        } } } },
                    } });
                    charts.set(card, chart);
                });
            }
            async function load() {
                const token = ++requestId; statusNode.textContent = 'Loading history…'; statusNode.classList.remove('renter-error');
                try {
                    const start = form.elements.start.value, end = form.elements.end.value;
                    const { data, meta } = await history(start, end, all); if (token !== requestId) return;
                    loadedStart = start; loadedEnd = end; groupUnits = meta.units || {};
                    rows = address ? data : data.map(row => ({ ...row.payload, date: row.snapshot_date, snapshot_height: row.snapshot_height }));
                    if (all && rows.length) { loadedStart = rows[0].date; loadedEnd = rows[rows.length - 1].date; form.elements.start.value = loadedStart; form.elements.end.value = loadedEnd; }
                    draw(); statusNode.textContent = rows.length ? `Daily history · ${loadedStart} – ${loadedEnd}. Missing dates remain gaps.${address ? ' The latest day may be incomplete.' : ''}` : 'No renter history available yet.';
                } catch (error) {
                    if (token !== requestId) return;
                    rows = []; draw(); statusNode.textContent = error.message; statusNode.classList.add('renter-error');
                }
            }
            dates(365); selected('365');
            buttons.forEach(button => { button.onclick = () => { all = button.dataset.days === 'all'; if (!all) dates(Number(button.dataset.days)); selected(button.dataset.days); load(); }; });
            form.onsubmit = event => { event.preventDefault(); all = false; selected(''); load(); };
            for (const name of ['start', 'end']) form.elements[name].onchange = () => { all = false; selected(''); };
            document.addEventListener('renter:tabchange', () => requestAnimationFrame(() => {
                if (!group.closest('[role="tabpanel"]')?.hidden) charts.forEach(chart => chart.resize?.());
            }));
            load();
        });
    }
    document.addEventListener('DOMContentLoaded', async () => {
        const currencySelect = el('currency-select');
        if (currencySelect && [...currencySelect.options].some(option => option.value === window.renterCurrency.code.toLowerCase())) currencySelect.value = window.renterCurrency.code.toLowerCase();
        await window.renterCurrency.ready();
        const page = document.querySelector('[data-renter-page]')?.dataset.renterPage;
        (page === 'directory' ? directory() : detail()).catch(e => status('renterStatus', e.message, true));
    });
})();
