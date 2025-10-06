document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('index-data');
    const url = '/api/v1/daily/compare_metrics';
    const cachedData = JSON.parse(dataEl?.dataset.cachedData || 'null');
    const cachedHighlights = JSON.parse(dataEl?.dataset.cachedHighlights || '{}');
    const cachedExplorer = JSON.parse(dataEl?.dataset.cachedExplorer || 'null');
    if (cachedExplorer) {
        try {
            localStorage.setItem('fetchCache:/api/v1/explorer_metrics', JSON.stringify({ timestamp: Date.now(), data: cachedExplorer }));
        } catch (err) {
            console.warn('Failed to seed explorer metrics cache', err);
        }
    }
    let currencyCookie = getCookie('currency') || 'eur';
    const timeSinceElement = document.getElementById('time-since-found');
    const blockFoundTimeElement = document.getElementById('block-found-time');
    const blockFoundTimeString = blockFoundTimeElement.textContent.trim();
    const extractedDateString = blockFoundTimeString.replace('Found at: ', '').trim();
    let currentHeight = 0;
    let blockFoundTime = blockFoundTimeElement.dataset.time || extractedDateString;
    if (blockFoundTime) {
        const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
        blockFoundTimeElement.textContent = 'Found at: ' + new Date(blockFoundTime).toLocaleString(loc);
    }
    async function fetchExplorerData() {
        const explorerData = await fetchData('/api/v1/explorer_metrics');
        if (explorerData) {
            const {
                blockHeight,
                averageFoundSeconds,
                blockFoundTime: fetchedBlockFoundTime,
                unconfirmedTransactions,
                connectedPeers,
                newHosts,
                completedContracts,
                newContracts
            } = explorerData;
            blockFoundTime = fetchedBlockFoundTime;
            let minutes = Math.floor(averageFoundSeconds / 60);
            let seconds = averageFoundSeconds % 60;
            let averageFoundTime = `Recent average: ${minutes} minutes ${seconds} seconds`;
            const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
            // Localize numeric values for display
            document.getElementById('block-height').innerText = Number(blockHeight).toLocaleString(loc);
            document.getElementById('next-block').innerText = Number(blockHeight + 1).toLocaleString(loc);
            document.getElementById('block-found-time').innerText = 'Found at: ' + new Date(blockFoundTime).toLocaleString(loc);
            document.getElementById('time-average').innerText = averageFoundTime;
            document.getElementById('unconfirmed-transactions').innerText = Number(unconfirmedTransactions).toLocaleString(loc);
            document.getElementById('connected-peers').innerText = Number(connectedPeers).toLocaleString(loc);
            document.getElementById('new-hosts').innerText = Number(newHosts).toLocaleString(loc);
            document.getElementById('completed-contracts').innerText = Number(completedContracts).toLocaleString(loc);
            document.getElementById('new-contracts').innerText = Number(newContracts).toLocaleString(loc);
        }
    }
    const CACHE_TTL = 300000; // 5 minutes

    async function fetchData(url) {
        try {
            return await fetchWithCache(url, { method: 'GET', headers: { 'Content-Type': 'application/json' } }, CACHE_TTL);
        } catch (error) {
            console.error('Error fetching data:', error.message);
            return null;
        }
    }
    async function fetchDataAndUpdateUI() {
        // Prefer cached server-rendered data when available
        let data = (cachedData && typeof cachedData === 'object' && (cachedData.actual || cachedData.change)) ? cachedData : null;
        if (!data) {
            try {
                data = await fetchWithCache(url, { method: 'GET', headers: { 'Content-Type': 'application/json' } }, CACHE_TTL);
            } catch (error) {
                console.error('Error fetching data:', error.message);
                return;
            }
        }
        if (data) {
            updateUI(data);
        }
    }
    function updateUI(data) {
        const el = {
            stats1a: document.getElementById('stats1a'),
            stats1b: document.getElementById('stats1b'),
            stats2a: document.getElementById('stats2a'),
            stats2b: document.getElementById('stats2b'),
            stats3a: document.getElementById('stats3a'),
            stats3b: document.getElementById('stats3b'),
            stats4a: document.getElementById('stats4a'),
            stats4b: document.getElementById('stats4b'),
            stats5a: document.getElementById('stats5a'),
            stats5b: document.getElementById('stats5b'),
            stats6a: document.getElementById('stats6a'),
            stats6b: document.getElementById('stats6b')
        };

        // Main values
        el.stats1a.textContent = formatBytes(data.actual.utilized_storage);
        el.stats2a.textContent = data.actual.active_contracts;
        el.stats3a.textContent = formatCurrency(data.actual['30_day_revenue'], true);
        el.stats4a.textContent = formatBytes(data.actual.total_storage);
        el.stats5a.textContent = data.actual.online_hosts;
        el.stats6a.textContent = formatCurrency(data.actual.coin_price);

        // Changes with arrows and color
        applyDelta(el.stats1b, data.change.utilized_storage, 'bytes');
        applyDelta(el.stats2b, data.change.active_contracts, 'number');
        const changeRevenue = data.change['30_day_revenue'][currencyCookie];
        applyDelta(el.stats3b, changeRevenue, 'currency', currencyCookie);
        applyDelta(el.stats4b, data.change.total_storage, 'bytes');
        applyDelta(el.stats5b, data.change.online_hosts, 'number');
        applyDelta(el.stats6b, data.change.coin_price[currencyCookie], 'currency', currencyCookie, 4);
    }
    function formatCurrency(value, divideSc = false) {
        if (currencyCookie === 'eur') return `EUR ${value.eur}`;
        if (currencyCookie === 'usd') return `USD ${value.usd}`;
        return `SC ${divideSc ? value.sc / 1e24 : value.sc}`;
    }
    function applyDelta(element, value, type = 'number', currency = null, decimals = 2) {
        // Remove up/down icons; retain color only and prepend '+' for positives
        let cls = 'text-gray-400';
        const n = Number(value) || 0;
        const plus = n > 0 ? '+' : '';
        if (n > 0) { cls = 'text-green-400'; }
        else if (n < 0) { cls = 'text-red-400'; }
        let formatted = '';
        if (type === 'bytes') {
            formatted = (plus ? '+' : '') + formatBytes(n);
        } else if (type === 'currency') {
            if (currency === 'sc') {
                formatted = `SC ${plus}${(n/1e24).toFixed(decimals)}`;
            } else if (currency === 'usd') {
                formatted = `USD ${plus}${Number(n).toFixed(decimals)}`;
            } else {
                formatted = `EUR ${plus}${Number(n).toFixed(decimals)}`;
            }
        } else {
            const loc2 = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
            formatted = `${plus}${Number(n).toLocaleString(loc2)}`;
        }
        element.textContent = `${formatted}`;
        element.className = `fs-6 ${cls}`;
    }
    function setDefaultValues() {
        const cur = currencyCookie.toUpperCase();
        const defaults = {
            stats1a: '0', stats1b: '(0)',
            stats2a: '0', stats2b: '(0)',
            stats3a: `${cur} 0`, stats3b: `(${cur} 0)`,
            stats4a: '0', stats4b: '(0)',
            stats5a: '0', stats5b: '(0)',
            stats6a: `${cur} 0`, stats6b: `(${cur} 0)`
        };
        for (const [id, value] of Object.entries(defaults)) {
            document.getElementById(id).textContent = value;
        }
    }
    function formatBytes(bytes) {
        const isNegative = bytes < 0;
        bytes = Math.abs(bytes);
        if (bytes === 0) return '0 Bytes';
        const units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        let unitIndex = 0;
        while (bytes >= 1000 && unitIndex < units.length - 1) {
            bytes /= 1000;
            unitIndex++;
        }
        const loc4 = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
        const formatted = `${Number(bytes.toFixed(3)).toLocaleString(loc4, { minimumFractionDigits: 3, maximumFractionDigits: 3 })} ${units[unitIndex]}`;
        return isNegative ? `-${formatted}` : formatted;
    }
    function prependPlusIfNeeded(input) {
        const string = input.toString();
        if (string.charAt(0) !== '-') {
            return '+' + string;
        }
        return string;
    }
    function formatNumber(num) {
        const n = Number(num);
        if (isNaN(n)) return num;
        const loc3 = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
        return n.toLocaleString(loc3);
    }
    function formatHastings(value) {
        return formatSC(value / 1e24);
    }
    async function fetchNetworkHighlights() {
        let metrics = cachedHighlights.metrics && cachedHighlights.metrics.length ? cachedHighlights.metrics : null;
        let aggregates = cachedHighlights.aggregates && cachedHighlights.aggregates.length ? cachedHighlights.aggregates : null;
        if (!metrics) {
            metrics = await fetchData('/api/v1/daily/metrics');
        }
        if (!aggregates) {
            aggregates = await fetchData('/api/v1/daily/aggregates');
        }
        if (metrics && metrics.length) {
            const latestM = metrics[metrics.length - 1];
            document.getElementById('highlight-circulating-supply').innerText = formatHastings(latestM.circulating_supply);
            document.getElementById('highlight-successful-contracts').innerText = formatNumber(latestM.total_successful_contracts);
        }
        if (aggregates && aggregates.length) {
            const latestA = aggregates[aggregates.length - 1];
            document.getElementById('highlight-blocks-mined').innerText = formatNumber(latestA.blocks_mined);
            document.getElementById('highlight-contract-revenue').innerText = formatHastings(latestA.contract_revenue.sc);
            document.getElementById('highlight-total-fees').innerText = formatHastings(latestA.total_fees);
            document.getElementById('highlight-avg-difficulty').innerText = formatNumber(latestA.avg_difficulty);
        }
    }
    function updateTimeSinceFound() {
        const now = new Date();
        const blockFoundTimeDate = new Date(blockFoundTime);
        const elapsed = Math.floor((now - blockFoundTimeDate) / 1000);
        const days = Math.floor(elapsed / 86400);
        const hours = String(Math.floor((elapsed % 86400) / 3600)).padStart(2, '0');
        const minutes = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
        const seconds = String(elapsed % 60).padStart(2, '0');
        if (days > 0) {
            timeSinceElement.textContent = `Time since: ${days} days ${hours}:${minutes}:${seconds}`;
        } else {
            timeSinceElement.textContent = `Time since: ${hours}:${minutes}:${seconds}`;
        }
    }
    // Only fetch and update via JS if no cached server data
    if (!(cachedData && typeof cachedData === 'object' && (cachedData.actual || cachedData.change))) {
        fetchDataAndUpdateUI();
    }
    fetchExplorerData();
    fetchNetworkHighlights();
    setInterval(updateTimeSinceFound, 1000);
    setInterval(fetchExplorerData, 30000);
    setInterval(fetchNetworkHighlights, 30000);
    document.addEventListener('currencyChange', function(e) {
        currencyCookie = e.detail;
        fetchDataAndUpdateUI();
    });
});
