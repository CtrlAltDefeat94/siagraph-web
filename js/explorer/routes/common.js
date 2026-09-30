import { ApiError } from '../api.js';

export const DEFAULT_LIMIT = 25;
const HASTINGS_PER_SC = 10n ** 24n;

export function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function getLocale() {
    return (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
}

function groupDigits(value) {
    return String(value).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function toNonNegativeBigInt(value) {
    if (typeof value === 'bigint') return value >= 0n ? value : null;
    if (typeof value === 'number') {
        if (!Number.isFinite(value) || !Number.isInteger(value) || value < 0) return null;
        return BigInt(value);
    }
    const raw = String(value ?? '').trim();
    if (!/^\d+$/.test(raw)) return null;
    try {
        return BigInt(raw);
    } catch (_err) {
        return null;
    }
}

export function formatInteger(value, fallback = 'N/A') {
    const asBigInt = toNonNegativeBigInt(value);
    if (asBigInt !== null) return groupDigits(asBigInt.toString());

    const asNumber = Number(value);
    if (!Number.isFinite(asNumber)) return fallback;
    return asNumber.toLocaleString(getLocale());
}

export function formatBoolean(value, fallback = 'N/A') {
    if (value === true) return 'Yes';
    if (value === false) return 'No';
    return fallback;
}

export function formatDurationSeconds(value, fallback = 'N/A') {
    const n = Number(value);
    if (!Number.isFinite(n)) return fallback;
    const seconds = n.toLocaleString(getLocale(), { maximumFractionDigits: 2 });
    const minutes = (n / 60).toLocaleString(getLocale(), { maximumFractionDigits: 2 });
    return `${seconds} sec (${minutes} min)`;
}

function renderScWithMeta(raw, opts = {}) {
    const whole = raw / HASTINGS_PER_SC;
    const fraction = raw % HASTINGS_PER_SC;
    const wholeDisplay = groupDigits(whole.toString());

    let fractionDisplay = '';
    if (fraction > 0n) {
        const padded = fraction.toString().padStart(24, '0');
        fractionDisplay = `.${padded.slice(0, 4).replace(/0+$/, '')}`;
        if (fractionDisplay === '.') fractionDisplay = '';
    }

    const hastingsDisplay = groupDigits(raw.toString());
    const meta = opts.hideMeta ? '' : ` <span class="text-muted">(<code>${hastingsDisplay} H</code>)</span>`;
    return `${wholeDisplay}${fractionDisplay} SC${meta}`;
}

export function formatHastings(value, opts = {}) {
    const raw = toNonNegativeBigInt(value);
    if (raw === null) return escapeHtml(opts.fallback || 'N/A');
    return renderScWithMeta(raw, opts);
}

export function formatHastingsShort(value, opts = {}) {
    const raw = toNonNegativeBigInt(value);
    if (raw === null) return escapeHtml(opts.fallback || 'N/A');
    return renderScWithMeta(raw, { hideMeta: true });
}

export function formatJson(value) {
    if (value === undefined) return 'undefined';
    try {
        return JSON.stringify(value, null, 2);
    } catch (_err) {
        return String(value);
    }
}

export function formatRelativeTime(value, fallback = 'N/A') {
    if (!value) return fallback;
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return fallback;

    const diffMs = Date.now() - d.getTime();
    const future = diffMs < 0;
    const absMs = Math.abs(diffMs);
    const sec = Math.floor(absMs / 1000);

    if (sec < 30) return future ? 'in a few seconds' : 'just now';
    if (sec < 90) return future ? 'in 1 minute' : '1 minute ago';

    const min = Math.floor(sec / 60);
    if (min < 60) return future ? `in ${min} min` : `${min} min ago`;

    const hr = Math.floor(min / 60);
    if (hr < 24) return future ? `in ${hr} hr` : `${hr} hr ago`;

    const day = Math.floor(hr / 24);
    return future ? `in ${day} day${day === 1 ? '' : 's'}` : `${day} day${day === 1 ? '' : 's'} ago`;
}

export function formatDateTime(value) {
    if (!value) return 'N/A';
    const d = new Date(value);
    if (Number.isNaN(d.getTime())) return String(value);
    const loc = getLocale();
    const local = d.toLocaleString(loc || undefined);
    const iso = d.toISOString();
    const rel = formatRelativeTime(value);
    return `<span title="${escapeHtml(iso)}">${escapeHtml(local)} <span class="text-muted">(${escapeHtml(rel)})</span></span>`;
}

export function parseRouteQuery() {
    return readExplorerRouteParamsFromLocation();
}

export function isDebugRouteEnabled(query = null) {
    const q = query || parseRouteQuery();
    const raw = String(q?.debug ?? '').trim().toLowerCase();
    return raw === '1' || raw === 'true' || raw === 'yes';
}

export function buildExplorerHref(route = '', params = {}) {
    const normalizedRoute = String(route || '').replace(/^\/+/, '').replace(/\/+$/, '');
    let base = normalizedRoute ? `/explorer/${normalizedRoute}` : '/explorer/';
    const search = new URLSearchParams();

    for (const [key, value] of Object.entries(params || {})) {
        if (value === null || value === undefined || value === '') continue;
        if (key === 'route') continue;
        search.set(String(key), String(value));
    }

    const query = search.toString();
    return query ? `${base}?${query}` : base;
}

export function readExplorerRouteFromLocation() {
    const path = String(window.location.pathname || '').trim();
    if (path === '/explorer' || path === '/explorer/') return '';
    if (path.startsWith('/explorer/')) {
        const routeFromPath = path.slice('/explorer/'.length).replace(/^\/+/, '').replace(/\/+$/, '');
        if (routeFromPath && !/\.php$/i.test(routeFromPath)) return routeFromPath;
    }

    // Legacy support for old route query.
    const params = new URLSearchParams(window.location.search || '');
    const routeQuery = String(params.get('route') || '').trim();
    if (routeQuery) return routeQuery.replace(/^\/+/, '').replace(/\/+$/, '');

    const hash = String(window.location.hash || '').trim();
    if (hash.startsWith('#/')) {
        return hash.slice(2).replace(/^\/+/, '').replace(/\/+$/, '');
    }
    if (hash.startsWith('#')) {
        const trimmed = hash.slice(1).trim();
        if (trimmed) return trimmed.replace(/^\/+/, '').replace(/\/+$/, '');
    }

    return '';
}

export function readExplorerRouteParamsFromLocation() {
    const params = new URLSearchParams(window.location.search || '');
    const out = {};

    for (const [k, v] of params.entries()) {
        if (k === 'route') continue;
        if (k.startsWith('r_')) {
            out[k.slice(2)] = v;
            continue;
        }
        out[k] = v;
    }

    return out;
}

export function toInt(value, fallback = 0, min = 0) {
    const n = Number.parseInt(String(value ?? ''), 10);
    if (!Number.isFinite(n)) return fallback;
    return Math.max(min, n);
}

export function truncateHash(value, head = 10, tail = 8) {
    const v = String(value || '');
    if (v.length <= head + tail + 1) return v;
    return `${v.slice(0, head)}...${v.slice(-tail)}`;
}

export function formatId(value, opts = {}) {
    const raw = String(value ?? '');
    const head = Number.isInteger(opts.head) ? opts.head : 12;
    const tail = Number.isInteger(opts.tail) ? opts.tail : 8;
    const display = opts.truncate === false ? raw : truncateHash(raw, head, tail);
    return `<code class="explorer-code" title="${escapeHtml(raw)}">${escapeHtml(display)}</code>`;
}

export function renderCodeValue(value, opts = {}) {
    return formatId(value, opts);
}

export function renderTagPills(items = []) {
    if (!Array.isArray(items) || items.length === 0) return '';
    return `<div class="explorer-tag-pills">${items.map((item) => `<span class="explorer-tag-pill">${escapeHtml(item)}</span>`).join('')}</div>`;
}

export function renderBadge(value, kind = 'neutral') {
    const safe = escapeHtml(value ?? 'N/A');
    const normalized = String(kind || 'neutral').toLowerCase();
    const allowed = new Set(['neutral', 'good', 'warn', 'bad', 'info']);
    const badgeKind = allowed.has(normalized) ? normalized : 'neutral';
    return `<span class="explorer-badge explorer-badge--${badgeKind}">${safe}</span>`;
}

export function smallStatGrid(items) {
    return renderKpiStrip(items, { compact: false });
}

export function renderKpiStrip(items, opts = {}) {
    const safeItems = (Array.isArray(items) ? items : []).filter((item) => item && item.label !== undefined && item.value !== undefined);
    const compactClass = opts.compact === true ? ' explorer-kpi-strip--compact' : '';

    return `
        <div class="explorer-kpi-strip${compactClass}">
            ${safeItems.map((item) => `
                <div class="explorer-kpi-card">
                    <div class="explorer-kpi-label">${escapeHtml(item.label)}</div>
                    <div class="explorer-kpi-value">${item.html ? item.value : escapeHtml(item.value)}</div>
                </div>
            `).join('')}
        </div>
    `;
}

export function keyValueTable(data) {
    return renderKeyValueTableCompact(data);
}

export function renderKeyValueTableCompact(data, opts = {}) {
    if (!data || typeof data !== 'object') return '<p>No data.</p>';

    const includeSet = Array.isArray(opts.includeKeys) ? new Set(opts.includeKeys) : null;
    const excludeSet = new Set(Array.isArray(opts.excludeKeys) ? opts.excludeKeys : []);
    const maxRows = Number.isInteger(opts.maxRows) ? opts.maxRows : 24;

    let entries = Object.entries(data);
    if (includeSet) {
        entries = entries.filter(([key]) => includeSet.has(key));
    }
    entries = entries.filter(([key]) => !excludeSet.has(key));
    if (maxRows > 0) entries = entries.slice(0, maxRows);

    const rows = entries.map(([key, value]) => {
        const rendered = renderValue(value, key);
        return `<tr><th>${escapeHtml(key)}</th><td>${rendered}</td></tr>`;
    }).join('');

    return `<div class="table-responsive"><table class="table table-dark table-clean text-white explorer-kv-table explorer-table-compact"><tbody>${rows}</tbody></table></div>`;
}

export function renderValue(value, key = '') {
    if (value === null || value === undefined) return '<span class="text-muted">null</span>';
    if (typeof value === 'string') {
        if (isPublicKeyField(key, value)) {
            return `<a href="/host?public_key=${encodeURIComponent(value)}" title="${escapeHtml(value)}">${escapeHtml(truncateHash(value, 14, 10))}</a>`;
        }
        if (looksLikeHash(value) || looksLikeAddress(value) || looksLikeFcid(value)) {
            return formatId(value, { truncate: true });
        }
        return escapeHtml(value);
    }
    if (typeof value === 'number') return escapeHtml(formatInteger(value));
    if (typeof value === 'boolean') return value ? 'true' : 'false';
    if (Array.isArray(value)) {
        if (!value.length) return '<span class="text-muted">[]</span>';
        return `<details><summary>${value.length} item(s)</summary><pre>${escapeHtml(formatJson(value))}</pre></details>`;
    }
    return `<details><summary>object</summary><pre>${escapeHtml(formatJson(value))}</pre></details>`;
}

function isPublicKeyField(key, value) {
    const normalized = String(key || '').toLowerCase();
    return (normalized === 'publickey' || normalized === 'public_key' || normalized.endsWith('publickey'))
        && /^ed25519:[0-9a-f]{64}$/i.test(value);
}

function looksLikeHash(v) {
    return /^[0-9a-f]{64}$/i.test(v);
}

function looksLikeAddress(v) {
    return /^[0-9a-f]{76}$/i.test(v);
}

function looksLikeFcid(v) {
    return /^fcid:[0-9a-f]{64}$/i.test(v);
}

export function renderSection(title, inner, opts = {}) {
    return renderSectionWithOptions(title, inner, opts);
}

function renderSectionWithOptions(title, inner, opts = {}) {
    const layout = opts.layout === 'full' ? 'full' : 'half';
    const importance = opts.importance === 'primary' ? 'primary' : 'secondary';
    const density = opts.density === 'compact' ? 'compact' : 'normal';

    if (opts.collapsible === true) {
        return renderCollapsibleSection(title, inner, {
            layout,
            importance,
            density,
            open: opts.defaultOpen === true,
            summaryMeta: opts.summaryMeta || '',
        });
    }

    const classes = `card explorer-section explorer-col explorer-col--${layout} explorer-col--${importance} explorer-density--${density}`;
    return `
        <section class="${classes}">
            <h2 class="card__heading">${escapeHtml(title)}</h2>
            <div class="card__content">${inner}</div>
        </section>
    `;
}

export function renderCollapsibleSection(title, inner, opts = {}) {
    const open = opts.open === true;
    const meta = opts.summaryMeta ? `<span class="explorer-summary-meta">${escapeHtml(opts.summaryMeta)}</span>` : '';
    const layout = opts.layout === 'full' ? 'full' : 'half';
    const importance = opts.importance === 'primary' ? 'primary' : 'secondary';
    const density = opts.density === 'compact' ? 'compact' : 'normal';
    const classes = `card explorer-section explorer-collapsible explorer-col explorer-col--${layout} explorer-col--${importance} explorer-density--${density}`;
    return `
        <section class="${classes}">
            <details ${open ? 'open' : ''}>
                <summary class="card__heading">${escapeHtml(title)}${meta}</summary>
                <div class="card__content">${inner}</div>
            </details>
        </section>
    `;
}

export function renderArrayTable(title, items, columns, opts = {}) {
    if (!Array.isArray(items) || items.length === 0) {
        return renderSectionWithOptions(title, '<p class="text-muted">No records.</p>', opts);
    }
    const head = columns.map((c) => `<th>${escapeHtml(c.label)}</th>`).join('');
    const body = items.map((row) => {
        const tds = columns.map((c) => `<td>${c.render(row)}</td>`).join('');
        return `<tr>${tds}</tr>`;
    }).join('');
    return renderSectionWithOptions(title, `
        <div class="table-responsive">
            <table class="table table-dark table-clean text-white explorer-table-compact">
                <thead><tr>${head}</tr></thead>
                <tbody>${body}</tbody>
            </table>
        </div>
    `, opts);
}

export function renderError(error, title = 'Explorer Error') {
    const status = error instanceof ApiError ? `HTTP ${error.status}` : 'Unexpected error';
    const message = error?.message || String(error);
    return `
        <section class="card explorer-section explorer-col explorer-col--full explorer-col--primary">
            <h2 class="card__heading">${escapeHtml(title)}</h2>
            <div class="card__content">
                <p><strong>${escapeHtml(status)}</strong></p>
                <p>${escapeHtml(message)}</p>
            </div>
        </section>
    `;
}

export function pager(basePath, offset, limit, hasNext) {
    const prevOffset = Math.max(0, offset - limit);
    const prevHref = buildExplorerHref(basePath, { offset: prevOffset, limit });
    const nextHref = buildExplorerHref(basePath, { offset: offset + limit, limit });
    return `
        <div class="explorer-pager">
            <a class="btn btn-sm btn-outline-light ${offset === 0 ? 'disabled' : ''}" ${offset === 0 ? '' : `href="${prevHref}"`}>Previous</a>
            <span>Offset ${offset} | Limit ${limit}</span>
            <a class="btn btn-sm btn-outline-light ${!hasNext ? 'disabled' : ''}" ${!hasNext ? '' : `href="${nextHref}"`}>Next</a>
        </div>
    `;
}
