document.addEventListener('DOMContentLoaded', async () => {
    const canvas = document.getElementById('renterDistributionChart');
    const status = document.getElementById('renterDistributionStatus');
    const body = document.getElementById('renterDistributionTableBody');
    const empty = text => { canvas.hidden = true; status.textContent = text; body.replaceChildren(); const row = body.insertRow(), cell = row.insertCell(); cell.colSpan = 4; cell.textContent = text; };
    try {
        const response = await fetch('/api/v2/network/storage/renter-distribution.php', { cache: 'no-store' });
        const { data, meta, errors } = await response.json();
        if (!response.ok || errors?.length) throw new Error('Unable to load renter distribution.');
        if (!data) { empty('Renter storage distribution is not available yet. Browse individual wallets below.'); return; }
        if (!Array.isArray(data.renters) || !/^\d+$/.test(data.total_filesize)) throw new Error('Invalid published distribution.');
        // Every share and the Others remainder is supplied by the producer.
        const entries = data.renters.slice();
        if (data.others && /^\d+$/.test(data.others.contracted_filesize) && BigInt(data.others.contracted_filesize) > 0n) entries.push(data.others);
        if (!entries.length || BigInt(data.total_filesize) === 0n) { empty('No contracted storage in this snapshot.'); return; }
        body.replaceChildren();
        const labels = entries.map(entry => entry.renter_wallet_address ? `${entry.renter_wallet_address.slice(0, 12)}…${entry.renter_wallet_address.slice(-8)}` : 'Others');
        entries.forEach((entry, index) => {
            const row = body.insertRow(); row.insertCell().textContent = entry.rank ?? '—';
            const cell = row.insertCell();
            if (entry.renter_wallet_address) {
                const link = document.createElement('a'); link.href = `/renter?address=${encodeURIComponent(entry.renter_wallet_address)}`; link.textContent = labels[index]; link.title = entry.renter_wallet_address; cell.append(link);
                const copy = document.createElement('button'); copy.type = 'button'; copy.textContent = 'Copy'; copy.className = 'btn btn-sm'; copy.setAttribute('aria-label', 'Copy renter wallet address'); copy.onclick = async () => { try { await navigator.clipboard.writeText(entry.renter_wallet_address); copy.textContent = 'Copied'; } catch (_) { copy.textContent = 'Unavailable'; } }; cell.append(copy);
            } else cell.textContent = 'Others';
            const sizeCell = row.insertCell(); sizeCell.textContent = window.renterFormat.format(entry.contracted_filesize, 'bytes'); sizeCell.title = `${entry.contracted_filesize} bytes`; row.insertCell().textContent = `${entry.share_percent}%`;
        });
        status.textContent = `Snapshot ${meta.snapshot_date} · Block ${meta.snapshot_height} · ${data.total_renters} storage participants`;
        if (!window.Chart) { canvas.hidden = true; return; }
        new Chart(canvas, { type: 'doughnut', data: { labels, datasets: [{ data: entries.map(e => Number(e.share_percent)), backgroundColor: ['#e84f4f','#e2d34d','#66db4d','#52d9a9','#4f93e8','#8c49df','#e65eb3','#f38b42','#34d399','#38bdf8','#94a3b8'] }] }, options: { cutout: '58%', onClick: (_event, elements) => { const address = entries[elements[0]?.index]?.renter_wallet_address; if (address) location.assign(`/renter?address=${encodeURIComponent(address)}`); }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => { const entry = entries[context.dataIndex]; return `${labels[context.dataIndex]}: ${window.renterFormat.format(entry.contracted_filesize, 'bytes')} (${entry.share_percent}%)`; } } } } } });
    } catch (e) { empty(e.message); }
});
