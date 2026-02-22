<?php

#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);


require_once 'bootstrap.php';
include_once 'include/database.php';
include_once 'include/graph.php';
include_once 'include/config.php';
require_once 'include/layout.php';

use Siagraph\Utils\Cache;
use Siagraph\Utils\Formatter;
use Siagraph\Utils\ApiClient;
// Fetch host and public key data
$host_id = isset($_GET['id']) ? $_GET['id'] : null;
$publickeyquery = "SELECT public_key, net_address from Hosts where host_id= '$host_id'";
$publickeyresult = mysqli_query($mysqli, $publickeyquery);
$result = mysqli_fetch_assoc($publickeyresult);
$net_address = $result['net_address'];

// Fetch and process data
$data = fetchData($host_id, 1, 'desc', false, result: $result);
$jsonData = json_encode($data);

// Initialize the result array with a global group
$groupedBenchmarks = ["global" => []];


// Group benchmarks by node
foreach ($data['benchmarks'] as $benchmark) {
    $groupedBenchmarks['global'][] = $benchmark;
    $node = $benchmark['node'];
    if (!isset($groupedBenchmarks[$node])) {
        $groupedBenchmarks[$node] = [];
    }
    $groupedBenchmarks[$node][] = $benchmark;
}
function calculateDailyStats($timestamps, $speeds)
{
    $dailySpeeds = [];
    $dailyAverages = [];
    $dailyStdDevs = [];

    // Group speeds by date
    foreach ($timestamps as $index => $timestamp) {
        $date = date('Y-m-d', strtotime($timestamp)); // Extract date (YYYY-MM-DD)
        $dailySpeeds[$date][] = $speeds[$index];
    }

    // Calculate stats per day
    foreach ($dailySpeeds as $date => $speeds) {
        $average = array_sum($speeds) / count($speeds);
        $dailyAverages[$date] = $average;

        if (count($speeds) > 1) {
            $squaredDiffs = array_map(fn($speed) => pow($speed - $average, 2), $speeds);
            $stdDev = sqrt(array_sum($squaredDiffs) / count($speeds));
        } else {
            $stdDev = 0;
        }
        $dailyStdDevs[$date] = $stdDev;
    }

    // Format results with time set to 00:00:00
    $formattedResults = [];
    foreach ($dailyAverages as $date => $average) {
        $formattedResults[] = [
            'timestamp' => $date . ' 00:00:00',
            'average_speed' => $average,
            'std_dev' => $dailyStdDevs[$date]
        ];
    }
    return $formattedResults;
}

$timestamps = [];
$downloadSpeeds = [];
$uploadSpeeds = [];

// Convert timestamps, download speeds, and upload speeds to arrays
foreach ($data['benchmarks'] as $benchmark) {
    $timestamps[] = date("Y-m-d H:i:s", strtotime($benchmark['timestamp']));
    $downloadSpeeds[] = $benchmark['downloadSpeed'] / 1000000; // Convert to Mbps
    $uploadSpeeds[] = $benchmark['uploadSpeed'] / 1000000; // Convert to Mbps
}

// `calculateDailyStats` returns an array of associative arrays, so extract the
// averages and standard deviations separately.
$downloadStats = calculateDailyStats($timestamps, $downloadSpeeds);
$avgDownloadSpeeds = array_column($downloadStats, 'average_speed');
$downloadStdDevs = array_column($downloadStats, 'std_dev');

$uploadStats = calculateDailyStats($timestamps, $uploadSpeeds);
$avgUploadSpeeds = array_column($uploadStats, 'average_speed');
$uploadStdDevs = array_column($uploadStats, 'std_dev');

// Function to calculate upper and lower bounds for std deviation
function calculateBounds($averages, $stdDevs)
{
    $upperBound = [];
    $lowerBound = [];

    for ($i = 0; $i < count($averages); $i++) {
        $upperBound[] = $averages[$i] + $stdDevs[$i];
        $lowerBound[] = max(0, $averages[$i] - $stdDevs[$i]);
    }

    return [$upperBound, $lowerBound];
}

list($downloadUpperBound, $downloadLowerBound) = calculateBounds($avgDownloadSpeeds, $downloadStdDevs);
list($uploadUpperBound, $uploadLowerBound) = calculateBounds($avgUploadSpeeds, $uploadStdDevs);

// PHP function to fetch and display data
function fetchData($host_id, $page, $sortCriteria, $showInactive, $result, $sortOrder = 'desc')
{
    global $SETTINGS;
    $data = null; // Initialize data variable

    if (isset($_GET['public_key'])) {
        $public_key = $_GET['public_key'];
    } else {
        $public_key = $result['public_key'];
    }

    try {
        // Get the current date and format it
        $formattedDate = (new DateTime('now', new DateTimeZone('UTC')))
            ->modify('-7 days')->setTime(0, 0, 0)->format('Y-m-d\TH:i:s\Z');

        // Construct the API URL        // Construct the API URL
        $url = "https://api.hostscore.info/v1/hosts/benchmarks?network=" . $SETTINGS['network'] . "&host=" . $public_key . "&all=true&from=" . $formattedDate;
        // Fetch data from the API
        $data = ApiClient::fetchJson($url);

        if ($data === null) {
            echo 'Error fetching data from API.';
            return null; // Handle the error appropriately
        }

        // Now you have the data array, let's work with it
        if (isset($data['benchmarks']) && is_array($data['benchmarks'])) {
            // Sort the benchmarks based on timestamp (latest first)
            usort($data['benchmarks'], function ($a, $b) {
                return strtotime($b['timestamp']) - strtotime($a['timestamp']);
            });
        } else {
            echo 'No benchmarks found in the response.';
            return null; // Handle the absence of benchmarks
        }

        return $data;
    } catch (Exception $e) {
        echo 'Error fetching data: ', $e->getMessage();
        return null; // Handle the exception appropriately
    }
}

?>
<?php render_header("SiaGraph - Host Benchmarks"); ?>

    <!-- Main Content Section -->
    <section id="main-content" class="sg-container masonry-container">
        <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-speedometer2 me-2"></i>Host Benchmarks</h1>
        <div class="flex flex-wrap justify-start mt-4 mb-2 gap-2">
            <a class="cursor-pointer hover:underline flex items-center font-bold text-xl" href='/host_explorer'>Top
                Hosts</a>
            <span class="flex items-center font-bold text-xl">/</span>
            <a class="cursor-pointer hover:underline flex items-center font-bold text-xl"
                href='/host?id=<?php echo htmlspecialchars($host_id, ENT_QUOTES, 'UTF-8'); ?>'>
                <?php echo htmlspecialchars($net_address, ENT_QUOTES, 'UTF-8'); ?>
            </a>
            <span class="flex items-center font-bold text-xl">/</span>
            <span class="flex items-center font-bold text-xl">Recent benchmarks</span>
        </div>

        <div class="d-flex flex-wrap mb-3 align-items-center">
            <label for="nodeSelect" class="form-label me-2 mb-0">Select Node:</label>
            <select id="nodeSelect" class="form-select" style="width: auto;">
                <?php
                // Populate dropdown options for each node group
                foreach ($groupedBenchmarks as $node => $benchmarks) {
                    echo '<option value="' . htmlspecialchars($node) . '">' . htmlspecialchars($node) . '</option>';
                }
                ?>
            </select>
        </div>


        <!-- Graphs Section -->
        <div class="row">
            <div class="col-md-6">
                <section id="graph2-section" class="bg-dark p-3 rounded-3 bg-gradient shadow-lg">
                    <section class="graph-container">
                        <h2 class="card__heading">Upload speed for
                            renters</h2>
                        <div class="benchmark-chart-wrap">
                            <canvas id="uploadChart" class="benchmark-chart-canvas"></canvas>
                        </div>
                    </section>
                </section>
            </div>
            <div class="col-md-6">
                <section id="graph2-section" class="bg-dark p-3 rounded-3 bg-gradient shadow-lg">
                    <section class="graph-container">
                        <h2 class="card__heading">Download speed for
                            renters</h2>
                        <div class="benchmark-chart-wrap">
                            <canvas id="downloadChart" class="benchmark-chart-canvas"></canvas>
                        </div>
                    </section>
                </section>
            </div>
        </div>

        <!-- Benchmarks Table -->
        <section class="table-container">
            <h2 class="card__heading">Benchmarks of the past 7 days
            </h2>
            <div class="overflow-x-auto">
                <table id="hostTable" class="table table-dark table-clean text-white min-w-full border-collapse" style="visibility: hidden;">
                    <thead></thead>
                    <tbody id="hostTableBody"></tbody>
                </table>
            </div>
        </section>
    </section>


    <script>
        const groupedBenchmarks = <?php echo json_encode($groupedBenchmarks); ?>;
        let selectedNode = 'global';
        let downloadChart;
        let uploadChart;
        let sortState = { key: 'timestamp', dir: 'desc' };

        // Responsive helpers
        function isMobileViewport() {
            return window.innerWidth < 768; // Tailwind md breakpoint
        }

        function isCompactBenchmarksView() {
            const container = document.querySelector('.table-container');
            const availableWidth = container ? container.clientWidth : window.innerWidth;
            return availableWidth < 980;
        }

        function showTable() {
            const tbl = document.getElementById('hostTable');
            if (tbl) tbl.style.visibility = 'visible';
        }

        function hideTable() {
            const tbl = document.getElementById('hostTable');
            if (tbl) tbl.style.visibility = 'hidden';
        }

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function truncateText(value, maxLength) {
            if (!value || value.length <= maxLength) return value;
            return `${value.slice(0, maxLength - 1)}...`;
        }

        function getNumericSafe(value) {
            const n = Number(value);
            return Number.isFinite(n) ? n : 0;
        }

        function getSortValue(benchmark, key) {
            switch (key) {
                case 'timestamp':
                    return new Date(benchmark.timestamp).getTime();
                case 'upload':
                    return getNumericSafe(benchmark.uploadSpeed);
                case 'download':
                    return getNumericSafe(benchmark.downloadSpeed);
                case 'ttfb':
                    return getNumericSafe(benchmark.ttfb);
                case 'success':
                    return benchmark.success ? 1 : 0;
                case 'node':
                    return String(benchmark.node || '').toLowerCase();
                default:
                    return 0;
            }
        }

        function getSortedBenchmarks(node) {
            const source = groupedBenchmarks && groupedBenchmarks[node] ? groupedBenchmarks[node] : [];
            const sorted = [...source];
            sorted.sort((a, b) => {
                const aValue = getSortValue(a, sortState.key);
                const bValue = getSortValue(b, sortState.key);
                if (aValue === bValue) return 0;
                if (sortState.dir === 'asc') {
                    return aValue > bValue ? 1 : -1;
                }
                return aValue < bValue ? 1 : -1;
            });
            return sorted;
        }

        function getSortArrow(key) {
            if (sortState.key !== key) return '';
            return sortState.dir === 'asc' ? ' ▲' : ' ▼';
        }

        function renderSortButton(label, key, extraClasses = '') {
            return `<button type="button" class="table-sort-btn ${extraClasses}" data-sort-key="${key}">${label}${getSortArrow(key)}</button>`;
        }

        function bindSortHandlers() {
            const buttons = document.querySelectorAll('#hostTable thead .table-sort-btn[data-sort-key]');
            buttons.forEach(button => {
                button.addEventListener('click', function () {
                    const nextKey = this.getAttribute('data-sort-key');
                    if (!nextKey) return;
                    if (sortState.key === nextKey) {
                        sortState.dir = sortState.dir === 'asc' ? 'desc' : 'asc';
                    } else {
                        sortState.key = nextKey;
                        sortState.dir = nextKey === 'timestamp' ? 'desc' : 'asc';
                    }
                    populateTable(selectedNode);
                });
            });
        }

        function getShortLocalizedTime(timestamp) {
            const d = new Date(timestamp);
            if (Number.isNaN(d.getTime())) return escapeHtml(timestamp);
            const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
            return new Intl.DateTimeFormat(loc, {
                month: 'short',
                day: '2-digit',
                hour: '2-digit',
                minute: '2-digit'
            }).format(d);
        }

        function renderStatusPill(benchmark, truncateAt = 48) {
            if (benchmark.success) {
                return '<span class="status-pill status-pill--ok">OK</span>';
            }
            const rawError = String(benchmark.error || 'Error');
            const displayText = escapeHtml(truncateText(rawError, truncateAt));
            const titleText = escapeHtml(rawError);
            return `<span class="status-pill status-pill--error" title="${titleText}">${displayText}</span>`;
        }

        function formatChartNumber(value, digits = 2) {
            const n = Number(value);
            if (!Number.isFinite(n)) return '0';
            const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
            return n.toLocaleString(loc, {
                minimumFractionDigits: digits,
                maximumFractionDigits: digits
            });
        }

        function formatTooltipDate(value) {
            const dt = moment(value);
            if (!dt.isValid()) return '';
            const hasTime = !(dt.hour() === 0 && dt.minute() === 0 && dt.second() === 0);
            return hasTime ? dt.format('DD MMM YYYY HH:mm') : dt.format('DD MMM YYYY');
        }

        function createSpeedChartOptions() {
            return {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'MB/s'
                        },
                        startAtZero: true,
                        ticks: {
                            callback: value => formatChartNumber(value, 2)
                        },
                        grid: {
                            color: 'rgba(255,255,255,0.08)'
                        }
                    },
                    x: {
                        type: 'time',
                        title: {
                            display: false,
                            text: 'Date'
                        },
                        time: {
                            unit: 'day',
                            tooltipFormat: 'MMM DD',
                            displayFormats: {
                                day: 'MMM DD'
                            }
                        },
                        ticks: {
                            autoSkip: true,
                            maxTicksLimit: isMobileViewport() ? 6 : 10
                        },
                        grid: {
                            color: 'rgba(255,255,255,0.05)'
                        }
                    }
                },
                interaction: { mode: 'nearest', intersect: false },
                plugins: {
                    title: { display: false },
                    legend: {
                        position: 'bottom',
                        onClick: () => {},
                        labels: {
                            usePointStyle: true,
                            boxWidth: 10,
                            filter: (legendItem, chartData) => {
                                const ds = chartData?.datasets?.[legendItem.datasetIndex];
                                return !(ds && ds.isAuxiliary === true);
                            }
                        }
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: context => {
                                let label = '';
                                if (context.dataset.label) {
                                    label = context.dataset.label + ': ';
                                }
                                const value = context.parsed.y;
                                const decimals = context.dataset.decimalPlaces ?? 0;
                                label += `${formatChartNumber(value, decimals)} MB/s`;
                                return label;
                            },
                            title: items => formatTooltipDate(items[0].parsed.x)
                        }
                    }
                }
            };
        }

        function renderTableHeader() {
            const thead = document.querySelector('#hostTable thead');
            if (!thead) return;
            const compact = isCompactBenchmarksView();
            const table = document.getElementById('hostTable');
            if (table) table.classList.toggle('compact-table', compact);
            if (compact) {
                thead.innerHTML = `
                    <tr>
                        <th class="px-3 py-2 timestamp-col">${renderSortButton('Timestamp', 'timestamp')}</th>
                        <th class="px-3 py-2 node-col">${renderSortButton('Node', 'node')}</th>
                    </tr>
                `;
            } else {
                thead.innerHTML = `
                    <tr>
                        <th class="px-3 py-2 timestamp-col">${renderSortButton('Timestamp', 'timestamp')}</th>
                        <th class="px-3 py-2 node-col">${renderSortButton('Node', 'node')}</th>
                        <th class="px-3 py-2 upload-col num">${renderSortButton('Upload Speed', 'upload', 'num')}</th>
                        <th class="px-3 py-2 download-col num">${renderSortButton('Download Speed', 'download', 'num')}</th>
                        <th class="px-3 py-2 ttfb-col num">${renderSortButton('Time to First Byte', 'ttfb', 'num')}</th>
                        <th class="px-3 py-2 success-col">${renderSortButton('Success', 'success')}</th>
                    </tr>
                `;
            }
            bindSortHandlers();
        }

        function generateColor(index, total) {
            const hue = Math.round((index * 360) / total) % 360;
            return `hsl(${hue}, 70%, 50%)`;
        }

        function buildChartData(node) {
            const nodes = node === 'global'
                ? Object.keys(groupedBenchmarks).filter(n => n !== 'global')
                : [node];

            const datasetsDownload = [];
            const datasetsUpload = [];
            const total = nodes.length;

            nodes.forEach((n, idx) => {
                const color = generateColor(idx, total);
                const bms = groupedBenchmarks[n];
                datasetsDownload.push({
                    label: n,
                    data: bms.map(b => ({ x: new Date(b.timestamp), y: b.downloadSpeed / 1000000 })),
                    borderColor: 'transparent',
                    backgroundColor: color,
                    pointBackgroundColor: color,
                    fill: false,
                    pointRadius: isMobileViewport() ? 3 : 4,
                    pointHitRadius: 10,
                    pointHoverRadius: 6,
                    showLine: false,
                    decimalPlaces: 2
                });
                datasetsUpload.push({
                    label: n,
                    data: bms.map(b => ({ x: new Date(b.timestamp), y: b.uploadSpeed / 1000000 })),
                    borderColor: 'transparent',
                    backgroundColor: color,
                    pointBackgroundColor: color,
                    fill: false,
                    pointRadius: isMobileViewport() ? 3 : 4,
                    pointHitRadius: 10,
                    pointHoverRadius: 6,
                    showLine: false,
                    decimalPlaces: 2
                });
            });

            const allBenchmarks = node === 'global' ? groupedBenchmarks['global'] : groupedBenchmarks[node];
            const timestamps = allBenchmarks.map(b => new Date(b.timestamp));
            const downloadSpeeds = allBenchmarks.map(b => b.downloadSpeed / 1000000);
            const uploadSpeeds = allBenchmarks.map(b => b.uploadSpeed / 1000000);

            const [dailyDates, avgDownloadSpeeds, downloadStdDevs] = calculateDailyStats(timestamps, downloadSpeeds);
            const [, avgUploadSpeeds, uploadStdDevs] = calculateDailyStats(timestamps, uploadSpeeds);

            const downloadBounds = calculateBounds(avgDownloadSpeeds, downloadStdDevs);
            const uploadBounds = calculateBounds(avgUploadSpeeds, uploadStdDevs);

                datasetsDownload.push({
                    label: 'Daily Avg.',
                    data: dailyDates.map((t, i) => ({ x: t, y: avgDownloadSpeeds[i] })),
                    borderColor: 'green',
                backgroundColor: 'rgba(0,255,0,0.2)',
                fill: false,
                    pointRadius: 0,
                    borderWidth: 2,
                    tension: 0.25,
                    decimalPlaces: 2,
                    isAuxiliary: true
                });
                datasetsDownload.push({
                    label: '+1σ',
                    data: dailyDates.map((t, i) => ({ x: t, y: downloadBounds[0][i] })),
                borderColor: 'rgba(50,205,50,0.5)',
                pointRadius: 0,
                    fill: '+1',
                    backgroundColor: 'rgba(50,205,50,0.1)',
                    borderWidth: 0,
                    decimalPlaces: 2,
                    isAuxiliary: true
                });
                datasetsDownload.push({
                    label: '-1σ',
                    data: dailyDates.map((t, i) => ({ x: t, y: downloadBounds[1][i] })),
                borderColor: 'rgba(50,205,50,0.5)',
                    pointRadius: 0,
                    fill: false,
                    borderWidth: 0,
                    decimalPlaces: 2,
                    isAuxiliary: true
                });

                datasetsUpload.push({
                    label: 'Daily Avg.',
                data: dailyDates.map((t, i) => ({ x: t, y: avgUploadSpeeds[i] })),
                borderColor: 'green',
                backgroundColor: 'rgba(0,255,0,0.2)',
                fill: false,
                    pointRadius: 0,
                    borderWidth: 2,
                    tension: 0.25,
                    decimalPlaces: 2,
                    isAuxiliary: true
                });
                datasetsUpload.push({
                    label: '+1σ',
                    data: dailyDates.map((t, i) => ({ x: t, y: uploadBounds[0][i] })),
                borderColor: 'rgba(50,205,50,0.5)',
                pointRadius: 0,
                    fill: '+1',
                    backgroundColor: 'rgba(50,205,50,0.1)',
                    borderWidth: 0,
                    decimalPlaces: 2,
                    isAuxiliary: true
                });
                datasetsUpload.push({
                    label: '-1σ',
                    data: dailyDates.map((t, i) => ({ x: t, y: uploadBounds[1][i] })),
                borderColor: 'rgba(50,205,50,0.5)',
                    pointRadius: 0,
                    fill: false,
                    borderWidth: 0,
                    decimalPlaces: 2,
                    isAuxiliary: true
                });

            return { download: datasetsDownload, upload: datasetsUpload };
        }

        function updateChartsForNode(node) {
            const data = buildChartData(node);
            downloadChart.data.datasets = data.download;
            uploadChart.data.datasets = data.upload;
            downloadChart.update();
            uploadChart.update();
        }

        function calculateDailyStats(timestamps, speeds) {
            const dailySpeeds = {};
            timestamps.forEach((ts, idx) => {
                const dateKey = ts.toISOString().split('T')[0];
                if (!dailySpeeds[dateKey]) {
                    dailySpeeds[dateKey] = [];
                }
                dailySpeeds[dateKey].push(speeds[idx]);
            });

            const dates = Object.keys(dailySpeeds).sort();
            const averageSpeeds = [];
            const stdDevs = [];

            dates.forEach(dateKey => {
                const values = dailySpeeds[dateKey];
                const avg = values.reduce((a, b) => a + b, 0) / values.length;
                averageSpeeds.push(avg);

                if (values.length > 1) {
                    const variance = values.reduce((acc, v) => acc + Math.pow(v - avg, 2), 0) / values.length;
                    stdDevs.push(Math.sqrt(variance));
                } else {
                    stdDevs.push(0);
                }
            });

            const dateObjects = dates.map(d => new Date(d + 'T00:00:00'));
            return [dateObjects, averageSpeeds, stdDevs];
        }

        function calculateBounds(averages, stdDevs) {
            const upperBound = averages.map((avg, index) => avg + stdDevs[index]);
            const lowerBound = averages.map((avg, index) => Math.max(0, avg - stdDevs[index]));
            return [upperBound, lowerBound];
        }

        document.getElementById('nodeSelect').addEventListener('change', function () {
            selectedNode = this.value;
            updateChartsForNode(selectedNode);
            populateTable(selectedNode);
        });

        // Chart instances will be initialized once the page has fully loaded
        function populateTable(node) {
            renderTableHeader();
            if (groupedBenchmarks && groupedBenchmarks[node]) {
                const benchmarks = getSortedBenchmarks(node);
                const tableBody = document.querySelector('#hostTableBody'); // Ensure this matches the correct selector
                let tableRows = ''; // Accumulate rows as a string

                benchmarks.forEach((benchmark, index) => {
                    const isEvenRow = (parseInt(index, 10) + 1) % 2 === 0;
                    const rowClass = isEvenRow ? 'bg-gray-800' : 'bg-gray-900';
                    const uploadValue = (getNumericSafe(benchmark.uploadSpeed) / 1000000).toFixed(2);
                    const downloadValue = (getNumericSafe(benchmark.downloadSpeed) / 1000000).toFixed(2);
                    const ttfbValue = getNumericSafe(benchmark.ttfb) / 1000000;
                    const safeNode = escapeHtml(benchmark.node);
                    const statusPill = renderStatusPill(benchmark, isCompactBenchmarksView() ? 28 : 56);
                    if (isCompactBenchmarksView()) {
                        // Mobile: Keep 2-column layout, but stack details for readability.
                        tableRows += `
                        <tr class="${rowClass}">
                            <td class="border px-3 py-2 timestamp-col" data-timestamp="${escapeHtml(benchmark.timestamp)}">
                                <span class="timestamp-short" title="${escapeHtml(getLocalizedTime(benchmark.timestamp))}">${getShortLocalizedTime(benchmark.timestamp)}</span>
                            </td>
                            <td class="border px-3 py-2 node-col">
                                <div class="font-semibold">${safeNode}</div>
                                <div class="bench-detail-list text-xs mt-1">
                                    <div class="bench-detail-row"><span class="bench-detail-label">Up</span><span class="num">${uploadValue} MB/s</span></div>
                                    <div class="bench-detail-row"><span class="bench-detail-label">Down</span><span class="num">${downloadValue} MB/s</span></div>
                                    <div class="bench-detail-row"><span class="bench-detail-label">TTFB</span><span class="num">${ttfbValue} ms</span></div>
                                    <div class="bench-detail-row"><span class="bench-detail-label">Status</span><span>${statusPill}</span></div>
                                </div>
                            </td>
                        </tr>`;
                    } else {
                        // Desktop: All separate columns
                        tableRows += `
                        <tr class="${rowClass}">
                            <td class="border px-3 py-2 timestamp-col" data-timestamp="${escapeHtml(benchmark.timestamp)}">${escapeHtml(getLocalizedTime(benchmark.timestamp))}</td>
                            <td class="border px-3 py-2 node-col">${safeNode}</td>
                            <td class="border px-3 py-2 upload-col num">${uploadValue} MB/s</td>
                            <td class="border px-3 py-2 download-col num">${downloadValue} MB/s</td>
                            <td class="border px-3 py-2 ttfb-col num">${ttfbValue} ms</td>
                            <td class="border px-3 py-2 success-col">${statusPill}</td>
                        </tr>`;
                    }
                });

                // Insert all rows at once into the table body
                tableBody.innerHTML = tableRows;
                showTable();
            } else {
                const tableBody = document.querySelector('#hostTableBody'); // Ensure this matches the correct selector
                const colspan = isCompactBenchmarksView() ? 2 : 6;
                tableBody.innerHTML = `
                <tr>
                    <td colspan="${colspan}" class="text-center">No benchmarks found for this host.</td>
                </tr>
            `;
                showTable();
            }
        }

        // Initialize charts and populate table once the page has fully loaded
        window.onload = function () {
            document.getElementById('nodeSelect').value = 'global';

            const initialData = buildChartData('global');

            const downloadCtx = document.getElementById('downloadChart').getContext('2d');
            downloadChart = new Chart(downloadCtx, {
                type: 'line',
                data: {
                    datasets: initialData.download
                },
                options: createSpeedChartOptions()
            });

            const uploadCtx = document.getElementById('uploadChart').getContext('2d');
            uploadChart = new Chart(uploadCtx, {
                type: 'line',
                data: {
                    datasets: initialData.upload
                },
                options: createSpeedChartOptions()
            });

            // Render the table for current viewport without flicker
            renderTableHeader();
            populateTable('global');
        };

        // Re-render on breakpoint changes
        (function setupResizeRerender(){
            let lastIsMobile = isMobileViewport();
            let lastIsCompact = isCompactBenchmarksView();
            window.addEventListener('resize', () => {
                const nowIsMobile = isMobileViewport();
                const nowIsCompact = isCompactBenchmarksView();
                if (nowIsMobile !== lastIsMobile) {
                    lastIsMobile = nowIsMobile;
                    const tickLimit = nowIsMobile ? 6 : 10;
                    if (downloadChart?.options?.scales?.x?.ticks) {
                        downloadChart.options.scales.x.ticks.maxTicksLimit = tickLimit;
                        downloadChart.update('none');
                    }
                    if (uploadChart?.options?.scales?.x?.ticks) {
                        uploadChart.options.scales.x.ticks.maxTicksLimit = tickLimit;
                        uploadChart.update('none');
                    }
                }
                if (nowIsCompact !== lastIsCompact) {
                    lastIsCompact = nowIsCompact;
                    populateTable(selectedNode);
                }
            });
        })();
    </script>

    <style>
        .benchmark-chart-wrap {
            position: relative;
            height: 400px;
            min-height: 280px;
            width: 100%;
        }

        .benchmark-chart-canvas {
            width: 100% !important;
            height: 100% !important;
            display: block;
        }

        .table-container .overflow-x-auto {
            max-height: none;
            overflow-y: visible;
            overflow-x: hidden;
            min-width: 0;
        }

        #hostTable {
            width: 100%;
            min-width: 0;
            table-layout: auto;
        }

        #hostTable th,
        #hostTable td {
            white-space: normal;
            overflow-wrap: anywhere;
            word-break: break-word;
            min-width: 0;
        }

        #hostTable .num {
            white-space: nowrap;
        }

        #hostTable thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background-color: rgba(24, 24, 24, 0.96);
        }

        #hostTable .table-sort-btn {
            width: 100%;
            text-align: left;
            background: transparent;
            border: 0;
            color: inherit;
            padding: 0;
            font-weight: inherit;
            line-height: 1.2;
        }

        #hostTable .table-sort-btn.num {
            text-align: right;
        }

        #hostTable .table-sort-btn:hover {
            text-decoration: underline;
        }

        #hostTable .timestamp-col {
            width: 170px;
        }

        #hostTable .node-col {
            width: 100px;
        }

        #hostTable .upload-col,
        #hostTable .download-col,
        #hostTable .ttfb-col {
            width: 130px;
        }

        #hostTable .success-col {
            width: 240px;
        }

        #hostTable.compact-table {
            table-layout: fixed;
        }

        #hostTable.compact-table .timestamp-col {
            width: 8.5rem;
            max-width: 8.5rem;
        }

        #hostTable.compact-table .node-col {
            width: auto;
        }

        #hostTable .status-pill {
            display: inline-block;
            padding: 0.15rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.8rem;
            line-height: 1.2;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
            vertical-align: middle;
        }

        #hostTable .status-pill--ok {
            color: #16a34a;
            border: 1px solid rgba(22, 163, 74, 0.6);
            background-color: rgba(22, 163, 74, 0.12);
        }

        #hostTable .status-pill--error {
            color: #f87171;
            border: 1px solid rgba(248, 113, 113, 0.6);
            background-color: rgba(248, 113, 113, 0.12);
        }

        @media (max-width: 767.98px) {
            .benchmark-chart-wrap {
                height: 320px;
            }

            #hostTable th,
            #hostTable td {
                white-space: normal;
                vertical-align: top;
            }

            #hostTable .timestamp-col {
                width: 124px;
            }

            #hostTable .node-col {
                width: auto;
            }

            #hostTable .bench-detail-list {
                display: grid;
                gap: 0.2rem;
                color: #d1d5db;
            }

            #hostTable .bench-detail-row {
                display: flex;
                justify-content: space-between;
                gap: 0.75rem;
                align-items: baseline;
            }

            #hostTable .bench-detail-label {
                color: #9ca3af;
                font-weight: 600;
            }

            #hostTable .status-pill {
                max-width: 180px;
            }
        }
    </style>

<?php render_footer(); ?>
