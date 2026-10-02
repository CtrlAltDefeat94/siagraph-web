import { getJson } from '../api.js';
import {
    buildExplorerHref,
    escapeHtml,
    formatDateTime,
    formatDurationSeconds,
    formatHastings,
    formatInteger,
    isDebugRouteEnabled,
    parseRouteQuery,
    renderArrayTable,
    renderBadge,
    renderCollapsibleSection,
    renderKpiStrip,
    renderKeyValueTableCompact,
    renderSection,
} from './common.js';

function numericSafe(value, fallback = 0) {
    const n = Number(value);
    return Number.isFinite(n) ? n : fallback;
}

function summarizeDifficulty(diff) {
    if (!diff || typeof diff !== 'object') return [];
    return Object.entries(diff)
        .filter(([, value]) => Number.isFinite(Number(value)))
        .slice(0, 8)
        .map(([name, value]) => ({
            metric: name,
            value: numericSafe(value).toLocaleString(undefined, { maximumFractionDigits: 4 }),
        }));
}

function renderDifficultyTable(rows) {
    if (!rows.length) {
        return '<p class="text-muted">No numeric difficulty values found for the selected range.</p>';
    }

    const body = rows.map((row) => `<tr><td>${escapeHtml(row.metric)}</td><td>${escapeHtml(row.value)}</td></tr>`).join('');
    return `
        <div class="table-responsive">
            <table class="table table-dark table-clean text-white explorer-table-compact">
                <thead><tr><th>Metric</th><th>Value</th></tr></thead>
                <tbody>${body}</tbody>
            </table>
        </div>
    `;
}

export async function renderDashboard() {
    const debugOpen = isDebugRouteEnabled(parseRouteQuery());
    const [health, state, tip, consensus, blockMetrics, hostMetrics, blockTime, fee] = await Promise.all([
        getJson('/health'),
        getJson('/state'),
        getJson('/explorer/tip'),
        getJson('/consensus/state'),
        getJson('/metrics/block'),
        getJson('/metrics/host'),
        getJson('/metrics/blocktime'),
        getJson('/txpool/fee'),
    ]);

    const kpis = renderKpiStrip([
        { label: 'Health', value: health === null ? renderBadge('Healthy', 'good') : renderBadge('Degraded', 'warn'), html: true },
        { label: 'Tip Height', value: formatInteger(tip?.height) },
        { label: 'Tip Block ID', value: tip?.id || 'N/A' },
        { label: 'Active Hosts', value: formatInteger(hostMetrics?.activeHosts) },
        { label: 'Total Hosts', value: formatInteger(blockMetrics?.totalHosts) },
        { label: 'Recommended Fee', value: formatHastings(fee), html: true },
        { label: 'Blocktime (24h)', value: formatDurationSeconds(blockTime?.day) },
        { label: 'Blocktime (7d)', value: formatDurationSeconds(blockTime?.week) },
    ], { compact: true });

    const snapshot = renderKeyValueTableCompact({
        version: state?.version ?? state?.commit,
        os: state?.os,
        daemonStart: state?.startTime,
        buildTime: state?.buildTime,
        tipHeight: tip?.height,
        tipBlockId: tip?.id,
    }, { maxRows: 10 });

    return `
        ${renderSection('Overview', kpis, { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderSection('Quick Actions', `
            <div class="explorer-link-grid">
                <a class="btn btn-sm btn-outline-light" href="${buildExplorerHref('consensus')}">Consensus</a>
                <a class="btn btn-sm btn-outline-light" href="${buildExplorerHref('metrics')}">Metrics</a>
                <a class="btn btn-sm btn-outline-light" href="${buildExplorerHref('txpool')}">Txpool</a>
                <a class="btn btn-sm btn-outline-light" href="${buildExplorerHref('peers')}">Peers</a>
                <a class="btn btn-sm btn-outline-light" href="${buildExplorerHref('exchange/USD')}">Exchange</a>
            </div>
        `, { layout: 'half', importance: 'primary', density: 'compact' })}
        ${renderSection('Network Snapshot', snapshot, { layout: 'half', importance: 'primary', density: 'compact' })}
        ${debugOpen ? renderCollapsibleSection('Advanced Daemon State', renderKeyValueTableCompact(state || {}, { maxRows: 20 }), { layout: 'half', summaryMeta: 'debug', open: true }) : ''}
        ${debugOpen ? renderCollapsibleSection('Advanced Consensus State', renderKeyValueTableCompact(consensus || {}, { maxRows: 20 }), { layout: 'half', summaryMeta: 'debug', open: true }) : ''}
        ${debugOpen ? renderCollapsibleSection('Advanced Metrics', `
            <h3 class="explorer-subheading">Tip Block Metrics</h3>
            ${renderKeyValueTableCompact(blockMetrics || {}, { maxRows: 20 })}
            <h3 class="explorer-subheading mt-3">Host Metrics</h3>
            ${renderKeyValueTableCompact(hostMetrics || {}, { maxRows: 20 })}
        `, { layout: 'full', summaryMeta: 'debug', open: true }) : ''}
    `;
}

export async function renderTxpool() {
    const [pool, fee] = await Promise.all([
        getJson('/txpool/transactions'),
        getJson('/txpool/fee'),
    ]);

    const v1 = Array.isArray(pool?.transactions) ? pool.transactions : [];
    const v2 = Array.isArray(pool?.v2transactions) ? pool.v2transactions : [];

    const sampleV1 = v1.slice(0, 10);
    const sampleV2 = v2.slice(0, 10);

    return `
        ${renderSection('Txpool Summary', renderKpiStrip([
            { label: 'Recommended Fee', value: formatHastings(fee), html: true },
            { label: 'V1 Transactions', value: formatInteger(v1.length) },
            { label: 'V2 Transactions', value: formatInteger(v2.length) },
        ], { compact: true }), { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderArrayTable('V1 Transaction Sample', sampleV1, [
            { label: 'ID', render: (row) => row?.id ? `<a href="${buildExplorerHref(`tx/${encodeURIComponent(row.id)}`)}"><code class="explorer-code" title="${escapeHtml(row.id)}">${escapeHtml(row.id.slice(0, 12))}...${escapeHtml(row.id.slice(-8))}</code></a>` : '<span class="text-muted">N/A</span>' },
            { label: 'Unconfirmed', render: (row) => row?.unconfirmed ? renderBadge('Pending', 'warn') : renderBadge('Confirmed', 'good') },
            { label: 'Host Announcements', render: (row) => escapeHtml(formatInteger(Array.isArray(row?.hostAnnouncements) ? row.hostAnnouncements.length : 0)) },
        ], { layout: 'half', importance: 'primary', density: 'compact' })}
        ${renderArrayTable('V2 Transaction Sample', sampleV2, [
            { label: 'ID', render: (row) => row?.id ? `<a href="${buildExplorerHref(`v2-tx/${encodeURIComponent(row.id)}`)}"><code class="explorer-code" title="${escapeHtml(row.id)}">${escapeHtml(row.id.slice(0, 12))}...${escapeHtml(row.id.slice(-8))}</code></a>` : '<span class="text-muted">N/A</span>' },
            { label: 'Unconfirmed', render: (row) => row?.unconfirmed ? renderBadge('Pending', 'warn') : renderBadge('Confirmed', 'good') },
            { label: 'Host Announcements', render: (row) => escapeHtml(formatInteger(Array.isArray(row?.hostAnnouncements) ? row.hostAnnouncements.length : 0)) },
        ], { layout: 'half', importance: 'primary', density: 'compact' })}
        ${renderCollapsibleSection('Raw Txpool Payload', `<pre>${escapeHtml(JSON.stringify(pool, null, 2))}</pre>`, { layout: 'full', summaryMeta: 'debug' })}
    `;
}

export async function renderConsensus() {
    const [network, state, tip] = await Promise.all([
        getJson('/consensus/network'),
        getJson('/consensus/state'),
        getJson('/consensus/tip'),
    ]);

    return `
        ${renderSection('Consensus Summary', renderKpiStrip([
            { label: 'Tip Height', value: formatInteger(tip?.height) },
            { label: 'Tip ID', value: tip?.id || 'N/A' },
            { label: 'Tip Timestamp', value: formatDateTime(tip?.timestamp), html: true },
            { label: 'Synced', value: state?.synced ? renderBadge('Yes', 'good') : renderBadge('No', 'warn'), html: true },
        ], { compact: true }), { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderSection('Jump To Height', `
            <form id="height-jump-form" class="explorer-inline-form">
                <input name="height" type="number" min="0" placeholder="Block height" required>
                <button type="submit" class="btn btn-sm btn-brand">Open</button>
            </form>
        `, { layout: 'half', importance: 'primary', density: 'compact' })}
        ${renderSection('Tip Details', renderKeyValueTableCompact(tip || {}, { includeKeys: ['height', 'id', 'timestamp'], maxRows: 8 }), { layout: 'half', importance: 'primary', density: 'compact' })}
        ${renderCollapsibleSection('State Details', renderKeyValueTableCompact(state || {}, { maxRows: 16 }), { layout: 'half', summaryMeta: 'advanced' })}
        ${renderCollapsibleSection('Network Details', renderKeyValueTableCompact(network || {}, { maxRows: 16 }), { layout: 'half', summaryMeta: 'advanced' })}
    `;
}

export function bindConsensusActions() {
    const form = document.getElementById('height-jump-form');
    if (!form) return;
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const height = String(fd.get('height') || '').trim();
        if (!height) return;
        window.location.href = buildExplorerHref(`height/${encodeURIComponent(height)}`);
    });
}

export async function renderMetrics(query = {}) {
    const [block, host, blockTime] = await Promise.all([
        getJson('/metrics/block'),
        getJson('/metrics/host'),
        getJson('/metrics/blocktime'),
    ]);

    const start = query.start ?? '';
    const end = query.end ?? '';
    let difficultyView = '<p class="text-muted">Choose a start and end height to load difficulty metrics.</p>';
    let difficultyRaw = '';

    if (start !== '' && end !== '') {
        const diff = await getJson(`/metrics/difficulty?start=${encodeURIComponent(start)}&end=${encodeURIComponent(end)}`);
        const rows = summarizeDifficulty(diff);
        difficultyView = renderDifficultyTable(rows);
        difficultyRaw = `<pre>${escapeHtml(JSON.stringify(diff, null, 2))}</pre>`;
    }

    return `
        ${renderSection('Metrics Summary', renderKpiStrip([
            { label: 'Total Hosts', value: formatInteger(block?.totalHosts) },
            { label: 'Active Hosts', value: formatInteger(host?.activeHosts) },
            { label: 'Blocktime (24h)', value: formatDurationSeconds(blockTime?.day) },
            { label: 'Blocktime (7d)', value: formatDurationSeconds(blockTime?.week) },
        ], { compact: true }), { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderSection('Difficulty Metrics', `
            <form class="explorer-inline-form" id="difficulty-form">
                <input type="number" name="start" min="0" placeholder="start height" value="${escapeHtml(start)}" required>
                <input type="number" name="end" min="0" placeholder="end height" value="${escapeHtml(end)}" required>
                <button type="submit" class="btn btn-sm btn-brand">Load</button>
            </form>
            ${difficultyView}
        `, { layout: 'full', importance: 'primary', density: 'compact' })}
        ${difficultyRaw ? renderCollapsibleSection('Raw Difficulty Payload', difficultyRaw, { layout: 'full', summaryMeta: 'debug' }) : ''}
        ${renderCollapsibleSection('Tip Block Metrics', renderKeyValueTableCompact(block || {}, { maxRows: 16 }), { layout: 'half', summaryMeta: 'advanced' })}
        ${renderCollapsibleSection('Host Metrics', renderKeyValueTableCompact(host || {}, { maxRows: 16 }), { layout: 'half', summaryMeta: 'advanced' })}
        ${renderCollapsibleSection('Block Time Metrics', renderKeyValueTableCompact(blockTime || {}, { maxRows: 12 }), { layout: 'half', summaryMeta: 'advanced' })}
    `;
}

export function bindMetricsActions() {
    const form = document.getElementById('difficulty-form');
    if (!form) return;
    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const fd = new FormData(form);
        const start = String(fd.get('start') || '').trim();
        const end = String(fd.get('end') || '').trim();
        if (!start || !end) return;
        window.location.href = buildExplorerHref('metrics', { start, end });
    });
}

export async function renderPeers() {
    const peers = await getJson('/syncer/peers');
    const rows = (Array.isArray(peers) ? peers : []).map((peer) => ({ address: peer }));

    return `
        ${renderSection('Peers Summary', renderKpiStrip([
            { label: 'Connected Peers', value: formatInteger(rows.length) },
        ], { compact: true }), { layout: 'half', importance: 'primary', density: 'compact' })}
        ${renderArrayTable('Connected Peers', rows, [
            { label: 'Address', render: (row) => `<code>${escapeHtml(row.address)}</code>` },
        ], { layout: 'full', importance: 'primary', density: 'compact' })}
    `;
}

export async function renderExchange(currency) {
    const code = String(currency || '').trim().toUpperCase();
    if (!/^[A-Z]{3}$/.test(code)) {
        return renderSection('Exchange Rate', '<p class="text-danger">Currency must be a 3-letter code, e.g. USD.</p>');
    }

    const rate = await getJson(`/exchange-rate/siacoin/${encodeURIComponent(code)}`);
    const numericRate = Number(rate);
    const rateDisplay = Number.isFinite(numericRate)
        ? numericRate.toLocaleString(undefined, { maximumFractionDigits: 8 })
        : String(rate);

    return `
        ${renderSection('Exchange Rate', renderKpiStrip([
            { label: 'Pair', value: `SC/${code}` },
            { label: 'Rate', value: rateDisplay },
            { label: 'As Of', value: formatDateTime(new Date().toISOString()), html: true },
        ], { compact: true }), { layout: 'half', importance: 'primary', density: 'compact' })}
    `;
}
