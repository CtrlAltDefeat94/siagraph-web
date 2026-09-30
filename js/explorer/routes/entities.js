import { getJson } from '../api.js';
import {
    DEFAULT_LIMIT,
    buildExplorerHref,
    escapeHtml,
    formatBoolean,
    formatDateTime,
    formatHastings,
    formatId,
    formatInteger,
    isDebugRouteEnabled,
    pager,
    parseRouteQuery,
    renderArrayTable,
    renderBadge,
    renderCollapsibleSection,
    renderCodeValue,
    renderKpiStrip,
    renderKeyValueTableCompact,
    renderSection,
    toInt,
} from './common.js';

function renderTableOnly(items, columns) {
    if (!Array.isArray(items) || items.length === 0) {
        return '<p class="text-muted">No records.</p>';
    }
    const head = columns.map((c) => `<th>${escapeHtml(c.label)}</th>`).join('');
    const body = items.map((row) => `<tr>${columns.map((c) => `<td>${c.render(row)}</td>`).join('')}</tr>`).join('');
    return `
        <div class="table-responsive">
            <table class="table table-dark table-clean text-white explorer-table-compact">
                <thead><tr>${head}</tr></thead>
                <tbody>${body}</tbody>
            </table>
        </div>
    `;
}

function toStateBadge(value, trueLabel = 'Yes', falseLabel = 'No') {
    if (value === true) return renderBadge(trueLabel, 'good');
    if (value === false) return renderBadge(falseLabel, 'warn');
    return renderBadge('N/A', 'neutral');
}

function getDebugOpen(query = null) {
    if (query) return isDebugRouteEnabled(query);
    return isDebugRouteEnabled(parseRouteQuery());
}

function renderHostLinks(hostAnnouncements) {
    const links = hostAnnouncements.map((item) => {
        const key = item?.publicKey || item?.public_key;
        if (!key) return '<li><span class="text-muted">Unknown key</span></li>';
        return `<li><a href="/host?public_key=${encodeURIComponent(key)}">${formatId(key, { head: 16, tail: 10 })}</a></li>`;
    }).join('');

    if (!links) return '<p class="text-muted">No host announcements.</p>';
    return `<ul class="mb-0">${links}</ul>`;
}

export async function renderSearch(id) {
    const searchId = String(id || '').trim();
    if (!searchId) {
        return renderSection('Search', '<p class="text-danger">Search ID is required.</p>');
    }

    if (/^\d+$/.test(searchId)) {
        try {
            const index = await getJson(`/consensus/tip/${encodeURIComponent(searchId)}`);
            if (index && index.id) {
                const target = buildExplorerHref(`block/${encodeURIComponent(index.id)}`);
                window.location.replace(target);
                return renderSection('Search Result', `<p>Resolved height ${escapeHtml(searchId)} to block ${formatId(index.id, { head: 12, tail: 10 })}. Redirecting...</p>`);
            }
        } catch (_err) {
            return renderSection('Search Result', `<p class="text-danger">No block found at height <code>${escapeHtml(searchId)}</code>.</p>`);
        }
    }

    const type = await getJson(`/search/${encodeURIComponent(searchId)}`);

    const routes = {
        block: buildExplorerHref(`block/${encodeURIComponent(searchId)}`),
        transaction: buildExplorerHref(`tx/${encodeURIComponent(searchId)}`),
        v2Transaction: buildExplorerHref(`v2-tx/${encodeURIComponent(searchId)}`),
        address: buildExplorerHref(`address/${encodeURIComponent(searchId)}`),
        siacoinElement: buildExplorerHref(`output/siacoin/${encodeURIComponent(searchId)}`),
        siafundElement: buildExplorerHref(`output/siafund/${encodeURIComponent(searchId)}`),
        contract: buildExplorerHref(`contract/${encodeURIComponent(searchId)}`),
        v2Contract: buildExplorerHref(`v2-contract/${encodeURIComponent(searchId)}`),
        host: `/host?public_key=${encodeURIComponent(searchId)}`,
    };

    if (type === 'invalid') {
        return renderSection('Search Result', `<p>No result found for <code>${escapeHtml(searchId)}</code>.</p>`);
    }

    const target = routes[type];
    if (target) {
        window.location.replace(target);
        return renderSection('Search Result', `<p>Redirecting to ${escapeHtml(type)}...</p>`);
    }

    return renderSection('Search Result', `<p>Unknown search type: <code>${escapeHtml(String(type))}</code></p>`);
}

export async function renderBlockById(id) {
    const block = await getJson(`/blocks/${encodeURIComponent(id)}`);
    return renderBlockPage(block, null, id);
}

export async function renderBlockByHeight(height) {
    const h = String(height || '').trim();
    const index = await getJson(`/consensus/tip/${encodeURIComponent(h)}`);
    try {
        const block = await getJson(`/blocks/${encodeURIComponent(index.id)}`);
        return renderBlockPage(block, index, index?.id || null);
    } catch (err) {
        if (err?.status === 404) {
            await new Promise((r) => setTimeout(r, 350));
            const block = await getJson(`/blocks/${encodeURIComponent(index.id)}`);
            return renderBlockPage(block, index, index?.id || null);
        }
        throw err;
    }
}

async function getBlockMetricsByIdSafe(blockId) {
    if (!blockId) return null;
    try {
        return await getJson(`/metrics/block/${encodeURIComponent(blockId)}`);
    } catch (_err) {
        return null;
    }
}

async function renderBlockPage(block, index = null, blockId = null) {
    const debugOpen = getDebugOpen();
    const metrics = await getBlockMetricsByIdSafe(blockId);
    const v1Tx = Array.isArray(block?.transactions) ? block.transactions : [];
    const v2Tx = Array.isArray(block?.v2?.transactions) ? block.v2.transactions : [];
    const payouts = Array.isArray(block?.minerPayouts) ? block.minerPayouts : [];
    const metricsRows = metrics ? renderKeyValueTableCompact(metrics, {
        includeKeys: ['difficulty', 'totalHosts', 'activeContracts', 'failedContracts', 'successfulContracts', 'storageUtilization', 'contractRevenue'],
        maxRows: 12,
    }) : '<p class="text-muted">No metrics found for this block ID.</p>';

    return `
        ${renderSection('Block Summary', renderKpiStrip([
            { label: 'Height', value: formatInteger(block?.height ?? index?.height) },
            { label: 'Block ID', value: formatId(blockId || index?.id || 'N/A'), html: true },
            { label: 'Timestamp', value: formatDateTime(block?.timestamp), html: true },
            { label: 'V1 Transactions', value: formatInteger(v1Tx.length) },
            { label: 'V2 Transactions', value: formatInteger(v2Tx.length) },
            { label: 'Miner Payouts', value: formatInteger(payouts.length) },
        ], { compact: true }) + metricsRows, { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderArrayTable('V1 Transactions', v1Tx, [
            { label: 'ID', render: (row) => row?.id ? `<a href="${buildExplorerHref(`tx/${encodeURIComponent(row.id)}`)}" title="${escapeHtml(row.id)}">${renderCodeValue(row.id, { truncate: true, head: 12, tail: 8 })}</a>` : '<span class="text-muted">N/A</span>' },
            { label: 'State', render: (row) => row?.unconfirmed ? renderBadge('Pending', 'warn') : renderBadge('Confirmed', 'good') },
            { label: 'Host Announcements', render: (row) => escapeHtml(formatInteger(Array.isArray(row?.hostAnnouncements) ? row.hostAnnouncements.length : 0)) },
        ], { layout: 'half', density: 'compact' })}
        ${renderArrayTable('V2 Transactions', v2Tx, [
            { label: 'ID', render: (row) => row?.id ? `<a href="${buildExplorerHref(`v2-tx/${encodeURIComponent(row.id)}`)}" title="${escapeHtml(row.id)}">${renderCodeValue(row.id, { truncate: true, head: 12, tail: 8 })}</a>` : '<span class="text-muted">N/A</span>' },
            { label: 'State', render: (row) => row?.unconfirmed ? renderBadge('Pending', 'warn') : renderBadge('Confirmed', 'good') },
            { label: 'Host Announcements', render: (row) => escapeHtml(formatInteger(Array.isArray(row?.hostAnnouncements) ? row.hostAnnouncements.length : 0)) },
        ], { layout: 'half', density: 'compact' })}
        ${renderCollapsibleSection('Raw Block JSON', `<pre>${escapeHtml(JSON.stringify(block, null, 2))}</pre>`, { open: debugOpen, layout: 'full', summaryMeta: 'debug' })}
    `;
}

export async function renderTx(id, isV2 = false) {
    const debugOpen = getDebugOpen();
    const prefix = isV2 ? '/v2/transactions' : '/transactions';
    const [tx, indices] = await Promise.all([
        getJson(`${prefix}/${encodeURIComponent(id)}`),
        getJson(`${prefix}/${encodeURIComponent(id)}/indices?offset=0&limit=100`),
    ]);

    const hostAnnouncements = Array.isArray(tx?.hostAnnouncements) ? tx.hostAnnouncements : [];
    const indexRows = (Array.isArray(indices) ? indices : []).slice(0, 25).map((entry, idx) => ({ idx, entry }));
    const feeValue = isV2
        ? tx?.minerFee
        : (Array.isArray(tx?.minerFees) && tx.minerFees.length ? tx.minerFees[0] : tx?.minerFee);

    return `
        ${renderSection(isV2 ? 'V2 Transaction Summary' : 'Transaction Summary', renderKpiStrip([
            { label: 'ID', value: formatId(tx?.id || id), html: true },
            { label: 'Type', value: isV2 ? renderBadge('V2', 'info') : renderBadge('V1', 'info'), html: true },
            { label: 'Status', value: tx?.unconfirmed ? renderBadge('Pending', 'warn') : renderBadge('Confirmed', 'good'), html: true },
            { label: 'Indices', value: formatInteger(Array.isArray(indices) ? indices.length : 0) },
            { label: 'Miner Fee', value: formatHastings(feeValue), html: true },
        ], { compact: true }), { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderSection('Chain Indices', renderTableOnly(indexRows, [
            { label: '#', render: (row) => escapeHtml(String(row.idx)) },
            { label: 'Height', render: (row) => escapeHtml(formatInteger(row?.entry?.height)) },
            { label: 'Block ID', render: (row) => row?.entry?.id ? formatId(row.entry.id) : '<span class="text-muted">N/A</span>' },
        ]), { layout: 'half', density: 'compact' })}
        ${renderSection('Host Announcements', renderHostLinks(hostAnnouncements), { layout: 'half', density: 'compact' })}
        ${renderCollapsibleSection('Raw Transaction JSON', `<pre>${escapeHtml(JSON.stringify(tx, null, 2))}</pre>`, { open: debugOpen, layout: 'full', summaryMeta: 'debug' })}
    `;
}

export async function renderAddress(address, query = {}) {
    const debugOpen = getDebugOpen(query);
    const offset = toInt(query.offset, 0, 0);
    const limit = toInt(query.limit, DEFAULT_LIMIT, 1);
    const a = encodeURIComponent(address);

    const [balance, checkpoint, events, unconfirmed, scUtxos, sfUtxos] = await Promise.all([
        getJson(`/addresses/${a}/balance`),
        getJson(`/addresses/${a}/checkpoint`),
        getJson(`/addresses/${a}/events?offset=${offset}&limit=${limit}`),
        getJson(`/addresses/${a}/events/unconfirmed`),
        getJson(`/addresses/${a}/utxos/siacoin?offset=${offset}&limit=${limit}`),
        getJson(`/addresses/${a}/utxos/siafund?offset=${offset}&limit=${limit}`),
    ]);

    const confirmedEvents = Array.isArray(events) ? events : [];
    const unconfirmedEvents = Array.isArray(unconfirmed) ? unconfirmed : [];
    const siacoinUtxos = Array.isArray(scUtxos) ? scUtxos : [];
    const siafundUtxos = Array.isArray(sfUtxos) ? sfUtxos : [];

    const basePath = `address/${encodeURIComponent(address)}`;

    return `
        ${renderSection('Address Summary', renderKpiStrip([
            { label: 'Address', value: formatId(address, { head: 14, tail: 10 }), html: true },
            { label: 'Spendable SC', value: formatHastings(balance?.unspentSiacoins), html: true },
            { label: 'Immature SC', value: formatHastings(balance?.immatureSiacoins), html: true },
            { label: 'Siafunds', value: formatInteger(balance?.unspentSiafunds) },
            { label: 'Checkpoint Height', value: formatInteger(checkpoint?.height) },
            { label: 'Checkpoint Block', value: checkpoint?.id ? formatId(checkpoint.id) : 'N/A', html: !!checkpoint?.id },
        ], { compact: true }), { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderArrayTable('Confirmed Events', confirmedEvents, [
            { label: 'ID', render: (row) => row?.id ? `<a href="${buildExplorerHref(`event/${encodeURIComponent(row.id)}`)}" title="${escapeHtml(row.id)}">${renderCodeValue(row.id, { truncate: true, head: 12, tail: 8 })}</a>` : '<span class="text-muted">N/A</span>' },
            { label: 'Type', render: (row) => escapeHtml(row?.type ?? 'N/A') },
            { label: 'Timestamp', render: (row) => formatDateTime(row?.timestamp) },
            { label: 'Confirmations', render: (row) => escapeHtml(formatInteger(row?.confirmations)) },
        ], { layout: 'full', importance: 'primary', density: 'compact' })}
        ${pager(basePath, offset, limit, confirmedEvents.length === limit)}
        ${renderCollapsibleSection('Unconfirmed Events', renderTableOnly(unconfirmedEvents, [
            { label: 'ID', render: (row) => row?.id ? `<a href="${buildExplorerHref(`event/${encodeURIComponent(row.id)}`)}" title="${escapeHtml(row.id)}">${renderCodeValue(row.id, { truncate: true, head: 12, tail: 8 })}</a>` : '<span class="text-muted">N/A</span>' },
            { label: 'Type', render: (row) => escapeHtml(row?.type ?? 'N/A') },
            { label: 'Timestamp', render: (row) => formatDateTime(row?.timestamp) },
        ]), { layout: 'half', summaryMeta: formatInteger(unconfirmedEvents.length) })}
        ${renderCollapsibleSection('Siacoin UTXOs', renderTableOnly(siacoinUtxos, [
            { label: 'ID', render: (row) => row?.id ? `<a href="${buildExplorerHref(`output/siacoin/${encodeURIComponent(row.id)}`)}" title="${escapeHtml(row.id)}">${renderCodeValue(row.id, { truncate: true, head: 12, tail: 8 })}</a>` : '<span class="text-muted">N/A</span>' },
            { label: 'Value', render: (row) => formatHastings(row?.siacoinOutput?.value ?? row?.value) },
            { label: 'State', render: (row) => row?.spentIndex ? renderBadge('Spent', 'bad') : renderBadge('Unspent', 'good') },
        ]), { layout: 'half', summaryMeta: formatInteger(siacoinUtxos.length) })}
        ${renderCollapsibleSection('Siafund UTXOs', renderTableOnly(siafundUtxos, [
            { label: 'ID', render: (row) => row?.id ? `<a href="${buildExplorerHref(`output/siafund/${encodeURIComponent(row.id)}`)}" title="${escapeHtml(row.id)}">${renderCodeValue(row.id, { truncate: true, head: 12, tail: 8 })}</a>` : '<span class="text-muted">N/A</span>' },
            { label: 'Value', render: (row) => escapeHtml(formatInteger(row?.siafundOutput?.value ?? row?.value)) },
            { label: 'State', render: (row) => row?.spentIndex ? renderBadge('Spent', 'bad') : renderBadge('Unspent', 'good') },
        ]), { layout: 'half', summaryMeta: formatInteger(siafundUtxos.length) })}
        ${renderCollapsibleSection('Address Raw Payload', `<pre>${escapeHtml(JSON.stringify({ balance, checkpoint }, null, 2))}</pre>`, { open: debugOpen, layout: 'full', summaryMeta: 'debug' })}
    `;
}

export async function renderEvent(id) {
    const debugOpen = getDebugOpen();
    const event = await getJson(`/events/${encodeURIComponent(id)}`);

    return `
        ${renderSection('Event Summary', renderKpiStrip([
            { label: 'Event ID', value: formatId(event?.id ?? id), html: true },
            { label: 'Type', value: event?.type ?? 'N/A' },
            { label: 'Timestamp', value: formatDateTime(event?.timestamp), html: true },
            { label: 'Confirmations', value: formatInteger(event?.confirmations) },
            { label: 'Maturity Height', value: formatInteger(event?.maturityHeight) },
        ], { compact: true }), { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderSection('Event Details', renderKeyValueTableCompact(event || {}, {
            includeKeys: ['index', 'maturityHeight', 'relevant', 'type'],
            maxRows: 10,
        }), { layout: 'half', density: 'compact' })}
        ${renderSection('Status', `
            <div class="explorer-status-row">
                ${toStateBadge((event?.confirmations ?? 0) > 0, 'Confirmed', 'Pending')}
            </div>
        `, { layout: 'half', density: 'compact' })}
        ${renderCollapsibleSection('Raw Event JSON', `<pre>${escapeHtml(JSON.stringify(event, null, 2))}</pre>`, { open: debugOpen, layout: 'full', summaryMeta: 'debug' })}
    `;
}

export async function renderOutput(id, isSiacoin = true) {
    const debugOpen = getDebugOpen();
    const prefix = isSiacoin ? '/outputs/siacoin/' : '/outputs/siafund/';
    const output = await getJson(`${prefix}${encodeURIComponent(id)}`);

    const value = isSiacoin
        ? formatHastings(output?.siacoinOutput?.value ?? output?.value)
        : escapeHtml(formatInteger(output?.siafundOutput?.value ?? output?.value));

    const address = output?.siacoinOutput?.address || output?.siafundOutput?.address || output?.address || 'N/A';
    const spent = !!output?.spentIndex;

    return `
        ${renderSection(isSiacoin ? 'Siacoin Output' : 'Siafund Output', renderKpiStrip([
            { label: 'Output ID', value: formatId(output?.id || id), html: true },
            { label: 'Address', value: formatId(address, { head: 14, tail: 10 }), html: true },
            { label: 'Value', value, html: true },
            { label: 'State', value: spent ? renderBadge('Spent', 'bad') : renderBadge('Unspent', 'good'), html: true },
        ], { compact: true }), { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderSection('Output Details', renderKeyValueTableCompact(output || {}, {
            includeKeys: ['maturityHeight', 'leafIndex', 'merkleProof', 'spentIndex'],
            maxRows: 10,
        }), { layout: 'half', density: 'compact' })}
        ${renderSection('Spend Status', `<p>${escapeHtml(formatBoolean(spent))}</p>`, { layout: 'half', density: 'compact' })}
        ${renderCollapsibleSection('Raw Output JSON', `<pre>${escapeHtml(JSON.stringify(output, null, 2))}</pre>`, { open: debugOpen, layout: 'full', summaryMeta: 'debug' })}
    `;
}

export async function renderContract(id, isV2 = false) {
    const debugOpen = getDebugOpen();
    const base = isV2 ? '/v2/contracts' : '/contracts';
    const [contract, revisions] = await Promise.all([
        getJson(`${base}/${encodeURIComponent(id)}`),
        getJson(`${base}/${encodeURIComponent(id)}/revisions`),
    ]);

    const hostCandidates = [];
    collectHostKeys(contract, hostCandidates);
    (Array.isArray(revisions) ? revisions : []).forEach((item) => collectHostKeys(item, hostCandidates));

    const uniqueHostKeys = [...new Set(hostCandidates)];
    const hostHtml = uniqueHostKeys.length
        ? `<ul class="mb-0">${uniqueHostKeys.map((key) => `<li><a href="/host?public_key=${encodeURIComponent(key)}">${formatId(key, { head: 16, tail: 10 })}</a></li>`).join('')}</ul>`
        : '<p class="text-muted">No host keys found in payload.</p>';

    return `
        ${renderSection(isV2 ? 'V2 Contract Summary' : 'Contract Summary', renderKpiStrip([
            { label: 'Contract ID', value: formatId(contract?.id || id), html: true },
            { label: 'Resolved', value: toStateBadge(contract?.resolved, 'Resolved', 'Open'), html: true },
            { label: 'Valid', value: toStateBadge(contract?.valid, 'Valid', 'Invalid'), html: true },
            { label: 'Revisions', value: formatInteger(Array.isArray(revisions) ? revisions.length : 0) },
            { label: 'Payout', value: formatHastings(contract?.payout), html: true },
            { label: 'Window', value: `${formatInteger(contract?.windowStart)} - ${formatInteger(contract?.windowEnd)}` },
        ], { compact: true }), { layout: 'full', importance: 'primary', density: 'compact' })}
        ${renderSection('Contract Lifecycle', renderKeyValueTableCompact(contract || {}, {
            includeKeys: ['transactionID', 'proofHeight', 'windowStart', 'windowEnd', 'revisionNumber', 'filesize'],
            maxRows: 10,
        }), { layout: 'half', density: 'compact' })}
        ${renderSection('Contract Values', renderKeyValueTableCompact(contract || {}, {
            includeKeys: ['payout', 'validHostValue', 'missedHostValue'],
            maxRows: 8,
        }), { layout: 'half', density: 'compact' })}
        ${renderSection('Host References', hostHtml, { layout: 'half', density: 'compact' })}
        ${renderCollapsibleSection('Revisions', `<pre>${escapeHtml(JSON.stringify(revisions, null, 2))}</pre>`, { open: debugOpen, layout: 'half', summaryMeta: formatInteger(Array.isArray(revisions) ? revisions.length : 0) })}
        ${renderCollapsibleSection('Raw Contract JSON', `<pre>${escapeHtml(JSON.stringify(contract, null, 2))}</pre>`, { open: debugOpen, layout: 'full', summaryMeta: 'debug' })}
    `;
}

function collectHostKeys(value, out) {
    if (value === null || value === undefined) return;
    if (typeof value === 'string') {
        if (/^ed25519:[0-9a-f]{64}$/i.test(value)) out.push(value);
        return;
    }
    if (Array.isArray(value)) {
        value.forEach((item) => collectHostKeys(item, out));
        return;
    }
    if (typeof value === 'object') {
        for (const [k, v] of Object.entries(value)) {
            if ((k === 'publicKey' || k === 'public_key') && typeof v === 'string') {
                if (/^ed25519:[0-9a-f]{64}$/i.test(v)) out.push(v);
            }
            collectHostKeys(v, out);
        }
    }
}
