// script.js
function displayStorageMetric(id, actualValue, changeValue) {
    const actualElement = document.getElementById(id + 'a');
    const changeElement = document.getElementById(id + 'b');

    if (actualElement && changeElement) {
        actualElement.textContent = actualValue;
        changeElement.textContent = (changeValue > 0) ? ` (+${changeValue})` : ` (${changeValue})`;
    } else {
        console.error('Elements with id ' + id + 'a or ' + id + 'b not found.');
    }
}

function normalizeRouteToPage(pathname) {
    if (!pathname) return 'index.php';
    const normalizedPath = pathname.replace(/\/+$/, '');
    const last = normalizedPath.substring(normalizedPath.lastIndexOf('/') + 1);
    if (last === '' || last === 'index.php') return 'index.php';
    if (last.includes('.')) return last.toLowerCase();
    return last.toLowerCase();
}

function normalizeHrefToPage(href) {
    if (!href || href === '#') return '';
    if (href === '/' || href === '/index.php' || href === 'index.php') return 'index.php';
    const trimmed = href.split('?')[0].replace(/\/+$/, '');
    const last = trimmed.substring(trimmed.lastIndexOf('/') + 1);
    return (last || 'index.php').toLowerCase();
}

// Highlight active nav link based on current path
document.addEventListener('DOMContentLoaded', () => {
    try {
        const currentPage = normalizeRouteToPage(window.location.pathname);
        const isHome = currentPage === 'index.php';
        const links = document.querySelectorAll('header .nav-link, header .dropdown-item');

        links.forEach(link => {
            const targetPage = normalizeHrefToPage(link.getAttribute('href') || '');
            const match = (isHome && targetPage === 'index.php') || (!isHome && targetPage === currentPage);
            if (!match) return;
            link.classList.add('active');
            link.setAttribute('aria-current', 'page');
            const dropdown = link.closest('.dropdown');
            if (dropdown) {
                const toggle = dropdown.querySelector('.nav-link.dropdown-toggle');
                if (toggle) toggle.classList.add('active');
            }
        });
    } catch (e) {
        // no-op
    }
});

// Dev-only route normalization sanity checks
if (typeof window !== 'undefined' && window.__RUN_NAV_TESTS === true) {
    console.assert(normalizeRouteToPage('/') === 'index.php', 'route test: home slash');
    console.assert(normalizeRouteToPage('/index.php') === 'index.php', 'route test: home php');
    console.assert(normalizeRouteToPage('/host_explorer/') === 'host_explorer', 'route test: trailing slash');
    console.assert(normalizeHrefToPage('/host_explorer') === 'host_explorer', 'href test: absolute path');
    console.assert(normalizeHrefToPage('host_explorer') === 'host_explorer', 'href test: bare page');
}




let currency = getCookie('currency') || 'eur';

function updateCurrencyIndicator() {
    const el = document.getElementById('current-currency');
    if (el) {
        el.textContent = currency.toUpperCase();
    }
}

function setCurrency(newCurrency) {
    currency = newCurrency;
    document.cookie =
        "currency=" + currency + "; expires=Fri, 31 Dec 9999 23:59:59 GMT; path=/";
    updateCurrencyIndicator();
    if (window.graphInstances) {
        Object.values(window.graphInstances).forEach(inst => {
            inst.setCurrency(currency);
            inst.setFiat(currency !== 'sc');
        });
    }
    document.dispatchEvent(new CustomEvent('currencyChange', { detail: currency }));
}

function switchEuroDollar() {
    const current = getCookie('currency');
    const next = current === 'usd' || current === null ? 'eur' : 'usd';
    setCurrency(next);
}

    // Helper function to get the value of a cookie by name
    function getCookie(name) {
        var cookieArr = document.cookie.split('; ');
        for (var i = 0; i < cookieArr.length; i++) {
            var cookiePair = cookieArr[i].split('=');
            if (cookiePair[0] === name) {
                return cookiePair[1];
            }
        }
        return null;
    }
function getLocalizedTime(timestamp) {
    // Create a new Date object from the provided UTC timestamp
    const date = new Date(timestamp);
    const options = {
        timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
    };

    const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
    return date.toLocaleString(loc || undefined, options);
}

/**
 * Fetch JSON data with localStorage caching.
 * @param {string} url Request URL
 * @param {Object} options Fetch options
 * @param {number} ttl Cache lifetime in milliseconds (default 1 hour)
 * @returns {Promise<any>} Parsed JSON response
 */
async function fetchWithCache(url, options = {}, ttl = 3600000, parseAs = 'json') {
    const version = (typeof window !== 'undefined' && window.FETCH_CACHE_VERSION) ? window.FETCH_CACHE_VERSION : 'v1';
    const cacheKey = `fetchCache:${version}:${url}`;
    try {
        const cached = localStorage.getItem(cacheKey);
        if (cached) {
            const { timestamp, data } = JSON.parse(cached);
            if (Date.now() - timestamp < ttl) {
                return data;
            }
        }
    } catch (err) {
        console.warn('Failed to parse cache', err);
    }

    const response = await fetch(url, options);
    if (!response.ok) {
        throw new Error(`Unexpected HTTP code: ${response.status}`);
    }
    const data = parseAs === 'text' ? await response.text() : await response.json();
    try {
        localStorage.setItem(cacheKey, JSON.stringify({ timestamp: Date.now(), data }));
    } catch (err) {
        // Ignore storage errors
    }
    return data;
}

window.currencyDisplay = window.currencyDisplay || {};
window.globalHistoricalRates = window.globalHistoricalRates || {};
window.globalSpotRates = window.globalSpotRates || { usd: null, eur: null };
window.currencyDisplayState = window.currencyDisplayState || { ratesReady: false };

window.currencyDisplay.formatFiatWithScTooltip = function formatFiatWithScTooltip(opts = {}) {
    const currencyCode = String(opts.currency || getCookie('currency') || 'eur').toLowerCase();
    const suffix = typeof opts.suffix === 'string' ? opts.suffix : '';
    const decimals = Number.isInteger(opts.decimals) ? opts.decimals : 2;
    const scDecimals = Number.isInteger(opts.scDecimals) ? opts.scDecimals : 2;
    const scValue = Number(opts.scValue);
    const fiatFromPayload = (opts.fiatValue !== undefined && opts.fiatValue !== null) ? Number(opts.fiatValue) : null;
    const rate = opts.rate !== undefined && opts.rate !== null ? Number(opts.rate) : null;
    const hasSc = Number.isFinite(scValue);
    const hasFiatPayload = fiatFromPayload !== null && Number.isFinite(fiatFromPayload);
    const hasRate = Number.isFinite(rate) && rate > 0;
    const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;

    if (!hasSc && !hasFiatPayload) return 'N/A';
    if (currencyCode === 'sc') {
        if (!hasSc) return 'N/A';
        return `${Number(scValue).toLocaleString(loc, { minimumFractionDigits: scDecimals, maximumFractionDigits: scDecimals })} SC${suffix}`;
    }

    let fiatValue = hasFiatPayload ? fiatFromPayload : null;
    if (fiatValue === null && hasSc && hasRate) {
        fiatValue = scValue * rate;
    }

    if (fiatValue === null || !Number.isFinite(fiatValue)) {
        if (!hasSc) return 'N/A';
        return `${Number(scValue).toLocaleString(loc, { minimumFractionDigits: scDecimals, maximumFractionDigits: scDecimals })} SC${suffix}`;
    }

    const fiatText = `${currencyCode.toUpperCase()} ${Number(fiatValue).toLocaleString(loc, { minimumFractionDigits: decimals, maximumFractionDigits: decimals })}${suffix}`;
    if (!hasSc) return fiatText;
    const scText = `${Number(scValue).toLocaleString(loc, { minimumFractionDigits: scDecimals, maximumFractionDigits: scDecimals })} SC${suffix}`;
    const title = `SC value: ${scText.replace(/"/g, '&quot;')}`;
    return `<span title="${title}">${fiatText}</span>`;
};

window.currencyDisplay.normalizeScValue = function normalizeScValue(value) {
    if (value === null || value === undefined) return null;
    let candidate = value;
    if (typeof candidate === 'object') {
        if (candidate.sc !== undefined && candidate.sc !== null) {
            candidate = candidate.sc;
        } else if (candidate.hastings !== undefined && candidate.hastings !== null) {
            const hastings = Number(candidate.hastings);
            return Number.isFinite(hastings) ? hastings / 1e24 : null;
        } else if (candidate.value !== undefined && candidate.value !== null) {
            candidate = candidate.value;
        } else {
            return null;
        }
    }
    const n = Number(candidate);
    if (!Number.isFinite(n)) return null;
    return n > 1e18 ? (n / 1e24) : n;
};

window.currencyDisplay.getRateForDate = function getRateForDate(date, currencyCode) {
    const code = String(currencyCode || '').toLowerCase();
    const key = String(date || '').slice(0, 10);
    if (key && window.globalHistoricalRates[key] && Number.isFinite(Number(window.globalHistoricalRates[key][code]))) {
        return Number(window.globalHistoricalRates[key][code]);
    }
    const spot = window.globalSpotRates && window.globalSpotRates[code];
    if (Number.isFinite(Number(spot))) return Number(spot);
    return null;
};

window.currencyDisplay.resolveRateForEntryDate = function resolveRateForEntryDate(date, currencyCode, localRates) {
    const code = String(currencyCode || '').toLowerCase();
    const dateKey = String(date || '').slice(0, 10);
    const local = localRates || null;
    if (local && dateKey) {
        const candidates = [
            local[dateKey],
            local[`${dateKey}T00:00:00Z`],
            date ? local[String(date)] : null
        ];
        for (const candidate of candidates) {
            if (candidate && Number.isFinite(Number(candidate[code]))) {
                return Number(candidate[code]);
            }
        }
    }
    return window.currencyDisplay.getRateForDate(date, code);
};

async function ensureGlobalCurrencyRates() {
    try {
        if (!window.currencyDisplayState.ratesReady) {
            const rows = await fetchWithCache('/api/v1/daily/exchange_rate', {}, 86400000);
            if (Array.isArray(rows)) {
                rows.forEach((row) => {
                    if (!row || !row.date) return;
                    const key = String(row.date).slice(0, 10);
                    window.globalHistoricalRates[key] = {
                        usd: Number.isFinite(Number(row.usd)) ? Number(row.usd) : null,
                        eur: Number.isFinite(Number(row.eur)) ? Number(row.eur) : null
                    };
                });
            }
            window.currencyDisplayState.ratesReady = true;
        }

        const compare = await fetchWithCache('/api/v1/daily/compare_metrics', {}, 3600000);
        const coin = compare && compare.actual && compare.actual.coin_price ? compare.actual.coin_price : null;
        if (coin) {
            window.globalSpotRates.usd = Number.isFinite(Number(coin.usd)) ? Number(coin.usd) : window.globalSpotRates.usd;
            window.globalSpotRates.eur = Number.isFinite(Number(coin.eur)) ? Number(coin.eur) : window.globalSpotRates.eur;
        }
        document.dispatchEvent(new CustomEvent('globalRatesReady'));
    } catch (err) {
        console.warn('Global currency rate bootstrap failed', err);
    }
}

document.addEventListener('DOMContentLoaded', () => {
    ensureGlobalCurrencyRates();
});

// No custom navbar JS needed; Bootstrap handles collapse/dropdowns
