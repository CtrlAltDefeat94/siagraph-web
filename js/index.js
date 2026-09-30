document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('index-data');
    const compareUrl = '/api/v1/daily/compare_metrics';
    const explorerUrl = '/api/v1/explorer_metrics';
    const cachedData = JSON.parse(dataEl?.dataset.cachedData || 'null');
    const cachedHighlights = JSON.parse(dataEl?.dataset.cachedHighlights || '{}');
    const cachedExplorer = JSON.parse(dataEl?.dataset.cachedExplorer || 'null');

    const CACHE_TTL = 300000;
    let currencyCookie = getCookie('currency') || 'eur';
    let blockFoundTime = document.getElementById('block-found-time')?.dataset.time || '';
    let latestCompareData = cachedData || null;
    const hasServerRenderedCompareData = !!(cachedData && typeof cachedData === 'object' && cachedData.actual && cachedData.change);

    const timeSinceElement = document.getElementById('time-since-found');

    if (cachedExplorer) {
        try {
            const version = (typeof window !== 'undefined' && window.FETCH_CACHE_VERSION) ? window.FETCH_CACHE_VERSION : 'v1';
            localStorage.setItem(`fetchCache:${version}:${explorerUrl}`, JSON.stringify({ timestamp: Date.now(), data: cachedExplorer }));
        } catch (err) {
            console.warn('Failed to seed explorer metrics cache', err);
        }
    }

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    const setHtml = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.innerHTML = value;
    };

    const formatNumber = (num, decimals = 0) => {
        const n = Number(num);
        if (!Number.isFinite(n)) return 'N/A';
        const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
        return n.toLocaleString(loc, { minimumFractionDigits: decimals, maximumFractionDigits: decimals });
    };

    const formatSignedNumber = (num, decimals = 0) => {
        const n = Number(num);
        if (!Number.isFinite(n)) return 'N/A';
        const sign = n > 0 ? '+' : '';
        return `${sign}${formatNumber(n, decimals)}`;
    };

    const formatPercent = (ratio) => {
        const n = Number(ratio);
        if (!Number.isFinite(n)) return 'N/A';
        return `${(n * 100).toFixed(2)}%`;
    };

    function formatBytes(bytes) {
        const n = Number(bytes);
        if (!Number.isFinite(n)) return 'N/A';
        const isNegative = n < 0;
        let value = Math.abs(n);
        if (value === 0) return '0 Bytes';
        const units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        let unitIndex = 0;
        while (value >= 1000 && unitIndex < units.length - 1) {
            value /= 1000;
            unitIndex += 1;
        }
        const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
        const formatted = `${Number(value.toFixed(3)).toLocaleString(loc, { minimumFractionDigits: 3, maximumFractionDigits: 3 })} ${units[unitIndex]}`;
        return isNegative ? `-${formatted}` : formatted;
    }

    const formatCurrencyFromBucket = (bucket, divideSc = false, fiatDecimals = 2, scDecimals = null) => {
        if (!bucket || typeof bucket !== 'object') return 'N/A';
        if (currencyCookie === 'eur') return `EUR ${formatNumber(bucket.eur, fiatDecimals)}`;
        if (currencyCookie === 'usd') return `USD ${formatNumber(bucket.usd, fiatDecimals)}`;
        const sc = Number(bucket.sc);
        if (!Number.isFinite(sc)) return 'N/A';
        const resolvedScDecimals = Number.isInteger(scDecimals) ? scDecimals : (divideSc ? 2 : 6);
        return `SC ${formatNumber(divideSc ? sc / 1e24 : sc, resolvedScDecimals)}`;
    };

    async function fetchFresh(url, options = {}, parseAs = 'json') {
        try {
            const response = await fetch(url, options);
            if (!response.ok) throw new Error(`Unexpected HTTP code: ${response.status}`);
            const data = parseAs === 'text' ? await response.text() : await response.json();
            try {
                const version = (typeof window !== 'undefined' && window.FETCH_CACHE_VERSION) ? window.FETCH_CACHE_VERSION : 'v1';
                localStorage.setItem(`fetchCache:${version}:${url}`, JSON.stringify({ timestamp: Date.now(), data }));
            } catch (_) {}
            return data;
        } catch (error) {
            console.error('Error fetching data:', error.message);
            return null;
        }
    }

    async function fetchCached(url) {
        try {
            return await fetchWithCache(url, { method: 'GET', headers: { 'Content-Type': 'application/json' } }, CACHE_TTL);
        } catch (error) {
            console.error('Error fetching data:', error.message);
            return null;
        }
    }

    function applyDelta(element, value, type = 'number', currency = null, decimals = 2) {
        if (!element) return;
        let cls = 'text-gray-400';
        const n = Number(value) || 0;
        const plus = n > 0 ? '+' : '';
        if (n > 0) cls = 'text-green-400';
        else if (n < 0) cls = 'text-red-400';
        let formatted = '';
        if (type === 'bytes') {
            formatted = (plus ? '+' : '') + formatBytes(n);
        } else if (type === 'currency') {
            if (currency === 'sc') formatted = `SC ${plus}${formatNumber(n / 1e24, decimals)}`;
            else if (currency === 'usd') formatted = `USD ${plus}${formatNumber(n, decimals)}`;
            else formatted = `EUR ${plus}${formatNumber(n, decimals)}`;
        } else {
            formatted = `${plus}${formatNumber(n, 0)}`;
        }
        element.textContent = `(${formatted})`;
        element.className = `fs-6 ${cls}`;
    }

    function updateUI(data) {
        if (!data || !data.actual || !data.change) return;
        latestCompareData = data;

        setText('stats1a', formatBytes(data.actual.utilized_storage));
        setText('stats2a', formatNumber(data.actual.active_contracts, 0));
        setText('stats3a', formatCurrencyFromBucket(data.actual['30_day_revenue'], true, 2, 2));
        setText('stats4a', formatBytes(data.actual.total_storage));
        setText('stats5a', formatNumber(data.actual.online_hosts, 0));
        setText('stats6a', formatCurrencyFromBucket(data.actual.coin_price, false, 6, 0));

        applyDelta(document.getElementById('stats1b'), data.change.utilized_storage, 'bytes');
        applyDelta(document.getElementById('stats2b'), data.change.active_contracts, 'number');
        applyDelta(document.getElementById('stats3b'), data.change['30_day_revenue'][currencyCookie], 'currency', currencyCookie);
        applyDelta(document.getElementById('stats4b'), data.change.total_storage, 'bytes');
        applyDelta(document.getElementById('stats5b'), data.change.online_hosts, 'number');
        applyDelta(document.getElementById('stats6b'), data.change.coin_price[currencyCookie], 'currency', currencyCookie, 6);
    }

    function renderOpsStrip(explorerData) {
        if (!explorerData) return;
        const avg = Number(explorerData.averageFoundSeconds);
        const unconfirmed = Number(explorerData.unconfirmedTransactions);
        const connected = Number(explorerData.connectedPeers);

        let blockTier = 'Cadence: N/A';
        let txTier = 'Tx pressure: N/A';
        let peerTier = 'Peer connectivity: N/A';

        if (Number.isFinite(avg)) {
            if (avg <= 900) blockTier = `Block cadence: Healthy (${Math.round(avg)}s)`;
            else if (avg <= 1800) blockTier = `Block cadence: Moderate (${Math.round(avg)}s)`;
            else blockTier = `Block cadence: Slow (${Math.round(avg)}s)`;
        }
        if (Number.isFinite(unconfirmed)) {
            if (unconfirmed < 50) txTier = `Tx pressure: Low (${unconfirmed})`;
            else if (unconfirmed < 250) txTier = `Tx pressure: Moderate (${unconfirmed})`;
            else txTier = `Tx pressure: High (${unconfirmed})`;
        }
        if (Number.isFinite(connected)) {
            if (connected >= 40) peerTier = `Peer connectivity: Healthy (${connected})`;
            else if (connected >= 20) peerTier = `Peer connectivity: Moderate (${connected})`;
            else peerTier = `Peer connectivity: Low (${connected})`;
        }

        setText('ops-block-tier', blockTier);
        setText('ops-tx-tier', txTier);
        setText('ops-peer-tier', peerTier);
    }

    function renderInsights(metrics, aggregates) {
        if (!window.sgInsights) return;
        const churn = window.sgInsights.computeHostChurn(metrics || []);
        const funnel = window.sgInsights.computeContractFunnel(aggregates || []);
        const quality = window.sgInsights.computeRevenueQuality(latestCompareData, aggregates || []);

        setText('insight-host-churn-net', churn.netChangeLatest === null ? 'N/A' : formatSignedNumber(churn.netChangeLatest, 0));
        setText('insight-host-churn-7d', churn.churn7d === null ? 'N/A' : formatNumber(churn.churn7d, 2));
        setText('insight-host-churn-30d', churn.churn30d === null ? 'N/A' : formatNumber(churn.churn30d, 2));

        setText('insight-funnel-success', formatPercent(funnel.successRate));
        setText('insight-funnel-renewal', formatPercent(funnel.renewalRate));
        setText('insight-funnel-failure', formatPercent(funnel.failureRate));

        setText('insight-rq-burn', formatPercent(quality.burnToRevenue));
        setText('insight-rq-fee', formatPercent(quality.feesToRevenue));

        const rpacEl = document.getElementById('insight-rq-rpac');
        if (!rpacEl) return;
        if (!Number.isFinite(Number(quality.revenuePerActiveContractSc))) {
            rpacEl.textContent = 'N/A';
            return;
        }

        const scCoins = Number(quality.revenuePerActiveContractSc) / 1e24;
        if (currencyCookie === 'sc') {
            rpacEl.textContent = `${formatNumber(scCoins, 4)} SC`;
            return;
        }

        const rate = latestCompareData && latestCompareData.actual && latestCompareData.actual.coin_price
            ? Number(latestCompareData.actual.coin_price[currencyCookie])
            : null;

        if (window.currencyDisplay && typeof window.currencyDisplay.formatFiatWithScTooltip === 'function') {
            rpacEl.innerHTML = window.currencyDisplay.formatFiatWithScTooltip({
                scValue: scCoins,
                currency: currencyCookie,
                rate: Number.isFinite(rate) ? rate : null,
                decimals: 2,
                scDecimals: 4
            });
        } else {
            rpacEl.textContent = `${currencyCookie.toUpperCase()} ${Number.isFinite(rate) ? formatNumber(scCoins * rate, 2) : 'N/A'}`;
        }
    }

    async function fetchExplorerData() {
        const explorerData = await fetchFresh(explorerUrl, { method: 'GET', headers: { 'Content-Type': 'application/json' } });
        if (!explorerData) return;

        blockFoundTime = explorerData.blockFoundTime || blockFoundTime;
        const averageFoundSeconds = Number(explorerData.averageFoundSeconds);
        const minutes = Number.isFinite(averageFoundSeconds) ? Math.floor(averageFoundSeconds / 60) : 0;
        const seconds = Number.isFinite(averageFoundSeconds) ? Math.round(averageFoundSeconds % 60) : 0;
        const averageFoundTime = `Recent average: ${minutes} minutes ${seconds} seconds`;
        const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;

        setText('block-height', Number(explorerData.blockHeight || 0).toLocaleString(loc));
        setText('next-block', Number((explorerData.blockHeight || 0) + 1).toLocaleString(loc));
        setText('block-found-time', 'Found at: ' + new Date(blockFoundTime).toLocaleString(loc));
        setText('time-average', averageFoundTime);
        setText('unconfirmed-transactions', Number(explorerData.unconfirmedTransactions || 0).toLocaleString(loc));
        setText('connected-peers', Number(explorerData.connectedPeers || 0).toLocaleString(loc));
        setText('new-hosts', Number(explorerData.newHosts || 0).toLocaleString(loc));
        setText('completed-contracts', Number(explorerData.completedContracts || 0).toLocaleString(loc));
        setText('new-contracts', Number(explorerData.newContracts || 0).toLocaleString(loc));

        renderOpsStrip(explorerData);
    }

    async function fetchDataAndUpdateUI() {
        let data = (cachedData && typeof cachedData === 'object' && (cachedData.actual || cachedData.change)) ? cachedData : null;
        if (!data) {
            data = await fetchCached(compareUrl);
        }
        if (data) updateUI(data);
    }

    async function fetchNetworkHighlights() {
        let metrics = cachedHighlights.metrics && cachedHighlights.metrics.length ? cachedHighlights.metrics : null;
        let aggregates = cachedHighlights.aggregates && cachedHighlights.aggregates.length ? cachedHighlights.aggregates : null;
        if (!metrics) metrics = await fetchCached('/api/v1/daily/metrics');
        if (!aggregates) aggregates = await fetchCached('/api/v1/daily/aggregates');

        if (metrics && metrics.length) {
            const latestM = metrics[metrics.length - 1];
            setText('highlight-circulating-supply', formatNumber((Number(latestM.circulating_supply) || 0) / 1e24, 0) + ' SC');
            setText('highlight-successful-contracts', formatNumber(latestM.total_successful_contracts, 0));
        }

        if (aggregates && aggregates.length) {
            const latestA = aggregates[aggregates.length - 1];
            setText('highlight-blocks-mined', formatNumber(latestA.blocks_mined, 0));
            setText('highlight-contract-revenue', formatNumber((Number(latestA.contract_revenue?.sc) || 0) / 1e24, 0) + ' SC');
            setText('highlight-total-fees', formatNumber((Number(latestA.total_fees) || 0) / 1e24, 0) + ' SC');
            setText('highlight-avg-difficulty', formatNumber(latestA.avg_difficulty, 0));
        }

        renderInsights(metrics, aggregates);
    }

    function updateTimeSinceFound() {
        if (!blockFoundTime || !timeSinceElement) return;
        const now = new Date();
        const found = new Date(blockFoundTime);
        const elapsed = Math.floor((now - found) / 1000);
        if (!Number.isFinite(elapsed) || elapsed < 0) return;
        const days = Math.floor(elapsed / 86400);
        const hours = String(Math.floor((elapsed % 86400) / 3600)).padStart(2, '0');
        const minutes = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
        const seconds = String(elapsed % 60).padStart(2, '0');
        timeSinceElement.textContent = days > 0
            ? `Time since: ${days} days ${hours}:${minutes}:${seconds}`
            : `Time since: ${hours}:${minutes}:${seconds}`;
    }

    if (!hasServerRenderedCompareData) {
        fetchDataAndUpdateUI();
    }

    fetchExplorerData();
    fetchNetworkHighlights();

    setInterval(updateTimeSinceFound, 1000);
    setInterval(fetchExplorerData, 30000);
    setInterval(fetchNetworkHighlights, 30000);

    document.addEventListener('currencyChange', (e) => {
        currencyCookie = String(e.detail || 'eur').toLowerCase();
        if (!hasServerRenderedCompareData) {
            fetchDataAndUpdateUI();
        }
        fetchNetworkHighlights();
    });
});
