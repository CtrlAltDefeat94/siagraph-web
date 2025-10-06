document.addEventListener('DOMContentLoaded', () => {
    const dataEl = document.getElementById('host-overview-data');
    const hostsData = JSON.parse(dataEl?.dataset.hosts || '[]');
    const hostLocations = JSON.parse(dataEl?.dataset.locations || '[]');
    const map = L.map('map').setView([20, 0], 2);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
    }).addTo(map);
    hostLocations.forEach(h => {
        const m = L.marker([h.lat, h.lng]).addTo(map);
        m.on('click', () => {
            document.getElementById('countryFilter').value = h.country;
            applyFilters();
        });
    });
    document.getElementById('versionFilter').addEventListener('change', applyFilters);
    document.getElementById('countryFilter').addEventListener('change', applyFilters);
    function applyFilters() {
        const version = document.getElementById('versionFilter').value;
        const country = document.getElementById('countryFilter').value;
        const filtered = hostsData.filter(h =>
            (version === '' || h.software_version === version) &&
            (country === '' || h.country_name === country)
        );
        updateStats(filtered);
        updateCountryTable(filtered);
    }
    function avg(arr) { return arr.length ? arr.reduce((a, b) => a + b, 0) / arr.length : 0; }
    function updateStats(hosts) {
        const storage = [], upload = [], download = [];
        let full = 0;
        hosts.forEach(h => {
            if (h.total_storage - h.used_storage === 0) full++;
            storage.push(h.storage_price / 1e12 * 4320);
            upload.push(h.upload_price / 1e12);
            download.push(h.download_price / 1e12);
        });
        document.getElementById('stats1a').textContent = formatSC(avg(storage));
        document.getElementById('stats2a').textContent = formatSC(avg(upload));
        document.getElementById('stats3a').textContent = formatSC(avg(download));
        document.getElementById('stats4a').textContent = hosts.length;
        document.getElementById('stats5a').textContent = full;
    }
    function formatBytes(bytes) { if (bytes === 0) return '0 B'; const k = 1024; const sizes = ['B','KB','MB','GB','TB','PB']; const i = Math.floor(Math.log(bytes) / Math.log(k)); return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i]; }
    function updateCountryTable(hosts) {
        const data = {};
        hosts.forEach(h => {
            if (!data[h.country_name]) data[h.country_name] = { host_count: 0, used: 0, total: 0 };
            data[h.country_name].host_count++;
            data[h.country_name].used += parseInt(h.used_storage);
            data[h.country_name].total += parseInt(h.total_storage);
        });
        const tbody = document.querySelector('#country-table tbody');
        tbody.innerHTML = '';
        Object.entries(data).sort((a, b) => b[1].used - a[1].used).forEach(([country, d], i) => {
            const row = document.createElement('tr');
            row.className = i % 2 === 0 ? 'bg-gray-900' : 'bg-gray-800';
            row.innerHTML = `<td class="px-4 py-2 border border-gray-300 text-center">${country}</td>` +
                `<td class="px-4 py-2 border border-gray-300 text-right">${d.host_count}</td>` +
                `<td class="px-4 py-2 border border-gray-300 text-right">${formatBytes(d.used)}</td>` +
                `<td class="px-4 py-2 border border-gray-300 text-right">${formatBytes(d.total)}</td>`;
            tbody.appendChild(row);
        });
    }
    applyFilters();
});
