document.addEventListener('DOMContentLoaded', () => {
    const chartCanvas = document.getElementById('renterDistributionChart');
    if (!chartCanvas) return;

    const statusEl = document.getElementById('renterDistributionStatus');
    const tableBody = document.getElementById('renterDistributionTableBody');
    const apiUrl = '/api/v1/storage/renter_distribution.php';
    let pieChart = null;

    const setStatus = (message, isError = false) => {
        if (!statusEl) return;
        statusEl.textContent = message;
        statusEl.classList.toggle('text-danger', !!isError);
    };

    const formatBytes = (bytes) => {
        const isNegative = bytes < 0;
        const absolute = Math.abs(bytes);
        if (!Number.isFinite(absolute) || absolute === 0) return '0 B';
        const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
        const exponent = Math.min(Math.floor(Math.log(absolute) / Math.log(1000)), units.length - 1);
        const scaled = absolute / Math.pow(1000, exponent);
        const formatted = `${scaled.toFixed(2)} ${units[exponent]}`;
        return isNegative ? `-${formatted}` : formatted;
    };

    const buildChart = (labels, data, totalBytes) => {
        const colors = data.map((_, idx) => `hsl(${(idx * 53) % 360}, 70%, 58%)`);
        if (pieChart) {
            pieChart.destroy();
        }
        pieChart = new Chart(chartCanvas.getContext('2d'), {
            type: 'pie',
            data: {
                labels,
                datasets: [{
                    data,
                    backgroundColor: colors,
                    borderColor: 'rgba(15,23,42,0.5)',
                    borderWidth: 1,
                    label: 'Utilized storage by renter'
                }]
            },
            options: {
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: (context) => {
                                const value = Number(context.raw) || 0;
                                const pct = totalBytes > 0 ? (value / totalBytes) * 100 : 0;
                                return `${context.label}: ${formatBytes(value)} (${pct.toFixed(2)}%)`;
                            }
                        }
                    }
                }
            }
        });
    };

    const buildTable = (labels, data, totalBytes) => {
        if (!tableBody) return;
        if (!data.length) {
            tableBody.innerHTML = '<tr><td colspan="3" class="text-center text-muted">No data available.</td></tr>';
            return;
        }

        const rows = labels.map((label, idx) => {
            const val = data[idx] || 0;
            const pct = totalBytes > 0 ? (val / totalBytes) * 100 : 0;
            const rank = idx + 1;
            const isOthers = label === 'Others';
            return `
                <tr>
                    <td>${isOthers ? '&mdash;' : `#${rank}`}</td>
                    <td class="text-end">${formatBytes(val)}</td>
                    <td class="text-end">${pct.toFixed(2)}%</td>
                </tr>
            `;
        });

        tableBody.innerHTML = rows.join('');
    };

    const hydrateChart = async () => {
        try {
            const data = await fetchWithCache(apiUrl, {}, 30 * 60 * 1000);
            if (!data || !Array.isArray(data.largest_sizes)) {
                throw new Error('Unexpected renter distribution payload');
            }

            const sizes = data.largest_sizes.map(v => Number(v) || 0);
            const totalFilesize = Number(data.total_filesize || 0);
            const renterCount = Number(data.total_renters || 0);
            const sizeSum = sizes.reduce((acc, val) => acc + val, 0);
            const leftoverRaw = totalFilesize - sizeSum;
            const leftover = Number.isFinite(leftoverRaw) ? leftoverRaw : 0;

            // Always include an Others slice to satisfy the requirement
            const chartData = sizes.concat([leftover]);
            const labels = sizes.map((_, idx) => `Renter ${idx + 1}`).concat(['Others']);
            const totalForChart = sizeSum + leftover;

            buildChart(labels, chartData, totalForChart);
            buildTable(labels, chartData, totalForChart);

            const othersCount = renterCount && renterCount > sizes.length ? renterCount - sizes.length : 0;
            const totalText = totalFilesize > 0 ? formatBytes(totalFilesize) : 'N/A';
            setStatus("");
            /*setStatus(
                `Top ${sizes.length} renters shown out of ${renterCount || 'unknown'}. ` +
                `Others combines ${othersCount} renter${othersCount === 1 ? '' : 's'} ` +
                `(${formatBytes(Math.max(0, leftover))} of ${totalText}).`
            );*/
        } catch (err) {
            console.error(err);
            setStatus('Failed to load renter distribution data.', true);
        }
    };

    hydrateChart();
});
