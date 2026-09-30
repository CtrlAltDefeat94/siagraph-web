document.addEventListener('DOMContentLoaded', async () => {
    const statusEl = document.getElementById('peersStatus');
    const mainTbody = document.querySelector('#mainnetTable tbody');
    const zenTbody = document.querySelector('#zenTable tbody');

    const setStatus = (message, isError = false) => {
        if (!statusEl) return;
        statusEl.textContent = message;
        statusEl.classList.toggle('text-danger', !!isError);
    };

    const escapeHtml = (value) => String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

    try {
        const data = await fetchWithCache('/api/v1/peers', {}, 300000);
        const mainnetPeers = Array.isArray(data?.mainnet) ? data.mainnet.slice(0, 5) : [];
        const zenPeers = Array.isArray(data?.zen) ? data.zen.slice(0, 5) : [];

        const createRow = (peer) => {
            const tr = document.createElement('tr');
            const address = escapeHtml(peer.address);
            const version = escapeHtml(peer.version || 'N/A');
            const lastScanned = escapeHtml(peer.last_scanned || 'N/A');
            tr.innerHTML = `
                <td>
                    <button class="copy-btn" data-text="${address}">${address}</button>
                </td>
                <td class="text-end">${version}</td>
                <td class="text-end">${lastScanned}</td>`;
            return tr;
        };

        if (mainTbody) mainTbody.innerHTML = '';
        if (zenTbody) zenTbody.innerHTML = '';
        mainnetPeers.forEach(p => mainTbody.appendChild(createRow(p)));
        zenPeers.forEach(p => zenTbody.appendChild(createRow(p)));
        if (!mainnetPeers.length && mainTbody) {
            mainTbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No recent mainnet peers found.</td></tr>';
        }
        if (!zenPeers.length && zenTbody) {
            zenTbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No recent Zen peers found.</td></tr>';
        }
        setStatus(`${mainnetPeers.length + zenPeers.length} peers shown`);

        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const ok = await copyToClipboard(btn.dataset.text);
                showToastNear(btn, ok ? 'Copied to clipboard!' : 'Copy failed');
            });
        });
    } catch (err) {
        console.error('Error fetching data', err);
        setStatus('Failed to load peers.', true);
        if (mainTbody) mainTbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Peer data unavailable.</td></tr>';
        if (zenTbody) zenTbody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">Peer data unavailable.</td></tr>';
    }
});

async function copyToClipboard(text) {
    try {
        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return true;
        }
    } catch (_) { /* fall through to legacy */ }

    // Fallback: temporary textarea + execCommand
    try {
        const ta = document.createElement('textarea');
        ta.value = text;
        ta.setAttribute('readonly', '');
        ta.style.position = 'fixed';
        ta.style.top = '-1000px';
        ta.style.left = '-1000px';
        document.body.appendChild(ta);
        ta.select();
        const ok = document.execCommand('copy');
        document.body.removeChild(ta);
        return ok;
    } catch (err) {
        console.error('Legacy copy failed', err);
        return false;
    }
}

function showToastNear(anchorEl, message) {
    if (!anchorEl) return;
    const rect = anchorEl.getBoundingClientRect();
    const tip = document.createElement('div');
    tip.textContent = message;
    tip.setAttribute('role', 'status');
    tip.style.position = 'fixed';
    tip.style.top = `${Math.max(8, rect.top - 32)}px`;
    tip.style.left = `${rect.left + rect.width / 2}px`;
    tip.style.transform = 'translateX(-50%)';
    tip.style.background = 'var(--brand, #2563eb)';
    tip.style.color = 'var(--brand-contrast, #fff)';
    tip.style.padding = '4px 8px';
    tip.style.borderRadius = '6px';
    tip.style.boxShadow = '0 6px 16px rgba(0,0,0,0.35)';
    tip.style.fontSize = '12px';
    tip.style.zIndex = '1000';
    tip.style.pointerEvents = 'none';
    tip.style.opacity = '0';
    tip.style.transition = 'opacity 120ms ease';

    document.body.appendChild(tip);
    requestAnimationFrame(() => { tip.style.opacity = '1'; });

    setTimeout(() => {
        tip.style.opacity = '0';
        tip.addEventListener('transitionend', () => tip.remove(), { once: true });
    }, 1200);
}
