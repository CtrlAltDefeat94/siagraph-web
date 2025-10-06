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
                        <canvas id="uploadChart" style="height:400px !important;width: 100% !important;"></canvas>
                    </section>
                </section>
            </div>
            <div class="col-md-6">
                <section id="graph2-section" class="bg-dark p-3 rounded-3 bg-gradient shadow-lg">
                    <section class="graph-container">
                        <h2 class="card__heading">Download speed for
                            renters</h2>
                        <canvas id="downloadChart" style="height:400px !important;width: 100% !important;"></canvas>
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

        // Responsive helpers (match host_explorer behavior)
        function isMobile() {
            return window.innerWidth < 768; // Tailwind md breakpoint
        }

        function showTable() {
            const tbl = document.getElementById('hostTable');
            if (tbl) tbl.style.visibility = 'visible';
        }

        function hideTable() {
            const tbl = document.getElementById('hostTable');
            if (tbl) tbl.style.visibility = 'hidden';
        }

        function renderTableHeader() {
            const thead = document.querySelector('#hostTable thead');
            if (!thead) return;
            if (isMobile()) {
                thead.innerHTML = `
                    <tr class="bg-gray-900">
                        <th class="px-4 py-2 timestamp-col">Timestamp</th>
                        <th class="px-4 py-2 node-col">Node</th>
                    </tr>
                `;
            } else {
                thead.innerHTML = `
                    <tr class="bg-gray-900">
                        <th class="px-4 py-2 timestamp-col">Timestamp</th>
                        <th class="px-4 py-2 node-col">Node</th>
                        <th class="px-4 py-2 upload-col">Upload Speed</th>
                        <th class="px-4 py-2 download-col">Download Speed</th>
                        <th class="px-4 py-2 ttfb-col">Time to First Byte</th>
                        <th class="px-4 py-2 success-col">Success</th>
                    </tr>
                `;
            }
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
                    pointRadius: 5,
                    pointHitRadius: 10,
                    pointHoverRadius: 7,
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
                    pointRadius: 5,
                    pointHitRadius: 10,
                    pointHoverRadius: 7,
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
                decimalPlaces: 2
            });
            datasetsDownload.push({
                label: 'Upper Bound',
                data: dailyDates.map((t, i) => ({ x: t, y: downloadBounds[0][i] })),
                borderColor: 'rgba(50,205,50,0.5)',
                pointRadius: 0,
                fill: '+1',
                backgroundColor: 'rgba(50,205,50,0.1)',
                borderWidth: 0,
                decimalPlaces: 2
            });
            datasetsDownload.push({
                label: 'Lower Bound',
                data: dailyDates.map((t, i) => ({ x: t, y: downloadBounds[1][i] })),
                borderColor: 'rgba(50,205,50,0.5)',
                pointRadius: 0,
                fill: false,
                borderWidth: 0,
                decimalPlaces: 2
            });

            datasetsUpload.push({
                label: 'Daily Avg.',
                data: dailyDates.map((t, i) => ({ x: t, y: avgUploadSpeeds[i] })),
                borderColor: 'green',
                backgroundColor: 'rgba(0,255,0,0.2)',
                fill: false,
                pointRadius: 0,
                borderWidth: 2,
                decimalPlaces: 2
            });
            datasetsUpload.push({
                label: 'Upper Bound',
                data: dailyDates.map((t, i) => ({ x: t, y: uploadBounds[0][i] })),
                borderColor: 'rgba(50,205,50,0.5)',
                pointRadius: 0,
                fill: '+1',
                backgroundColor: 'rgba(50,205,50,0.1)',
                borderWidth: 0,
                decimalPlaces: 2
            });
            datasetsUpload.push({
                label: 'Lower Bound',
                data: dailyDates.map((t, i) => ({ x: t, y: uploadBounds[1][i] })),
                borderColor: 'rgba(50,205,50,0.5)',
                pointRadius: 0,
                fill: false,
                borderWidth: 0,
                decimalPlaces: 2
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
                const benchmarks = groupedBenchmarks[node];
                const tableBody = document.querySelector('#hostTableBody'); // Ensure this matches the correct selector
                let tableRows = ''; // Accumulate rows as a string

                benchmarks.forEach((benchmark, index) => {
                    const isEvenRow = (parseInt(index, 10) + 1) % 2 === 0;
                    const rowClass = isEvenRow ? 'bg-gray-800' : 'bg-gray-900';
                    if (isMobile()) {
                        // Mobile: Only Timestamp + Node with inline details
                        tableRows += `
                        <tr class="${rowClass}">
                            <td class="border px-4 py-2 timestamp-col" data-timestamp="${benchmark.timestamp}">${getLocalizedTime(benchmark.timestamp)}</td>
                            <td class="border px-4 py-2 node-col">
                                ${benchmark.node}
                                <div class="text-xs text-gray-400 mt-1">
                                    <span class="whitespace-nowrap">Up: ${(benchmark.uploadSpeed / 1000000).toFixed(2)} MB/s</span> ·
                                    <span class="whitespace-nowrap">Down: ${(benchmark.downloadSpeed / 1000000).toFixed(2)} MB/s</span> ·
                                    <span class="whitespace-nowrap">TTFB: ${(benchmark.ttfb / 1000000)} ms</span> ·
                                    <span class="whitespace-nowrap">${benchmark.success ? 'OK' : benchmark['error']}</span>
                                </div>
                            </td>
                        </tr>`;
                    } else {
                        // Desktop: All separate columns
                        tableRows += `
                        <tr class="${rowClass}">
                            <td class="border px-4 py-2 timestamp-col" data-timestamp="${benchmark.timestamp}">${getLocalizedTime(benchmark.timestamp)}</td>
                            <td class="border px-4 py-2 node-col">${benchmark.node}</td>
                            <td class="border px-4 py-2 upload-col">${(benchmark.uploadSpeed / 1000000).toFixed(2)} MB/s</td>
                            <td class="border px-4 py-2 download-col">${(benchmark.downloadSpeed / 1000000).toFixed(2)} MB/s</td>
                            <td class="border px-4 py-2 ttfb-col">${(benchmark.ttfb / 1000000)} ms</td>
                            <td class="border px-4 py-2 success-col">${benchmark.success ? 'OK' : benchmark['error']}</td>
                        </tr>`;
                    }
                });

                // Insert all rows at once into the table body
                tableBody.innerHTML = tableRows;
                showTable();
            } else {
                const tableBody = document.querySelector('#hostTableBody'); // Ensure this matches the correct selector
                const colspan = isMobile() ? 2 : 6;
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
                options: {
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'MB/s'
                            },
                            startAtZero: true
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
                                maxTicksLimit: 10
                            }
                        }
                    },
                    interaction: { mode: 'nearest', intersect: false },
                    plugins: {
                        title: { display: false },
                        legend: { onClick: e => e.stopPropagation() },
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
                                    label += value.toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                    return label;
                                },
                                title: items => moment(items[0].parsed.x).format('DD MMM YYYY')
                            }
                        }
                    }
                }
            });

            const uploadCtx = document.getElementById('uploadChart').getContext('2d');
            uploadChart = new Chart(uploadCtx, {
                type: 'line',
                data: {
                    datasets: initialData.upload
                },
                options: {
                    scales: {
                        y: {
                            title: {
                                display: true,
                                text: 'MB/s'
                            },
                            startAtZero: true
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
                                maxTicksLimit: 10
                            }
                        }
                    },
                    interaction: { mode: 'nearest', intersect: false },
                    plugins: {
                        title: { display: false },
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
                                    label += value.toFixed(decimals).replace(/\d(?=(\d{3})+\.)/g, '$&,');
                                    return label;
                                },
                                title: items => moment(items[0].parsed.x).format('DD MMM YYYY')
                            }
                        }
                    }
                }
            });

            // Render the table for current viewport without flicker
            renderTableHeader();
            populateTable('global');
        };

        // Re-render on breakpoint changes
        (function setupResizeRerender(){
            let lastIsMobile = isMobile();
            window.addEventListener('resize', () => {
                const nowIsMobile = isMobile();
                if (nowIsMobile !== lastIsMobile) {
                    lastIsMobile = nowIsMobile;
                    // Re-render current node table
                    populateTable(selectedNode);
                }
            });
        })();
    </script>

    <style>
        #hostTable th,
        #hostTable td {
            white-space: nowrap;
        }

        #hostTable .timestamp-col {
            width: 170px;
        }

        #hostTable .node-col {
            width: 90px;
        }

        #hostTable .upload-col,
        #hostTable .download-col,
        #hostTable .ttfb-col {
            width: 130px;
        }

        #hostTable .success-col {
            width: 80px;
        }
    </style>

<?php render_footer(); ?>
