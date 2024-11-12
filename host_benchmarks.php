<?php

#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);


include_once "include/database.php";
include_once "include/utils.php";
include_once "include/graph.php";
include_once "include/redis.php";

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

// Calculate stats for 24-hour windows
function calculate24HourStats($timestamps, $speeds)
{
    $averageSpeeds = [];
    $stdDevs = [];
    $windowSize = 24 * 60 * 60; // 24 hours in seconds

    for ($i = 0; $i < count($timestamps); $i++) {
        $currentTimestamp = strtotime($timestamps[$i]);
        $windowSpeeds = [];

        for ($j = 0; $j <= $i; $j++) {
            $compareTimestamp = strtotime($timestamps[$j]);
            if ($currentTimestamp - $compareTimestamp <= $windowSize) {
                $windowSpeeds[] = $speeds[$j];
            }
        }

        $average = count($windowSpeeds) > 0 ? array_sum($windowSpeeds) / count($windowSpeeds) : 0;
        $averageSpeeds[] = $average;

        if (count($windowSpeeds) > 1) {
            $squaredDiffs = array_map(function ($speed) use ($average) {
                return pow($speed - $average, 2);
            }, $windowSpeeds);
            $stdDev = sqrt(array_sum($squaredDiffs) / count($windowSpeeds));
        } else {
            $stdDev = 0;
        }
        $stdDevs[] = $stdDev;
    }

    return [$averageSpeeds, $stdDevs];
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

list($avgDownloadSpeeds, $downloadStdDevs) = calculate24HourStats($timestamps, $downloadSpeeds);
list($avgUploadSpeeds, $uploadStdDevs) = calculate24HourStats($timestamps, $uploadSpeeds);

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

        // Construct the API URL
        $url = "https://api.hostscore.info/v1/hosts/benchmarks?network=mainnet&host=" . $public_key . "&all=true&from=" . $formattedDate;

        // Fetch data from the API
        $response = file_get_contents($url);

        // Check if the response was successful
        if ($response === false) {
            echo 'Error fetching data from API.';
            return null; // Handle the error appropriately
        }

        // Decode the JSON response
        $data = json_decode($response, true);

        // Check for JSON decoding errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo 'JSON decode error: ' . json_last_error_msg();
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

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiaGraph</title>
    <script src="script.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@latest/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.0/nouislider.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/moment"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment"></script>

</head>

<body>

    <!-- Header Section -->
    <?php include "include/header.html" ?>

    <!-- Main Content Section -->
    <section id="main-content" class="container mt-4 pb-5 masonry-container">
        <div class="flex flex-wrap justify-start mt-4 mb-2 gap-2">
            <a class="cursor-pointer hover:underline flex items-center font-bold text-xl"
                href='/host_explorer'>Top Hosts</a>
            <span class="flex items-center font-bold text-xl">/</span>
            <a class="cursor-pointer hover:underline flex items-center font-bold text-xl"
                href='/host.php?id=<?php echo $host_id; ?>'>
                <?php echo $net_address; ?>
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
                <section id="graph2-section" class="bg-light p-3 rounded-3">
                    <section class="graph-container">
                        <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Upload speed for
                            renters</h2>
                        <canvas id="uploadChart" style="height:400px !important;width: 100% !important;"></canvas>
                    </section>
                </section>
            </div>
            <div class="col-md-6">
                <section id="graph2-section" class="bg-light p-3 rounded-3">
                    <section class="graph-container">
                        <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Download speed for
                            renters</h2>
                        <canvas id="downloadChart" style="height:400px !important;width: 100% !important;"></canvas>
                    </section>
                </section>
            </div>
        </div>

        <!-- Benchmarks Table -->
        <section class="table-container">
            <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Benchmarks of the past 7 days
            </h2>
            <div class="overflow-x-auto">
                <table id="hostTable" class="table-auto min-w-full border-collapse">
                    <thead>
                        <tr class="bg-gray-100">
                            <th class="px-4 py-2">Timestamp</th>
                            <th class="px-4 py-2">Node</th>
                            <th class="px-4 py-2">Upload Speed</th>
                            <th class="px-4 py-2">Download Speed</th>
                            <th class="px-4 py-2">Time to First Sector</th>
                            <th class="px-4 py-2">Success</th>
                        </tr>
                    </thead>
                    <tbody id="hostTableBody"></tbody>
                </table>
            </div>
        </section>
    </section>


    <script>
        const groupedBenchmarks = <?php echo json_encode($groupedBenchmarks); ?>;
        let selectedNode = 'global';

        function updateChartsForNode(node) {
            const benchmarks = groupedBenchmarks[node];

            const timestamps = benchmarks.map(b => new Date(b.timestamp));
            const downloadSpeeds = benchmarks.map(b => b.downloadSpeed / 1000000); // Convert to Mbps
            const uploadSpeeds = benchmarks.map(b => b.uploadSpeed / 1000000); // Convert to Mbps

            const avgDownloadSpeeds = calculate24HourStats(timestamps, downloadSpeeds)[0];
            const downloadStdDevs = calculate24HourStats(timestamps, downloadSpeeds)[1];

            const avgUploadSpeeds = calculate24HourStats(timestamps, uploadSpeeds)[0];
            const uploadStdDevs = calculate24HourStats(timestamps, uploadSpeeds)[1];

            const downloadBounds = calculateBounds(avgDownloadSpeeds, downloadStdDevs);
            const uploadBounds = calculateBounds(avgUploadSpeeds, uploadStdDevs);

            // Update charts
            updateChart(downloadChart, timestamps, downloadSpeeds, avgDownloadSpeeds, downloadBounds);
            updateChart(uploadChart, timestamps, uploadSpeeds, avgUploadSpeeds, uploadBounds);
        }

        function updateChart(chart, labels, benchmarkData, avgSpeed, bounds) {
            chart.data.labels = labels;
            chart.data.datasets[0].data = benchmarkData; // Update benchmarks
            chart.data.datasets[1].data = avgSpeed; // Update 24h average
            chart.data.datasets[2].data = bounds[0]; // Update upper bound
            chart.data.datasets[3].data = bounds[1]; // Update lower bound
            chart.update();
        }

        function calculate24HourStats(timestamps, speeds) {
            const averageSpeeds = [];
            const stdDevs = [];
            const windowSize = 24 * 60 * 60 * 1000; // 24 hours in milliseconds

            for (let i = 0; i < timestamps.length; i++) {
                const currentTimestamp = timestamps[i].getTime();
                const windowSpeeds = speeds.slice(0, i + 1).filter((_, j) => {
                    const compareTimestamp = timestamps[j].getTime();
                    return currentTimestamp - compareTimestamp <= windowSize;
                });

                const average = windowSpeeds.length > 0 ? windowSpeeds.reduce((a, b) => a + b, 0) / windowSpeeds.length : 0;
                averageSpeeds.push(average);

                if (windowSpeeds.length > 1) {
                    const squaredDiffs = windowSpeeds.map(speed => Math.pow(speed - average, 2));
                    const stdDev = Math.sqrt(squaredDiffs.reduce((a, b) => a + b, 0) / windowSpeeds.length);
                    stdDevs.push(stdDev);
                } else {
                    //console.log('ignore');
                    stdDevs.push(0);
                }
            }

            return [averageSpeeds, stdDevs];
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

        // Initial chart setup for global data
        const globalBenchmarks = groupedBenchmarks['global'];
        const timestamps = globalBenchmarks.map(b => new Date(b.timestamp));
        const downloadSpeeds = globalBenchmarks.map(b => b.downloadSpeed / 1000000);
        const uploadSpeeds = globalBenchmarks.map(b => b.uploadSpeed / 1000000);

        const avgDownloadSpeeds = calculate24HourStats(timestamps, downloadSpeeds)[0];
        const downloadStdDevs = calculate24HourStats(timestamps, downloadSpeeds)[1];

        const avgUploadSpeeds = calculate24HourStats(timestamps, uploadSpeeds)[0];
        const uploadStdDevs = calculate24HourStats(timestamps, uploadSpeeds)[1];

        const downloadBounds = calculateBounds(avgDownloadSpeeds, downloadStdDevs);
        const uploadBounds = calculateBounds(avgUploadSpeeds, uploadStdDevs);

        const downloadChart = new Chart(document.getElementById('downloadChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: timestamps, // X-axis (Timestamps)
                datasets: [
                    {
                        label: 'Benchmarks',
                        data: downloadSpeeds, // Benchmark data (upload/download)
                        borderColor: 'transparent', // No connecting line
                        backgroundColor: 'gray',
                        pointBackgroundColor: 'gray', // Dots
                        fill: false,
                        pointRadius: 5,  // Size of the dots for benchmarks
                        showLine: false  // Disable lines for this dataset
                    },
                    {
                        label: '24h Avg.',
                        data: avgDownloadSpeeds, // 24h average data
                        borderColor: 'green', // Line color for average
                        backgroundColor: 'rgba(0,255,0,0.2)', // Optional fill under line
                        fill: false,  // No fill for average line
                        pointRadius: 0,  // No points for 24h average
                        borderWidth: 2, // Make the line thicker
                    },
                    {
                        label: 'Upper Bound',
                        data: downloadBounds[0], // Upper boundary for std deviation
                        borderColor: 'rgba(50,205,50,0.5)', // Lighter line for the upper bound
                        pointRadius: 0,  // No points for this dataset
                        fill: '+1',  // Fill between the upper and lower bound
                        backgroundColor: 'rgba(50,205,50,0.1)', // Light fill color for standard deviation
                        borderWidth: 0, // No actual border for upper bound
                    },
                    {
                        label: 'Lower Bound',
                        data: downloadBounds[1], // Lower boundary for std deviation
                        borderColor: 'rgba(50,205,50,0.5)', // Lighter line for the lower bound
                        pointRadius: 0,  // No points for this dataset
                        fill: false,  // No fill needed for lower boundary itself
                        borderWidth: 0, // No actual border for lower bound
                    }
                ]
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
                        type: 'time', // Set the x-axis to a time scale
                        title: {
                            display: false,
                            text: 'Date'
                        },
                        time: {
                            unit: 'day', // Show data by day
                            tooltipFormat: 'MMM DD', // Tooltip format for hover
                            displayFormats: {
                                day: 'MMM DD' // Format for x-axis ticks
                            }
                        },
                        ticks: {
                            autoSkip: true, // Automatically skip ticks if they're too dense
                            maxTicksLimit: 10 // Limit the maximum number of ticks
                        }
                    }
                },
                plugins: {
                    title: {
                        display: false,
                    },
                    legend: { onClick: function (e) { e.stopPropagation(); } }
                }
            }
        });

        const uploadChart = new Chart(document.getElementById('uploadChart').getContext('2d'), {
            type: 'line',
            data: {
                labels: timestamps, // X-axis (Timestamps)
                datasets: [
                    {
                        label: 'Benchmarks',
                        data: uploadSpeeds, // Benchmark data (upload/download)
                        borderColor: 'transparent', // No connecting line
                        backgroundColor: 'gray',
                        pointBackgroundColor: 'gray', // Dots
                        fill: false,
                        pointRadius: 5,  // Size of the dots for benchmarks
                        showLine: false  // Disable lines for this dataset
                    },
                    {
                        label: '24h Avg.',
                        data: avgUploadSpeeds, // 24h average data
                        borderColor: 'green', // Line color for average
                        backgroundColor: 'rgba(0,255,0,0.2)', // Optional fill under line
                        fill: false,  // No fill for average line
                        pointRadius: 0,  // No points for 24h average
                        borderWidth: 2, // Make the line thicker
                    },
                    {
                        label: 'Upper Bound',
                        data: uploadBounds[0], // Upper boundary for std deviation
                        borderColor: 'rgba(50,205,50,0.5)', // Lighter line for the upper bound
                        pointRadius: 0,  // No points for this dataset
                        fill: '+1',  // Fill between the upper and lower bound
                        backgroundColor: 'rgba(50,205,50,0.1)', // Light fill color for standard deviation
                        borderWidth: 0, // No actual border for upper bound
                    },
                    {
                        label: 'Lower Bound',
                        data: uploadBounds[1], // Lower boundary for std deviation
                        borderColor: 'rgba(50,205,50,0.5)', // Lighter line for the lower bound
                        pointRadius: 0,  // No points for this dataset
                        fill: false,  // No fill needed for lower boundary itself
                        borderWidth: 0, // No actual border for lower bound
                    }
                ]
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
                        type: 'time', // Set the x-axis to a time scale
                        title: {
                            display: false,
                            text: 'Date'
                        },
                        time: {
                            unit: 'day', // Show data by day
                            tooltipFormat: 'MMM DD', // Tooltip format for hover
                            displayFormats: {
                                day: 'MMM DD' // Format for x-axis ticks
                            }
                        },
                        ticks: {
                            autoSkip: true, // Automatically skip ticks if they're too dense
                            maxTicksLimit: 10 // Limit the maximum number of ticks
                        }
                    }
                },
                plugins: {

                    title: {
                        display: false,
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false,
                        callbacks: {
                            label: (context) => {
                                var label = '';
                                if (context.dataset.label) {
                                    label = context.dataset.label + ': ';
                                }
                                // Conditionally format values with or without unit
                                var value = context.parsed.y;
                                var decimalPlaces = context.dataset.decimalPlaces;
                                label += value.toFixed(decimalPlaces).replace(/\d(?=(\d{3})+\.)/g, '$&,');

                                return label;
                            },
                            title: function (tooltipItems) {
                                return moment(tooltipItems[0].label).format('DD MMM YYYY'); // Format date as 'DD MMM YYYY'
                            }
                        }
                    }
                }
            }
        });
        function populateTable(node) {
            if (groupedBenchmarks && groupedBenchmarks[node]) {
                const benchmarks = groupedBenchmarks[node];
                const tableBody = document.querySelector('#hostTableBody'); // Ensure this matches the correct selector
                let tableRows = ''; // Accumulate rows as a string

                benchmarks.forEach((benchmark, index) => {
                    const isEvenRow = (parseInt(index, 10) + 1) % 2 === 0;
                    const rowClass = isEvenRow ? 'bg-gray-50' : 'bg-gray-25';
                    // Build the entire row using template literals
                    tableRows += `
                    <tr class="${rowClass}">
                        <td class="border px-4 py-2" data-timestamp="${benchmark.timestamp}">${getLocalizedTime(benchmark.timestamp)}</td>
                        <td class="border px-4 py-2">${benchmark.node}</td>
                        <td class="border px-4 py-2">${(benchmark.uploadSpeed / 1000000).toFixed(2)} MB/s</td>
                        <td class="border px-4 py-2">${(benchmark.downloadSpeed / 1000000).toFixed(2)} MB/s</td>
                        <td class="border px-4 py-2">${(benchmark.ttfb / 1000000)} ms</td>
                        <td class="border px-4 py-2">${benchmark.success ? 'OK' : benchmark['error']}</td>
                    </tr>`;
                });

                // Insert all rows at once into the table body
                tableBody.innerHTML = tableRows;
            } else {
                const tableBody = document.querySelector('#hostTableBody'); // Ensure this matches the correct selector
                tableBody.innerHTML = `
                <tr>
                    <td colspan="6" class="text-center">No benchmarks found for this host.</td>
                </tr>
            `;
            }
        }

        // Example: Simulate data loading (call this function when data is loaded)
        window.onload = function () {

            document.getElementById('nodeSelect').value = 'global';
            populateTable("global");  // Call populateTable once `groupedBenchmarks` is available
        };
    </script>
</body>

</html>