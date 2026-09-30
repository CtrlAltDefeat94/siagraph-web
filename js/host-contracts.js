(() => {
    'use strict';
    document.addEventListener('DOMContentLoaded', () => {
        const root = document.querySelector('[data-host-contract-browser]');
        if (!root) return;
        const renter = root.dataset.kind === 'renter';
        const identityParams = renter ? { address: root.dataset.identity } : { host_public_key: root.dataset.identity };
        const { api, table, link, value } = window.activeContracts;
        const tabs = [...root.querySelectorAll('[data-contract-tab]')], states = new Map();
        const initialQuery = new URLSearchParams(location.search);
        let selected = location.hash === '#completed' ? 'completed' : 'active';
        function updateUrl() {
            const state = states.get(selected), query = new URLSearchParams(renter ? { address: root.dataset.identity } : { public_key: root.dataset.identity });
            if (state.search) query.set('search', state.search);
            query.set('sort', state.sort);
            history.replaceState(null, '', `${location.pathname}?${query}#${selected}`);
        }
        for (const panel of root.querySelectorAll('[data-contract-list]')) {
            const kind = panel.dataset.contractList, form = panel.querySelector('form');
            const status = panel.querySelector('[data-list-status]'), target = panel.querySelector('[data-list-table]');
            const previous = panel.querySelector('[data-list-previous]'), next = panel.querySelector('[data-list-next]');
            const refresh = panel.querySelector('[data-list-refresh]'), apply = form.querySelector('button');
            const pageLabel = panel.querySelector('[data-list-page]');
            if (kind === selected) {
                form.elements.search.value = initialQuery.get('search') || '';
                if ([...form.elements.sort.options].some(o => o.value === initialQuery.get('sort'))) form.elements.sort.value = initialQuery.get('sort');
            }
            const state = { panel, page: 1, cursors: [''], loaded: false, request: 0, data: null,
                search: form.elements.search.value.trim(), sort: form.elements.sort.value };
            states.set(kind, state);
            function draw() {
                if (!state.data) return;
                const active = kind === 'active';
                const counterpart = row => link(renter ? row.host_public_key : row.renter_wallet_address, renter ? 'host' : 'renter');
                table(target, active ? ['Contract', renter ? 'Host' : 'Renter wallet', 'Stored', 'End height', 'Renewed from', ...(!renter ? ['Locked revenue'] : [])] : ['Contract', renter ? 'Host' : 'Renter wallet', 'Resolution', 'Height'],
                    state.data.items.map(row => active
                        ? [link(row.contract_id, 'contract'), counterpart(row), value(row.filesize, 'bytes'), value(row.windowend, 'count'), row.renewed_from_contract_id ? link(row.renewed_from_contract_id, 'contract') : '—', ...(!renter ? [value(row.revenue_locked, 'money')] : [])]
                        : [link(row.contract_id, 'contract'), counterpart(row), ({storage_proof:'Storage proof',renewal:'Renewal',expiration:'Expiration'})[row.resolution_type] || row.resolution_type || 'Unknown', value(row.resolution_height, 'count')]),
                    state.search ? 'No matching contract for this profile.' : `No ${kind} contracts on this page.`);
            }
            state.load = async () => {
                const token = ++state.request; state.loaded = true;
                status.textContent = `Loading ${kind} contracts…`; status.classList.remove('ac-error');
                previous.disabled = next.disabled = refresh.disabled = apply.disabled = true;
                try {
                    const params = { ...identityParams, sort: state.sort, search: state.search,
                        ...(kind === 'active' ? { page: state.page } : { after: state.cursors[state.page - 1] }) };
                    const result = await api(kind, params);
                    if (token !== state.request) return;
                    state.data = result.data; draw();
                    status.textContent = `Read ${result.meta.generated_at} · ${result.data.items.length} contracts shown`;
                    pageLabel.textContent = `Page ${state.page}`;
                    previous.disabled = state.page === 1; next.disabled = !result.data.pagination.has_more;
                    if (kind === 'completed' && result.data.pagination.has_more) state.cursors[state.page] = result.data.pagination.next_after;
                    if (kind === 'active') window.renterCurrency.ready().then(() => { if (token === state.request) draw(); });
                } catch (error) {
                    if (token !== state.request) return;
                    state.data = null; target.replaceChildren(); pageLabel.textContent = '';
                    status.textContent = error.name === 'AbortError' ? 'The request timed out. Please retry.' : error.message;
                    status.classList.add('ac-error'); previous.disabled = state.page === 1;
                } finally { if (token === state.request) refresh.disabled = apply.disabled = false; }
            };
            form.onsubmit = event => {
                event.preventDefault(); state.page = 1; state.cursors = [''];
                state.search = form.elements.search.value.trim(); state.sort = form.elements.sort.value;
                updateUrl(); state.load();
            };
            previous.onclick = () => { state.page--; state.load(); };
            next.onclick = () => { state.page++; state.load(); };
            refresh.onclick = () => { state.page = 1; state.cursors = ['']; state.load(); };
            document.addEventListener('renter:currencyready', draw);
        }
        function activate(kind, focus = false, update = true) {
            selected = states.has(kind) ? kind : 'active';
            for (const tab of tabs) {
                const active = tab.dataset.contractTab === selected;
                tab.setAttribute('aria-selected', String(active)); tab.tabIndex = active ? 0 : -1;
                states.get(tab.dataset.contractTab).panel.hidden = !active;
                if (active && focus) tab.focus();
            }
            if (update) updateUrl();
            if (!states.get(selected).loaded) states.get(selected).load();
        }
        tabs.forEach((tab, index) => {
            tab.onclick = () => activate(tab.dataset.contractTab);
            tab.onkeydown = event => {
                let next;
                if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') next = 1 - index;
                if (event.key === 'Home') next = 0;
                if (event.key === 'End') next = 1;
                if (next !== undefined) { event.preventDefault(); activate(tabs[next].dataset.contractTab, true); }
            };
        });
        window.addEventListener('hashchange', () => activate(location.hash.slice(1), false, false));
        activate(selected, false, false);
    });
})();
