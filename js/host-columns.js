const hostColumnDefinitions = [
    ['rank', 'Rank'], ['host', 'Host'], ['country', 'Country'],
    ['used_storage', 'Used Storage & Growth'], ['total_storage', 'Total Storage'],
    ['estimated_egress', 'Estimated Egress'], ['price', 'Storage Price'],
    ['upload_price', 'Ingress Price'], ['download_price', 'Egress Price'], ['score', 'Score'],
    ['available_storage', 'Available Storage'], ['software_version', 'Software Version'],
    ['revenue_30d', '30 Day Revenue']
];
const fixedHostColumns = new Set(['rank', 'host']);
const hostColumnStorageKey = 'siagraph.hostExplorer.hiddenColumns.v3';
const newHostColumns = ['available_storage', 'software_version', 'revenue_30d'];
let hiddenHostColumns = new Set(['upload_price', 'download_price', ...newHostColumns]);
try {
    const current = localStorage.getItem(hostColumnStorageKey);
    const previous = localStorage.getItem('siagraph.hostExplorer.hiddenColumns.v2');
    const saved = JSON.parse(current ?? previous ?? localStorage.getItem('siagraph.hostExplorer.hiddenColumns.v1'));
    // Preserve existing selections while keeping newly added columns optional.
    const additionalHidden = current !== null ? [] : previous !== null
        ? newHostColumns : ['upload_price', 'download_price', ...newHostColumns];
    if (Array.isArray(saved)) {
        hiddenHostColumns = new Set([...additionalHidden, ...saved.filter(key => !fixedHostColumns.has(key) && hostColumnDefinitions.some(([id]) => id === key))]);
    }
} catch (error) { /* Column controls also work when storage is unavailable. */ }

function applyHostColumnVisibility() {
    fixedHostColumns.forEach(id => hiddenHostColumns.delete(id));
    const table = document.getElementById('hostTable');
    if (!table) return;
    const compact = table.classList.contains('compact-table');
    const columns = compact ? ['rank', 'host'] : hostColumnDefinitions.map(([id]) => id);
    const visibleCount = columns.filter(id => !hiddenHostColumns.has(id)).length;
    table.querySelectorAll('tr').forEach(row => {
        Array.from(row.children).forEach((cell, index) => {
            if (row.children.length === 1) {
                cell.colSpan = visibleCount;
            } else {
                cell.hidden = hiddenHostColumns.has(columns[index]);
            }
        });
    });
    table.querySelectorAll('.host-link').forEach(link => {
        // Keep the full address available when its visual label is ellipsized.
        link.title = link.textContent;
    });
    const mobileColumns = ['country', 'used_storage', 'total_storage', 'estimated_egress', 'used_storage', 'price', 'upload_price', 'download_price', 'score', 'available_storage', 'software_version', 'revenue_30d'];
    table.querySelectorAll('.mobile-metrics').forEach(metrics => {
        Array.from(metrics.children).forEach((metric, index) => {
            metric.hidden = hiddenHostColumns.has(mobileColumns[index]);
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    const options = document.getElementById('hostColumnOptions');
    if (!options) return;
    function save() {
        try { localStorage.setItem(hostColumnStorageKey, JSON.stringify([...hiddenHostColumns])); } catch (error) { /* Keep changes for this page. */ }
        applyHostColumnVisibility();
    }
    hostColumnDefinitions.forEach(([id, name]) => {
        if (fixedHostColumns.has(id)) return;
        const label = document.createElement('label');
        const checkbox = document.createElement('input');
        checkbox.type = 'checkbox';
        checkbox.value = id;
        checkbox.checked = !hiddenHostColumns.has(id);
        checkbox.addEventListener('change', () => {
            if (checkbox.checked) hiddenHostColumns.delete(id);
            else hiddenHostColumns.add(id);
            save();
        });
        label.append(checkbox, document.createTextNode(name));
        options.appendChild(label);
    });
    document.getElementById('hostColumnsReset').addEventListener('click', () => {
        hiddenHostColumns.clear();
        options.querySelectorAll('input').forEach(input => { input.checked = true; });
        save();
    });
    const menu = document.getElementById('hostColumnMenu');
    document.addEventListener('click', event => { if (!menu.contains(event.target)) menu.open = false; });
    menu.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
    applyHostColumnVisibility();
});
