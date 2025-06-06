<?php
include_once 'include/graph.php';
include_once "include/redis.php";
include_once "include/utils.php";

$recentstats = getCache($recentStatsKey);
if (isset($recentstats)) {
    $recentstats = json_decode($recentstats, true);
}

$currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur';

?>
<!DOCTYPE html>
<html>

<head>
  <link href="css/tailwind.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <link href="css/bootstrap.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.0/nouislider.min.css" rel="stylesheet">
    <script src="script.js"></script>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiaGraph</title>
    <meta property="og:title" content="SiaGraph" />
    <meta property="og:description" content="A statistics website for the Sia Network." />
    <link rel="icon" href="img/favicon.ico" type="image/png">
</head>

<body>
    <!-- Header Section -->
    <?php include "include/header.html" ?>

    <!-- Main Content Section -->
    <section id="main-content" class="container mt-4 pb-5 max-w-screen-xl">
        <!-- Row for new section and additional info section -->
        <div class="row">
            <!-- Additional Information Section -->
            <div class="col-md-6">
                <section id="additional-info" class="bg-light p-3 rounded-3">
                    <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">
                        Growth in the past day
                    </h2>
                    <!-- Used Storage -->
                    <div class="row mt-4">
                        <!-- Statistics Section -->
                        <div class="col-md-12">
                            <div class="row">
                                <!-- Used Storage -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">Utilized Storage</span>
                                        <br><span id="stats1a"
                                            class="glanceNumber fs-4"><?php echo !empty($recentstats) ? formatBytes($recentstats['actual']['utilized_storage']) : 0; ?></span>
                                        <br><span id="stats1b"
                                            class="fs-6">(<?php echo !empty($recentstats) ? prependPlusIfNeeded(formatBytes($recentstats['change']['utilized_storage'])) : 0; ?>)</span>
                                    </div>
                                </div>

                                <!-- Active Contracts -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">Active Contracts</span>
                                        <br><span id="stats2a"
                                            class="glanceNumber fs-4"><?php echo !empty($recentstats) ? $recentstats['actual']['active_contracts'] : 0; ?></span>
                                        <span id="stats2b"
                                            class="fs-6">(<?php echo !empty($recentstats) ? prependPlusIfNeeded($recentstats['change']['active_contracts']) : 0; ?>)</span>
                                    </div>
                                </div>

                                <!-- 30-day Network Revenue -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">30-day Network Revenue</span>
                                        <br>
                                        <span id="stats3a"
                                            class="glanceNumber fs-4"><?php echo strtoupper($currencyCookie) . " ";
                                            echo !empty($recentstats) ? $recentstats['actual']['30_day_revenue'][$currencyCookie] : 0; ?></span>
                                        <span id="stats3b"
                                            class="fs-6">(<?php echo strtoupper($currencyCookie) . " ";
                                            echo !empty($recentstats) ? prependPlusIfNeeded($recentstats['change']['30_day_revenue'][$currencyCookie]) : 0; ?>)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Right column for stats4 to stats6 -->
                        <div class="col-md-12 mt-4">
                            <div class="row">
                                <!-- Network Capacity -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">Network Capacity</span>
                                        <br><span id="stats4a"
                                            class="glanceNumber fs-4"><?php echo !empty($recentstats) ? formatBytes($recentstats['actual']['total_storage']) : 0; ?></span>
                                        <br><span id="stats4b"
                                            class="fs-6">(<?php echo !empty($recentstats) ? prependPlusIfNeeded(formatBytes($recentstats['change']['total_storage'])) : 0; ?>)</span>
                                    </div>
                                </div>
                                <!-- Online Hosts -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">Online Hosts</span>
                                        <br><span id="stats5a"
                                            class="glanceNumber fs-4"><?php echo !empty($recentstats) ? $recentstats['actual']['online_hosts'] : 0; ?></span>
                                        <span id="stats5b"
                                            class="fs-6">(<?php echo !empty($recentstats) ? prependPlusIfNeeded($recentstats['change']['online_hosts']) : 0; ?>)</span>
                                    </div>
                                </div>

                                <!-- Siacoin Market Value -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">Siacoin Market Value</span>
                                        <br>
                                        <span id="stats6a"
                                            class="glanceNumber fs-4"><?php echo strtoupper($currencyCookie) . " " . (!empty($recentstats) ? $recentstats['actual']['coin_price'][$currencyCookie] : 0); ?>
                                        </span>
                                        <span id="stats6b"
                                            class="fs-6">(<?php echo strtoupper($currencyCookie) . " ";
                                            echo !empty($recentstats) ? prependPlusIfNeeded(string: $recentstats['change']['coin_price'][$currencyCookie]) : 0; ?>)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <div class="col-md-6">
                <section id="blockchain-explorer" class="bg-light p-3 rounded-3">
                    <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">
                        Blockchain Explorer
                    </h2>
                    <div class="text-right space-y-2">
                        <!-- Connected peers at the top -->
                        <div class="text-gray-500 text-xs">
                            Connected peers: <span id="connected-peers" class="font-semibold"></span>
                        </div>
                    </div>
                    <!-- Block height, found time, and Connected peers -->
                    <div class="bg-gray-200 p-2 rounded-lg flex justify-between text-sm">
                        <!-- Block height and time on the left -->
                        <div>
                            <span id="block-height" class="font-bold text-5xl">0</span>
                            <!-- Time block was found -->
                            <span id="block-found-time" class="text-gray-500 text-sm block mt-1">
                                Found at: 1970-01-01
                            </span>
                            <!-- Time since the block was found (HH:MM:SS format) -->
                            <span id="time-since-found" class="text-gray-600 text-sm block">Time since: 00:00:00</span>
                            <span id="time-average" class="text-gray-600 text-sm block">Recent average: 00:00:00</span>

                        </div>

                        <div class="text-right space-y-2">

                            <div class="flex justify-end items-center space-x-2">
                                <span class="fs-6">New contracts:</span>
                                <span id="new-contracts" class="glanceNumber fs-4"></span>
                            </div>
                            <div class="flex justify-end items-center space-x-2">
                                <span class="fs-6">Completed contracts:</span>
                                <span id="completed-contracts" class="glanceNumber fs-4"></span>
                            </div>
                            <div class="flex justify-end items-center space-x-2">
                                <span class="fs-6">New hosts:</span>
                                <span id="new-hosts" class="glanceNumber fs-4"></span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-gray-200 p-2 rounded-lg flex justify-between text-sm mt-2">
                        <div>
                            <span class="block fs-6">Next block</span>
                            <span id="next-block" class="block font-semibold text-lg"></span>
                        </div>
                        <div class="flex flex-col items-end">
                            <span class="block fs-6">Unconfirmed transactions</span>
                            <span id=unconfirmed-transactions class="block font-semibold text-lg"></span>
                        </div>
                    </div>
                    <div class="flex justify-center items-center mt-2 space-x-2">
                        <span class="text-gray-600 font-bold text-sm">Estimated hardfork completion date:</span>
                        <span id="estimated-v2-time" class="text-gray-600 font-bold text-sm">00:00:00</span>
                    </div>
                </section>
            </div>

        </div>

        <div class="row align-items-start mt-4">
            <div class="col-md-6">
                <section id="graph-section" class="bg-light p-3 rounded-3">
                    <!-- Graph Section for Network -->
                    <section class="graph-container">
                        <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">
                            Utilized Storage
                        </h2>
                        <?php
                        renderGraph(
                            $canvasid = "networkstorage",
                            $datasets = [
                                [
                                    'label' => 'Utilized Storage',
                                    'key' => 'total_storage', 
                                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                                    'borderColor' => 'rgba(75, 192, 192, 1)',
                                    'transform' => "return entry['utilized_storage'];",
                                    'unit' => 'PB',
                                    'unitDivisor' => 1e15,
                                    'decimalPlaces' => 2,
                                    'startAtZero' => false
                                ]
                            ],
                            $dateKey = "date",
                            $jsonUrl = "/api/v1/daily/growth", // JSON URL
                            $jsonData = getCache($metricsKey),             // JSON key for date
                            $charttype = 'line',
                            $interval = 'month',
                            $rangeslider = false,
                            $displaylegend = false,
                            $defaultrangeinmonths = 3,
                            $displayYAxis = "false",
                            $unitType = 'bytes',
                            $jsonKey = null
                        );
                        ?>
                    </section>
                </section>
            </div>

            <div class="col-md-6">
                <section id="graph2-section" class="bg-light p-3 rounded-3">
                    <!-- Graph Section for Network -->
                    <section class="graph-container">
                        <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">
                            Monthly Revenue
                        </h2>
                        <?php
                        // Call the function with specific parameters
                        renderGraph(
                            $canvasid = "monthlyrevenue",
                            $datasets = [
                                [
                                    'label' => 'Monthly revenue',
                                    'key' => 'total_storage', // Modify based on your data
                                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                                    'borderColor' => 'rgba(75, 192, 192, 1)',
                                    'transform' => "return entry['" . $currencyCookie . "'];",
                                    'startAtZero' => true
                                ]
                            ],
                            $dateKey = "date",
                            $jsonUrl = '/api/v1/monthly/revenue', // JSON URL
                            $jsonData = null,#getCache($revenueMonthlyKey),
                            $charttype = 'bar',

                            $interval = 'month',
                            $rangeslider = false,
                            $displaylegend = false,
                            $defaultrangeinmonths = 6,
                            $displayYAxis = "false",
                            $unitType = $currencyCookie,
                            $jsonKey = null
                        );
                        ?>
                    </section>
                </section>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <?php include "include/footer.php" ?>
    <!-- Import Moment.js library -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <!-- Import Chart.js 3 library with Moment adapter -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.0/nouislider.min.js"></script>

</body>

<script>
    const url = '/api/v1/daily/compare_metrics';
    const cachedData = <?php echo json_encode($recentstats); ?>;
    const currencyCookie = document.cookie.replace(/(?:(?:^|.*;\s*)currency\s*=\s*([^;]*).*$)|^.*$/, "$1") || 'eur';
    const timeSinceElement = document.getElementById('time-since-found');
    const blockFoundTimeString = document.getElementById('block-found-time').textContent.trim();
    const estimatedV2TimeString = document.getElementById('estimated-v2-time').textContent.trim();
    const extractedDateString = blockFoundTimeString.replace('Found at: ', '').trim();

    let currentHeight = 0;


    // Create a new Date object from the string
    let blockFoundTime;
    let estimatedV2Time;
    async function fetchExplorerData() {
        // Fetch the consolidated explorer metrics JSON data
        const explorerData = await fetchData('/api/v1/explorer_metrics');

        if (explorerData) {
            // Extract data from the fetched JSON object
            const {
                blockHeight,
                averageFoundSeconds,
                blockFoundTime: fetchedBlockFoundTime,
                estimatedV2Time: fetchedEstimatedV2Time,
                unconfirmedTransactions,
                connectedPeers,
                newHosts,
                completedContracts,
                newContracts
            } = explorerData;

            blockFoundTime = fetchedBlockFoundTime;
            estimatedV2Time = fetchedEstimatedV2Time;
            // Update the HTML elements with the fetched data
            let minutes = Math.floor(averageFoundSeconds / 60);  // Get the number of full minutes
            let seconds = averageFoundSeconds % 60;
            let averageFoundTime = `Recent average: ${minutes} minutes ${seconds} seconds`;
            document.getElementById('block-height').innerText = blockHeight;
            document.getElementById('next-block').innerText = blockHeight + 1;
            document.getElementById('block-found-time').innerText = new Date(blockFoundTime).toLocaleString();
            document.getElementById('estimated-v2-time').innerText = new Date(estimatedV2Time).toLocaleString();
            document.getElementById('time-average').innerText = averageFoundTime;
            document.getElementById('unconfirmed-transactions').innerText = unconfirmedTransactions;
            document.getElementById('connected-peers').innerText = connectedPeers;
            document.getElementById('new-hosts').innerText = newHosts;
            document.getElementById('completed-contracts').innerText = completedContracts;
            document.getElementById('new-contracts').innerText = newContracts;
        }
    }
    async function fetchData(url) {
        try {
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Content-Type': 'application/json' }
            });

            if (!response.ok) throw new Error(`Unexpected HTTP code: ${response.status}`);
            return await response.json();
        } catch (error) {
            console.error("Error fetching data:", error.message);
            return null;
        }
    }

    async function fetchDataAndUpdateUI() {
        let data = cachedData && cachedData.length ? cachedData : null;
        if (!data) {
            try {
                const response = await fetch(url, {
                    method: 'GET',
                    headers: { 'Content-Type': 'application/json' }
                });

                if (!response.ok) throw new Error(`Unexpected HTTP code: ${response.status}`);

                data = await response.json();
            } catch (error) {
                console.error("Error fetching data:", error.message);
                //setDefaultValues();
                return;
            }
        }

        if (data) {
            updateUI(data);
        }
    }

    function updateUI(data) {
        const elements = {
            stats1a: document.getElementById('stats1a'),
            stats1b: document.getElementById('stats1b'),
            stats2a: document.getElementById('stats2a'),
            stats2b: document.getElementById('stats2b'),
            stats3a: document.getElementById('stats3a'),
            stats3b: document.getElementById('stats3b'),
            stats4a: document.getElementById('stats4a'),
            stats4b: document.getElementById('stats4b'),
            stats5a: document.getElementById('stats5a'),
            stats5b: document.getElementById('stats5b'),
            stats6a: document.getElementById('stats6a'),
            stats6b: document.getElementById('stats6b')
        };

        const stats = {
            stats1: formatBytes(data.actual.utilized_storage),
            stats1Change: prependPlusIfNeeded(formatBytes(data.change.utilized_storage)),
            stats2: data.actual.active_contracts,
            stats2Change: prependPlusIfNeeded(data.change.active_contracts),
            stats3: data.actual["30_day_revenue"],
            stats3Change: prependPlusIfNeeded(data.change["30_day_revenue"][currencyCookie]),
            stats4: formatBytes(data.actual.total_storage),
            stats4Change: prependPlusIfNeeded(formatBytes(data.change.total_storage)),
            stats5: data.actual.online_hosts,
            stats5Change: prependPlusIfNeeded(data.change.online_hosts),
            stats6: data.actual.coin_price,
            stats6Change: prependPlusIfNeeded(data.change.coin_price[currencyCookie])
        };

        // Update storage
        elements.stats1a.textContent = stats.stats1;
        elements.stats1b.textContent = `(${stats.stats1Change})`;

        // Update contracts
        elements.stats2a.textContent = stats.stats2;
        elements.stats2b.textContent = `(${stats.stats2Change})`;
        // Update revenue (currency sensitive)
        elements.stats3a.textContent = formatCurrency(stats.stats3);
        elements.stats3b.textContent = formatCurrencyChange(stats.stats3Change);

        // Update total storage
        elements.stats4a.textContent = stats.stats4;
        elements.stats4b.textContent = `(${stats.stats4Change})`;

        // Update online hosts
        elements.stats5a.textContent = stats.stats5;
        elements.stats5b.textContent = `(${stats.stats5Change})`;

        // Update coin price
        elements.stats6a.textContent = formatCurrency(stats.stats6);
        elements.stats6b.textContent = formatCurrencyChange(stats.stats6Change);
    }

    function formatCurrency(value) {
        return currencyCookie === 'eur' ? `EUR ${value.eur}` : `USD ${value.usd}`;
    }

    function formatCurrencyChange(change) {
        //const changeValue = change[currencyCookie];
        return currencyCookie === 'eur' ? `(EUR ${change})` : `(USD ${change})`;
    }

    function setDefaultValues() {
        const defaults = {
            stats1a: '0', stats1b: '(0)',
            stats2a: '0', stats2b: '0',
            stats3a: 'EUR 0', stats3b: '(EUR 0)',
            stats4a: '0', stats4b: '(0)',
            stats5a: '0', stats5b: '(0)',
            stats6a: 'EUR 0', stats6b: '(EUR 0)'
        };

        for (const [id, value] of Object.entries(defaults)) {
            document.getElementById(id).textContent = value;
        }
    }

    function formatBytes(bytes) {
        const isNegative = bytes < 0;
        bytes = Math.abs(bytes);

        if (bytes === 0) return '0 Bytes';

        const units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
        let unitIndex = 0;

        while (bytes >= 1000 && unitIndex < units.length - 1) {
            bytes /= 1000;
            unitIndex++;
        }

        const formatted = `${bytes.toFixed(3)} ${units[unitIndex]}`;
        return isNegative ? `-${formatted}` : formatted;
    }
    function prependPlusIfNeeded(input) {
        // Convert the input to a string
        const string = input.toString();

        // Check if the first character is not a minus
        if (string.charAt(0) !== '-') {
            // Prepend a plus sign and return the new string
            return '+' + string;
        }

        // Return the original string if it starts with a minus
        return string;
    }
    function updateTimeSinceFound() {
        const now = new Date();
        const blockFoundTimeDate = new Date(blockFoundTime)
        const elapsed = Math.floor((now - blockFoundTimeDate) / 1000);
        const days = Math.floor(elapsed / 86400); // Number of full days
        const hours = String(Math.floor((elapsed % 86400) / 3600)).padStart(2, '0'); // Remaining hours
        const minutes = String(Math.floor((elapsed % 3600) / 60)).padStart(2, '0');
        const seconds = String(elapsed % 60).padStart(2, '0');

        if (days > 0) {
            timeSinceElement.textContent = `Time since: ${days} days ${hours}:${minutes}:${seconds}`;
        } else {
            timeSinceElement.textContent = `Time since: ${hours}:${minutes}:${seconds}`;
        }
    }


    // Call functions on page load
    if (!cachedData) {
        window.onload = fetchDataAndUpdateUI();
    }
    window.onload = fetchExplorerData;
    setInterval(updateTimeSinceFound, 1000);
    setInterval(fetchExplorerData, 30000);
</script>

</html>