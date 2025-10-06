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

// Highlight active nav link based on current path
document.addEventListener('DOMContentLoaded', () => {
    try {
        const pathname = window.location.pathname;
        const base = pathname.substring(pathname.lastIndexOf('/') + 1) || 'index.php';
        const isHome = base === '' || base === 'index.php';

        const links = document.querySelectorAll('header .nav-link, header .dropdown-item');
        links.forEach(link => {
            let href = link.getAttribute('href') || '';
            // Normalize href to basename
            if (href.startsWith('/')) {
                href = href.substring(href.lastIndexOf('/') + 1);
            }
            const linkIsHome = href === '/' || href === '' || href === 'index.php';
            const match = (isHome && linkIsHome) || (!isHome && href === base);
            if (match) {
                link.classList.add('active');
                link.setAttribute('aria-current', 'page');
                // Also highlight dropdown toggle parent if present
                const dropdown = link.closest('.dropdown');
                if (dropdown) {
                    const toggle = dropdown.querySelector('.nav-link.dropdown-toggle');
                    if (toggle) toggle.classList.add('active');
                }
            }
        });
    } catch (e) {
        // no-op
    }
});




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

// No custom navbar JS needed; Bootstrap handles collapse/dropdowns
