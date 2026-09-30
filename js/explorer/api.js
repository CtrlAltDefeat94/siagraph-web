const RAW_BASE = (typeof window !== 'undefined' ? window.EXPLORER_API_BASE : '') || '';
export const API_BASE = String(RAW_BASE).replace(/\/+$/, '');

const ALLOWED_POST_PATHS = new Set([
    '/transactions',
    '/v2/transactions',
    '/contracts',
    '/v2/contracts',
]);

export class ApiError extends Error {
    constructor(status, message, method, path, body = null) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.method = method;
        this.path = path;
        this.body = body;
    }
}

function buildUrl(path) {
    if (!API_BASE) {
        throw new ApiError(0, 'Explorer API base URL is not configured.', 'GET', path);
    }
    if (!path || typeof path !== 'string' || path[0] !== '/') {
        throw new ApiError(0, `Invalid API path: ${String(path)}`, 'GET', String(path));
    }
    return `${API_BASE}${path}`;
}

async function requestJson(method, path, body) {
    const url = buildUrl(path);
    const init = {
        method,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
        },
    };
    if (body !== undefined) {
        init.body = JSON.stringify(body);
    }

    let response;
    try {
        response = await fetch(url, init);
    } catch (err) {
        throw new ApiError(0, `Network error while calling ${path}`, method, path, err?.message || null);
    }

    const text = await response.text();
    let parsed = null;
    if (text) {
        try {
            parsed = JSON.parse(text);
        } catch (_err) {
            parsed = text;
        }
    }

    if (!response.ok) {
        const detail = typeof parsed === 'object' && parsed && parsed.message
            ? `: ${parsed.message}`
            : '';
        throw new ApiError(response.status, `API ${response.status} for ${path}${detail}`, method, path, parsed);
    }

    return parsed;
}

export async function getJson(path) {
    return requestJson('GET', path);
}

export async function postJson(path, body) {
    if (!ALLOWED_POST_PATHS.has(path)) {
        throw new ApiError(0, `POST path is out of scope: ${path}`, 'POST', path);
    }
    return requestJson('POST', path, body);
}

export function encodePath(value) {
    return encodeURIComponent(String(value || '').trim());
}
