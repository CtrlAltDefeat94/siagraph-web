document.addEventListener('DOMContentLoaded', async () => {
    const canvas = document.getElementById('renterDistributionChart');
    const status = document.getElementById('renterDistributionStatus');
    const body = document.getElementById('renterDistributionTableBody');
    const empty = text => { canvas.hidden = true; status.textContent = text; body.replaceChildren(); const row = body.insertRow(), cell = row.insertCell(); cell.colSpan = 4; cell.textContent = text; };
    try {
        const response = await fetch('/api/v2/renters/index.php?sort=contracted_filesize&direction=desc&active=1&per_page=10&page=1', { cache: 'no-store' });
        const { data, meta, errors } = await response.json();
        if (!response.ok || errors?.length) throw new Error('Unable to load renter distribution.');
        const items = Array.isArray(data?.items) ? data.items : [];
        const totalFilesizeRaw = meta?.totals?.total_filesize;
        if (!items.length || !/^\d+$/.test(totalFilesizeRaw) || BigInt(totalFilesizeRaw) === 0n) {
            empty('Renter storage distribution is not available yet. Browse individual wallets below.');
            return;
        }
        const totalFilesize = BigInt(totalFilesizeRaw);
        const totalRenters = /^\d+$/.test(meta?.totals?.renter_count) ? meta.totals.renter_count : String(items.length);
        // Round (not truncate) to 6 decimal places, matching the previous published-summary precision.
        const sharePercent = size => (Number((size * 100000000n + totalFilesize / 2n) / totalFilesize) / 1000000).toFixed(6);
        const topSum = items.reduce((sum, item) => sum + (/^\d+$/.test(item.contracted_filesize) ? BigInt(item.contracted_filesize) : 0n), 0n);
        const othersSize = totalFilesize > topSum ? totalFilesize - topSum : 0n;
        const data2 = {
            total_filesize: totalFilesize.toString(),
            total_renters: totalRenters,
            renters: items.map((item, index) => ({
                rank: String(index + 1),
                renter_wallet_address: item.renter_wallet_address,
                contracted_filesize: item.contracted_filesize,
                share_percent: sharePercent(/^\d+$/.test(item.contracted_filesize) ? BigInt(item.contracted_filesize) : 0n),
            })),
            others: othersSize > 0n ? {
                renter_wallet_address: null,
                contracted_filesize: othersSize.toString(),
                share_percent: sharePercent(othersSize),
            } : null,
        };
        // Every share and the Others remainder is derived from the live renters directory above.
        const entries = data2.renters.slice();
        if (data2.others && BigInt(data2.others.contracted_filesize) > 0n) entries.push(data2.others);
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
        status.textContent = `${data2.total_renters} storage participants`;
        if (!window.Chart) { canvas.hidden = true; return; }
        new Chart(canvas, { type: 'doughnut', data: { labels, datasets: [{ data: entries.map(e => Number(e.share_percent)), backgroundColor: ['#e84f4f','#e2d34d','#66db4d','#52d9a9','#4f93e8','#8c49df','#e65eb3','#f38b42','#34d399','#38bdf8','#94a3b8'] }] }, options: { cutout: '58%', onClick: (_event, elements) => { const address = entries[elements[0]?.index]?.renter_wallet_address; if (address) location.assign(`/renter?address=${encodeURIComponent(address)}`); }, plugins: { legend: { display: false }, tooltip: { callbacks: { label: context => { const entry = entries[context.dataIndex]; return `${labels[context.dataIndex]}: ${window.renterFormat.format(entry.contracted_filesize, 'bytes')} (${entry.share_percent}%)`; } } } } } });
    } catch (e) { empty(e.message); }
});
