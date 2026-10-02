import { ApiError } from './api.js';
import { parseRouteQuery, readExplorerRouteFromLocation, renderError, renderSection } from './routes/common.js';
import {
    renderAddress,
    renderBlockByHeight,
    renderBlockById,
    renderContract,
    renderEvent,
    renderOutput,
    renderSearch,
    renderTx,
} from './routes/entities.js';
import {
    bindConsensusActions,
    bindMetricsActions,
    renderConsensus,
    renderExchange,
    renderMetrics,
    renderPeers,
    renderTxpool,
} from './routes/system.js';

function getRoute() {
    const routeFromLocation = readExplorerRouteFromLocation();
    if (routeFromLocation) return routeFromLocation;

    const raw = (window.EXPLORER_ROUTE || '').toString().trim();
    return raw.replace(/^\/+/, '').replace(/\/+$/, '');
}

function splitRoute(route) {
    if (!route) return [];
    return route.split('/').map((s) => decodeURIComponent(s));
}

function setContent(html) {
    const container = document.getElementById('explorer-content');
    if (!container) return;
    container.innerHTML = html;
}

async function renderRoute() {
    const route = getRoute();
    const parts = splitRoute(route);
    const q = parseRouteQuery();

    if (parts.length === 0) {
        setContent(renderSection('Explorer Search', '<p>The explorer homepage is disabled. Use the search box on the main website homepage.</p>'));
        return;
    }

    if (parts[0] === 'search' && parts[1]) {
        setContent(await renderSearch(parts.slice(1).join('/')));
        return;
    }

    if (parts[0] === 'block' && parts[1]) {
        setContent(await renderBlockById(parts[1]));
        return;
    }

    if (parts[0] === 'height' && parts[1]) {
        setContent(await renderBlockByHeight(parts[1]));
        return;
    }

    if (parts[0] === 'tx' && parts[1]) {
        setContent(await renderTx(parts[1], false));
        return;
    }

    if (parts[0] === 'v2-tx' && parts[1]) {
        setContent(await renderTx(parts[1], true));
        return;
    }

    if (parts[0] === 'address' && parts[1]) {
        setContent(await renderAddress(parts[1], q));
        return;
    }

    if (parts[0] === 'event' && parts[1]) {
        setContent(await renderEvent(parts[1]));
        return;
    }

    if (parts[0] === 'output' && parts[1] === 'siacoin' && parts[2]) {
        setContent(await renderOutput(parts[2], true));
        return;
    }

    if (parts[0] === 'output' && parts[1] === 'siafund' && parts[2]) {
        setContent(await renderOutput(parts[2], false));
        return;
    }

    if (parts[0] === 'contract' && parts[1]) {
        setContent(await renderContract(parts[1], false));
        return;
    }

    if (parts[0] === 'v2-contract' && parts[1]) {
        setContent(await renderContract(parts[1], true));
        return;
    }

    if (parts[0] === 'txpool' && parts.length === 1) {
        setContent(await renderTxpool());
        return;
    }

    if (parts[0] === 'consensus' && parts.length === 1) {
        setContent(await renderConsensus());
        bindConsensusActions();
        return;
    }

    if (parts[0] === 'metrics' && parts.length === 1) {
        setContent(await renderMetrics(q));
        bindMetricsActions();
        return;
    }

    if (parts[0] === 'peers' && parts.length === 1) {
        setContent(await renderPeers());
        return;
    }

    if (parts[0] === 'exchange' && parts[1]) {
        setContent(await renderExchange(parts[1]));
        return;
    }

    setContent(renderSection('Not Found', '<p>The explorer route was not found.</p>'));
}

async function boot() {
    try {
        await renderRoute();
    } catch (err) {
        if (err instanceof ApiError) {
            setContent(renderError(err));
            return;
        }
        setContent(renderError(err, 'Unhandled Explorer Error'));
    }
}

document.addEventListener('DOMContentLoaded', boot);
