document.addEventListener('DOMContentLoaded', async () => {
    try {
        const data = await fetchWithCache('/api/v1/peers', {}, 300000);
        const mainnetPeers = data.mainnet.slice(0, 5);
        const zenPeers = data.zen.slice(0, 5);

        const mainTbody = document.querySelector('#mainnetTable tbody');
        const zenTbody = document.querySelector('#zenTable tbody');

        const createRow = (peer) => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td class="px-4 py-2">
                    <button class="copy-btn text-left hover:underline" data-text="${peer.address}">${peer.address}</button>
                </td>
                <td class="px-4 py-2 text-right">${peer.version}</td>
                <td class="px-4 py-2 text-right">${peer.last_scanned}</td>`;
            return tr;
        };

        mainnetPeers.forEach(p => mainTbody.appendChild(createRow(p)));
        zenPeers.forEach(p => zenTbody.appendChild(createRow(p)));

        document.querySelectorAll('.copy-btn').forEach(btn => {
            btn.addEventListener('click', async (e) => {
                e.preventDefault();
                const ok = await copyToClipboard(btn.dataset.text);
                showToastNear(btn, ok ? 'Copied to clipboard!' : 'Copy failed');
            });
        });
    } catch (err) {
        console.error('Error fetching data', err);
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
