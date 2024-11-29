<?php

include_once 'include/graph.php';
include_once "include/redis.php";
include_once "include/utils.php";

// Assuming hostdata.json is in the same directory as this PHP file
#$json_data = file_get_contents('../rawdata/hostdata.json');
$data = null;
$json_data = getCache(md5("hosts?limit=0"));
if (empty($json_data)) {
    // Retrieve JSON data from the API
    $apiUrl = "https://alpha.siagraph.info/api/v1/hosts?limit=0";

    // Use file_get_contents to fetch the data
    $json_data = file_get_contents($apiUrl);
}
$json_data = json_decode($json_data, true);
$versions = [];
$countries = [];
$storage_prices = [];
$upload_prices = [];
$download_prices = [];
$fullHosts=0;
foreach ($json_data['hosts'] as $host) {
    if (!isset($versions[$host['version']])) {
        $versions[$host['version']] = 1;
    } else { 
        $versions[$host['version']]++;
    }

    // Initialize country data if not already set
    if (!isset($countries[$host['country']])) {
        $countries[$host['country']] = [
            'host_count' => 0,
            'used_storage' => 0,
            'total_storage' => 0
        ];
    }
    if ($host['total_storage']-$host['used_storage']==0) {
        $fullHosts++;
    }
    // Update stats for the country
    $countries[$host['country']]['host_count']++;
    $countries[$host['country']]['used_storage'] += (int) $host['used_storage'];
    $countries[$host['country']]['total_storage'] += (int) $host['total_storage'];
    if ($host['country'] >0){
        $storage_prices[]=$host['storage_price']/ pow(10, 12)*4320;
        $upload_prices[]=$host['upload_price']/ pow(10, 12);
        $download_prices[]=$host['download_price']/ pow(10, 12);
    }

}
#$versions = json_encode($versions, true);
// Check if data was successfully loaded from JSON

    // Extract relevant data
    /*$avg_storage_price = $data['average_prices']['avg_storage_price'];
    $avg_download_price = $data['average_prices']['avg_download_price'];
    $avg_upload_price = $data['average_prices']['avg_upload_price'];
    $avg_max_collateral = $data['average_prices']['avg_max_collateral'];

    // Example data for stats1-6 (replace this with your actual data retrieval logic)
    $stats1a_value = $avg_storage_price;
    $stats1b_value = 0;
    $stats2a_value = $avg_upload_price;
    $stats2b_value = 0;
    $stats3a_value = $avg_download_price;
    $stats3b_value = 0;
    $stats4a_value = $data['hosts_count'];
    $stats4b_value = 0;
    $stats5a_value = $data['filled_count'];
    $stats5b_value = 0;
    $stats6a_value = $data['error_count'];
    $stats6b_value = 0;
*/

    $stats1a_value = round(calculate_average_excluding_outliers($storage_prices),1);
    $stats1b_value = 0;
    $stats2a_value = round(calculate_average_excluding_outliers($upload_prices),1);
    $stats2b_value = 0;
    $stats3a_value = round(calculate_average_excluding_outliers($download_prices),1);
    $stats3b_value = 0;
    $stats4a_value = count($json_data['hosts']);
    $stats4b_value = 0;
    $stats5a_value = $fullHosts;
    $stats5b_value = 0;
    $stats6a_value = "N/A";
    $stats6b_value = 0;

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiaGraph</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@latest/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="script.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>


</head>

<body>

    <!-- Header Section -->
    <?php include_once "include/header.html" ?>

    <!-- Main Content Section -->
    <section id="main-content" class="container mt-4 pb-5 masonry-container">

        <!-- Updated Additional Information Section -->
        <div class="w-full break-inside-avoid">
            <section id="additional-info" class="bg-light p-3 rounded-3">
                <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Today's Stats</h2>
                <!-- Used Storage -->
                <div class="row mt-4">
                    <!-- Statistics Section -->
                    <div class="col-md-12">
                        <div class="row">
                            <!-- Average Storage Price -->
                            <div class="col-md-4">
                                <div class="p-2">
                                    <span class="fs-6">Average Storage Price</span>
                                    <br><span id="stats1a"
                                        class="glanceNumber fs-4"><?php echo $stats1a_value . ' SC'; ?></span>
                                    <span id="stats1b" class="fs-6"> (<?php echo $stats1b_value; ?>)</span>
                                </div>
                            </div>
                            <!-- Average Upload Price -->
                            <div class="col-md-4">
                                <div class="p-2">
                                    <span class="fs-6">Average Upload Price</span>
                                    <br><span id="stats2a"
                                        class="glanceNumber fs-4"><?php echo $stats2a_value . ' SC'; ?></span>
                                    <span id="stats2b" class="fs-6">(<?php echo $stats2b_value; ?>)</span>
                                </div>
                            </div>
                            <!-- Average Download Price -->
                            <div class="col-md-4">
                                <div class="p-2">
                                    <span class="fs-6">Average Download Price</span>
                                    <br><span id="stats3a"
                                        class="glanceNumber fs-4"><?php echo $stats3a_value . ' SC'; ?></span>
                                    <span id="stats3b" class="fs-6">(<?php echo $stats3b_value; ?>)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 mt-4">
                        <div class="row">
                            <!-- Average Max Collateral -->
                            <div class="col-md-4">
                                <div class="p-2">
                                    <span class="fs-6">Active Hosts</span>
                                    <br><span id="stats4a"
                                        class="glanceNumber fs-4"><?php echo $stats4a_value . ''; ?></span>
                                </div>
                            </div>
                            <!-- Active hosts -->
                            <div class="col-md-4">
                                <div class="p-2">
                                    <span class="fs-6">Hosts Fully Utilized</span>
                                    <br><span id="stats5a"
                                        class="glanceNumber fs-4"><?php echo $stats5a_value; ?></span>
                                </div>
                            </div>
                            <!-- Hosts With Issues -->
                            <div class="col-md-4">
                                <div class="p-2">
                                    <span class="fs-6">Hosts with Issues</span>
                                    <br><span id="stats6a"
                                        class="glanceNumber fs-4"><?php echo $stats6a_value; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <!-- Graph Section 2 
        <div class="w-full break-inside-avoid">
            <section id="graph2-section" class="bg-light p-3 rounded-3 mt-4">
                <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Host Issues</h2>
                <section class="graph-container">
                    <?php //include $_SERVER['DOCUMENT_ROOT'] . "/graphs/HostErrors.php"; ?>
                </section>
            </section>
        </div>-->

        <!-- Graph Section 1 -->
        <div class="w-full break-inside-avoid">
            <section id="graph3-section" class="bg-light p-3 rounded-3 mt-4">
                <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Network Size per Country
                </h2>
                <section class="graph-container">
                    <div class="container mx-auto">
                        <?php
                        // Example data
                        

                        // Sort by used_storage descending
                        uasort($countries, function ($a, $b) {
                            return $b['used_storage'] <=> $a['used_storage'];
                        });

                        // Generate the table
                        echo '<table class="table-auto w-full border-collapse border border-gray-300">';
                        echo '<thead class="bg-gray-200">';
                        echo '<tr>';
                        echo '<th class="px-4 py-2 border border-gray-300">Country</th>';
                        echo '<th class="px-4 py-2 border border-gray-300">Hosts</th>';
                        echo '<th class="px-4 py-2 border border-gray-300">Used Storage</th>';
                        echo '<th class="px-4 py-2 border border-gray-300">Total Storage</th>';
                        echo '</tr>';
                        echo '</thead>';
                        echo '<tbody>';

                        // Alternate row colors
                        $index = 0;
                        foreach ($countries as $country => $data) {
                            $rowClass = $index % 2 === 0 ? 'bg-gray-100' : 'bg-gray-200';
                            echo "<tr class=\"$rowClass\">";
                            echo "<td class=\"px-4 py-2 border border-gray-300 text-center\">$country</td>";
                            echo "<td class=\"px-4 py-2 border border-gray-300 text-right\">{$data['host_count']}</td>";
                            echo "<td class=\"px-4 py-2 border border-gray-300 text-right\">" . formatBytes($data['used_storage']) . "</td>";
                            echo "<td class=\"px-4 py-2 border border-gray-300 text-right\">" . formatBytes($data['total_storage']) . "</td>";
                            echo '</tr>';
                            $index++;
                        }

                        echo '</tbody>';
                        echo '</table>';
                        ?>
                    </div>
                </section>
            </section>
        </div>
        <!-- Graph Section 1 -->
        <div class="w-full break-inside-avoid">
            <section id="graph4-section" class="bg-light p-3 rounded-3 mt-4">
                <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Largest host
                </h2>
                <section class="graph4-container">
                    <?php


                    renderGraph(
                        $canvasid = "maxhoststorage",
                        $datasets = [
                            [
                                'label' => 'Used Storage',
                                'key' => 'max_used_storage',
                                'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                                'borderColor' => 'rgba(255, 99, 132, 1)',
                                'transform' => "return entry['max_used_storage'];",
                                'unit' => 'TB',
                                'unitDivisor' => 1e12,
                                'decimalPlaces' => 2,
                                'startAtZero' => false
                            ]
                        ],
                        $dateKey = "date",
                        $jsonUrl = "/api/v1/metrics_monthly", // JSON URL
                        $jsonData = null,
                        $charttype = 'bar',

                        $interval = 'month',
                        $rangeslider = false,
                        $displaylegend = false,
                        $defaultrangeinmonths = 30,
                        $displayYAxis = "false",
                        $unitType = 'bytes'
                    );
                    ?>
                </section>
            </section>
        </div>
        <div class="w-full flex flex-wrap justify-between">
            <!-- Graph Section 1 -->
            <section id="graph-section-1" class="w-[24%] bg-light p-3 rounded-md mt-4">
                <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-t-md">Host Versions</h2>
                <section class="graph-container">
                    <?php 
                    renderGraph(
                        $canvasid = "hostversions-1",
                        $datasets = [
                            [
                                'label' => '1.6.0',
                                'key' => '1.6.0',
                                'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                                'borderColor' => 'rgba(255, 99, 132, 1)',
                                'transform' => "return entry['1.6.0'];",
                            ]
                        ],
                        $dateKey = "date",
                        $jsonUrl = "",
                        $jsonData = $versions,
                        $charttype = 'pie',
                        $interval = 'month',
                        $rangeslider = false,
                        $displaylegend = "true",
                        $defaultrangeinmonths = 6,
                        $displayYAxis = "false",
                        $unitType = null,
                        $jsonKey = null,
                        $height = 170
                    );
                    ?>
                </section>
            </section>

            <!-- Graph Section 2 -->
            <section id="graph-section-2" class="w-[24%] bg-light p-3 rounded-md mt-4">
                <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-t-md">Host Errors</h2>
                <section class="graph-container">
                    <?php 
                        echo "N/A";
                    renderGraph(
                        $canvasid = "hosterrors",
                        $datasets = [
                            [
                                'label' => '1.6.0',
                                'key' => '1.6.0',
                                'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                                'borderColor' => 'rgba(255, 99, 132, 1)',
                                'transform' => "return entry['1.6.0'];",
                            ]
                        ],
                        $dateKey = "date",
                        $jsonUrl = "",
                        $jsonData = $versions,
                        $charttype = null,
                        $interval = 'month',
                        $rangeslider = false,
                        $displaylegend = "true",
                        $defaultrangeinmonths = 6,
                        $displayYAxis = "false",
                        $unitType = null,
                        $jsonKey = null,
                        $height = 170
                    ); 
                    ?>
                </section>
            </section>
        </div>
    </section>


    <!-- Footer Section -->
    <?php include "include/footer.html" ?>


    <style>
        /* Custom CSS */
        .masonry-container {
            column-count: 1;
            /* Default to 1 column on small screens */
        }

        /* Apply column-count: 2 for md screens and above */
        @media (min-width: 768px) {
            .masonry-container {
                column-count: 2;
            }
        }

        .w-full {
            break-inside: avoid;
        }
    </style>
</body>

<script>

    async function fetchAndProcessHosts() {
        try {
            // Fetch data from the API
            const response = await fetch('/api/v1/hosts');
            if (!response.ok) {
                throw new Error(`HTTP error! Status: ${response.status}`);
            }

            const data = await response.json();

            // Check if hosts exist in the response
            if (!data.hosts || !Array.isArray(data.hosts)) {
                console.error("No hosts data found in response");
                return {};
            }

            // Create a dictionary to store sums of amounts by version
            const versionSums = {};

            // Iterate through the hosts array
            data.hosts.forEach(host => {
                const version = host.version; // Replace with the correct field for version


                // Add the amount to the sum for this version
                if (!versionSums[version]) {
                    versionSums[version] = 0;
                }
                versionSums[version] += 1;
            });

            console.log(versionSums);
            return versionSums;

        } catch (error) {
            console.error("Error fetching or processing hosts:", error);
            return {};
        }
    }




</script>

</html>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1"></script>
<link href="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.js"></script>