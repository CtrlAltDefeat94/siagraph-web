/* Current contract detail is separate from published daily wallet/host metrics. */
(() => {
    'use strict';
    const node = (tag, text) => { const n = document.createElement(tag); if (text !== undefined) n.textContent = text; return n; };
    const short = value => `${value.slice(0, 12)}…${value.slice(-8)}`;
    function link(value, kind) {
        if (!value) return node('span', 'Unknown');
        const a = node('a', short(value)); a.title = value;
        a.href = kind === 'contract' ? `/contract/${encodeURIComponent(value)}` : `/${kind}?${kind === 'host' ? 'public_key' : 'address'}=${encodeURIComponent(value)}`;
        return a;
    }
    function value(raw, type) {
        const n = node('span', type === 'money' ? window.renterCurrency.format(raw, { money: 'hastings' }) : window.renterFormat.format(raw, type));
        n.title = raw == null ? 'Unavailable' : `${raw}${type === 'money' ? ' Hastings' : type === 'bytes' ? ' bytes' : ''}`;
        return n;
    }
    function share(bytes, total) {
        if (bytes == null || total == null || BigInt(total) === 0n) return 'Unavailable';
        const amount = BigInt(bytes), sum = BigInt(total);
        // Do not label a small, nonzero renter's share as 0.0%.
        if (amount > 0n && amount * 1000n < sum) {
            const fraction = (amount * 1000000n + sum / 2n) / sum;
            return fraction === 0n ? '<0.0001%' : `0.${fraction.toString().padStart(4, '0')}%`;
        }
        const tenths = (amount * 1000n + sum / 2n) / sum;
        return `${tenths / 10n}.${tenths % 10n}%`;
    }
    function table(target, headings, rows, empty) {
        const t = node('table'), head = node('thead'), tr = node('tr'), body = node('tbody');
        const numeric = label => ['Contracts', 'Contracted storage', 'Share', 'Stored', 'End height', 'Locked revenue', 'Height'].includes(label);
        headings.forEach(label => { const th = node('th', label); th.scope = 'col'; if (numeric(label)) th.className = 'ac-number'; tr.append(th); }); head.append(tr); t.append(head, body);
        rows.forEach(row => { const tr = node('tr'); row.forEach((content, index) => { const td = node('td'); if (numeric(headings[index])) td.className = 'ac-number'; td.append(content instanceof Node ? content : node('span', content)); tr.append(td); }); body.append(tr); });
        target.replaceChildren(rows.length ? t : node('p', empty));
    }
    const requests = new Map();
    function api(action, params) {
        const key = action + '?' + new URLSearchParams(Object.entries(params).sort(([a], [b]) => a.localeCompare(b)));
        const cached = requests.get(key);
        if (cached && Date.now() - cached.time < 60000) return cached.promise;
        const promise = fetchApi(action, params).catch(error => { if (requests.get(key)?.promise === promise) requests.delete(key); throw error; });
        requests.set(key, { time: Date.now(), promise });
        return promise;
    }
    async function fetchApi(action, params) {
        const controller = new AbortController(), timeout = setTimeout(() => controller.abort(), 20000);
        try {
            const res = await fetch(`/api/v2/contracts/${action}.php?${new URLSearchParams(params)}`, { cache: 'no-store', signal: controller.signal });
            const payload = await res.json();
            if (!res.ok || payload.errors?.length) throw new Error(payload.errors?.[0]?.message || 'Contract information is unavailable.');
            return payload;
        } finally { clearTimeout(timeout); }
    }
    function mount(root, identity) {
        if (!root || root.dataset.mounted || !identity) return;
        root.dataset.mounted = 'true'; root.classList.add('active-contracts');
        const host = root.dataset.kind === 'host', params = host ? { host_public_key: identity } : { address: identity };
        const summaryOnly = host || root.hasAttribute('data-summary-only');
        const palette = host
            ? ['#ef8f7b', '#8aa3ff', '#f7c873', '#65d19e', '#b49ae8', '#33d4e0', '#ef7db3', '#e38e60', '#6d728c']
            : ['#33d4e0', '#ef8f7b', '#8aa3ff', '#f7c873', '#9de5b4', '#ef7db3', '#65d19e', '#e38e60', '#6d728c'];
        let page = 1, request = 0, loaded = false, current = null, chart = null;
        const status = node('p', 'Open this tab to load current contracts.'); status.setAttribute('role', 'status');
        const summary = node('div'); summary.className = 'ac-summary';
        const distribution = node('div'), expirations = node('div'), items = node('div');
        [distribution, expirations, items].forEach(n => n.className = 'ac-scroll');
        const chartWrap = node('div'), canvas = node('canvas'); chartWrap.className = 'ac-chart'; chartWrap.hidden = true; chartWrap.append(canvas);
        canvas.setAttribute('role', 'img'); canvas.setAttribute('aria-label', 'Contracted storage distribution; values are listed in the following table.');
        const form = node('form'); form.className = 'ac-controls';
        const searchLabel = node('label', 'Find contract'), search = node('input'); search.placeholder = 'Full contract ID'; search.maxLength = 64; search.pattern = '[a-fA-F0-9]{64}'; searchLabel.append(search);
        const sortLabel = node('label', 'Sort'), sort = node('select');
        for (const [key, label] of [['ending', 'Ending first'], ['size', 'Largest first'], ['newest', 'Newest first']]) { const o = node('option', label); o.value = key; sort.append(o); }
        sortLabel.append(sort); const submit = node('button', 'Apply'); submit.type = 'submit'; form.append(searchLabel, sortLabel, submit);
        const previous = node('button', 'Previous'), next = node('button', 'Next'), pageLabel = node('span'), pager = node('div');
        previous.type = next.type = 'button'; previous.disabled = next.disabled = true; pager.className = 'ac-controls'; pager.append(previous, pageLabel, next);
        root.append(status, summary, node('h3', host ? 'Renter storage distribution' : 'Host distribution'), chartWrap, distribution,
            Object.assign(node('h3', 'Contract end windows'), { title: 'Separate windows estimated at 144 blocks per day. Past-end contracts remain listed separately from upcoming deadlines until they leave the active set.' }), expirations);
        if (!summaryOnly) root.append(node('h3', 'Active contracts'), form, items, pager);
        if (summaryOnly) {
            root.classList.add('ac-host');
            const toolbar = node('div'); toolbar.className = 'ac-toolbar'; root.prepend(toolbar); toolbar.append(status);
            status.classList.add('ac-freshness');
            // Reuse the host page's section and stat-card vocabulary.
            let section;
            for (const child of [...root.children]) {
                if (child.tagName === 'H3') {
                    section = node('section'); section.className = 'card host-section-card ac-section';
                    root.insertBefore(section, child); child.className = 'card__heading'; section.append(child);
                } else if (section) section.append(child);
            }
            const distributionLayout = node('div'); distributionLayout.className = 'ac-distribution-layout';
            chartWrap.before(distributionLayout); distributionLayout.append(chartWrap, distribution);
            distributionLayout.closest('section').querySelector('h3').title = host ? 'Slices are sized by stored data. The top eight wallets are listed individually; the rest are included in Others. Wallets with zero stored data have no slice.' : 'Slices are sized by contracted storage. The top eight hosts are listed individually; the rest are included in Others.';
            form.classList.add('ac-filterbar'); pager.classList.add('ac-pagination');
            searchLabel.className = 'ac-search';
            [previous, next, submit].forEach(button => button.classList.add('button', 'text-sm'));
            [distribution, expirations].forEach(scroll => {
                scroll.tabIndex = 0; scroll.setAttribute('role', 'region');
                scroll.setAttribute('aria-label', `${scroll.closest('section').querySelector('h3').textContent} table`);
            });
        }

        function draw(data) {
            const s = data.summary; summary.replaceChildren();
            const metrics = [['Active contracts', s.contracts, 'count'], ['With stored data', s.with_data, 'count'], ['Empty contracts', s.empty_contracts, 'count'],
                [host ? 'Renter wallets' : 'Hosts', host ? s.renter_wallets : s.hosts, 'count'], ['Contracted storage', s.bytes, 'bytes']];
            if (s.unknown_size !== '0') metrics.push(['Unknown filesize', s.unknown_size, 'count']);
            if (host && s.unknown_wallet_contracts !== '0') metrics.push(['Unmapped contracts', s.unknown_wallet_contracts, 'count']);
            for (const [label, raw, type] of metrics) {
                const dl = node('dl'), dt = node('dt', label), dd = node('dd');
                if (summaryOnly) { dl.className = 'host-stat-card'; dt.className = 'host-stat-card__label'; dd.className = 'host-stat-card__value'; }
                if (label === 'Contracted storage') dt.title = 'Sum of active contract filesizes. This can differ from used storage and does not measure unique user data.';
                dd.append(value(raw, type)); dl.append(dt, dd); summary.append(dl);
            }
            const groups = data.distribution;
            table(distribution, [host ? 'Renter wallet' : 'Host', 'Contracts', 'Contracted storage', 'Share'], groups.map(r => [r.others ? 'Others' : link(r.identity, host ? 'renter' : 'host'), value(r.contracts, 'count'), value(r.bytes, 'bytes'), share(r.bytes, s.bytes)]), 'No active contracts.');
            chart?.destroy(); chart = null; chartWrap.hidden = true;
            if (typeof Chart === 'function' && s.bytes != null && BigInt(s.bytes) > 0n && groups.every(r => r.bytes != null)) {
                chartWrap.hidden = false;
                chart = new Chart(canvas, { type: 'doughnut', data: {
                    labels: groups.map(r => r.others ? 'Others' : r.identity ? short(r.identity) : 'Unknown'),
                    datasets: [{ data: groups.map(r => Number(r.bytes)), backgroundColor: palette, ...(host ? { borderColor: '#302323', borderWidth: 2, hoverOffset: 4 } : {}) }]
                }, options: { maintainAspectRatio: false, cutout: host ? '72%' : '50%', plugins: { legend: { display: !host, labels: { color: '#d8d5cf' } }, tooltip: { callbacks: { label: ctx => `${ctx.label}: ${window.renterFormat.format(groups[ctx.dataIndex].bytes, 'bytes')} (${share(groups[ctx.dataIndex].bytes, s.bytes)})` } } } } });
            }
            if (host) {
                distribution.querySelectorAll('tbody tr').forEach((row, index) => {
                    const swatch = node('span'); swatch.className = 'ac-swatch'; swatch.style.backgroundColor = palette[index]; swatch.setAttribute('aria-hidden', 'true');
                    row.firstChild.prepend(swatch);
                });
            }
            if (!data.reference) expirations.replaceChildren(node('p', 'End-window estimates are unavailable because no reference block is available.'));
            else {
                const buckets = new Map(data.expirations.map(r => [r.period, r]));
                table(expirations, ['End window', 'Contracts', 'Contracted storage', ...(host ? ['Locked revenue'] : [])],
                    [['past', 'At/past end height'], ['day', 'Next ~1 day'], ['week', '~1–7 days'], ['month', '~7–30 days'], ['later', 'After ~30 days'], ['unknown', 'Unknown end height']].map(([key, label]) => {
                        const r = buckets.get(key) || { contracts: '0', bytes: '0', revenue_locked: '0' };
                        return [label, value(r.contracts, 'count'), value(r.bytes, 'bytes'), ...(host ? [value(r.revenue_locked, 'money')] : [])];
                    }), 'No active contracts.');
            }
            if (!summaryOnly) table(items, ['Contract', host ? 'Renter wallet' : 'Host', 'Stored', 'End height', 'Renewed from', ...(host ? ['Locked revenue'] : [])], data.items.map(r => [
                link(r.contract_id, 'contract'), link(host ? r.renter_wallet_address : r.host_public_key, host ? 'renter' : 'host'), value(r.filesize, 'bytes'),
                value(r.windowend, 'count'), r.renewed_from_contract_id ? link(r.renewed_from_contract_id, 'contract') : '—', ...(host ? [value(r.revenue_locked, 'money')] : [])
            ]), search.value.trim() ? 'No matching active contract for this profile.' : 'No active contracts on this page.');
        }
        async function load() {
            const token = ++request; status.textContent = 'Loading current contracts…'; status.classList.remove('ac-error');
            previous.disabled = next.disabled = submit.disabled = true;
            try {
                const payload = await api('active', { ...params, page, sort: sort.value, search: search.value.trim() });
                if (token !== request) return;
                current = payload.data; draw(current);
                if (host) window.renterCurrency.ready().then(() => { if (token === request && current) draw(current); });
                const ref = current.reference;
                status.textContent = `Read ${payload.meta.generated_at}${ref ? ` · Reference block ${ref.block_height} (${ref.timestamp ?? 'time unavailable'})` : ''}. Contract updates may lag this indexed block and differ from daily summaries.`;
                if (host) {
                    status.title = status.textContent;
                    status.textContent = `Updated ${payload.meta.generated_at.replace('T', ' ').replace(/Z$/, ' UTC')}`;
                }
                pageLabel.textContent = `Page ${page}`; previous.disabled = page === 1; next.disabled = !current.pagination.has_more;
            } catch (e) {
                if (token !== request) return;
                current = null; summary.replaceChildren(); distribution.replaceChildren(); expirations.replaceChildren(); items.replaceChildren(); chart?.destroy(); chart = null; chartWrap.hidden = true; pageLabel.textContent = '';
                status.textContent = e.name === 'AbortError' ? 'Contract request timed out.' : e.message.replace(/\s*Please retry\.?$/i, ''); status.classList.add('ac-error');
            } finally { if (token === request) submit.disabled = false; }
        }
        form.onsubmit = e => { e.preventDefault(); page = 1; load(); };
        previous.onclick = () => { page--; load(); }; next.onclick = () => { page++; load(); };
        function visible() {
            if (root.getClientRects().length && !loaded) { loaded = true; load(); }
            else if (root.getClientRects().length) chart?.resize();
        }
        document.addEventListener('renter:tabchange', visible);
        document.addEventListener('renter:currencyready', () => { if (current) draw(current); });
        document.querySelectorAll('input[name="host-tabs"]').forEach(radio => radio.addEventListener('change', visible));
        window.addEventListener('hashchange', () => requestAnimationFrame(visible));
        if (typeof IntersectionObserver === 'function') { const observer = new IntersectionObserver(visible); observer.observe(root); }
        visible();
    }
    window.activeContracts = { mount, api, table, link, value };
    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-active-contracts][data-kind="host"]').forEach(root => mount(root, root.dataset.identity)));
    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-contract-economics]').forEach(root => {
        const amount = root.querySelector('[data-locked-revenue]');
        let loaded = false, raw = null;
        const draw = () => amount.replaceChildren(value(raw, 'money'));
        async function load() {
            try {
                const { data } = await api('active', { host_public_key: root.dataset.identity, page: 1, sort: 'ending', search: '' });
                raw = data.summary.revenue_locked; draw();
                window.renterCurrency.ready().then(draw);
            } catch (_) { raw = null; draw(); }
        }
        const visible = () => { if (!loaded && root.getClientRects().length) { loaded = true; load(); } };
        document.addEventListener('renter:currencyready', draw);
        document.querySelectorAll('input[name="host-tabs"]').forEach(radio => radio.addEventListener('change', visible));
        window.addEventListener('hashchange', () => requestAnimationFrame(visible));
        if (typeof IntersectionObserver === 'function') new IntersectionObserver(visible).observe(root);
        visible();
    }));
})();
