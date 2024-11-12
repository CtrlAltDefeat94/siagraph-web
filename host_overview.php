<?php

include_once 'include/graph.php';
// Assuming hostdata.json is in the same directory as this PHP file
#$json_data = file_get_contents('../rawdata/hostdata.json');
$data = null;
json_decode($json_data, true);

// Check if data was successfully loaded from JSON
if ($data !== null) {
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

    $stats1a_value = 0;
    $stats1b_value = 0;
    $stats2a_value = 0;
    $stats2b_value = 0;
    $stats3a_value = 0;
    $stats3b_value = 0;
    $stats4a_value = 0;
    $stats4b_value = 0;
    $stats5a_value = 0;
    $stats5b_value = 0;
    $stats6a_value = 0;
    $stats6b_value = 0;
} else {
    // Handle error loading JSON data
    $avg_storage_price = $avg_download_price = $avg_upload_price = $avg_max_collateral = 0;
    // Provide default values or handle the error in a way that suits your application
}

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

        <!-- Graph Section 2 -->
        <div class="w-full break-inside-avoid">
            <section id="graph2-section" class="bg-light p-3 rounded-3 mt-4">
                <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Host Issues</h2>
                <section class="graph-container">
                    <!-- Include the Chart.js graph using an iframe -->
                    <?php //include $_SERVER['DOCUMENT_ROOT'] . "/graphs/HostErrors.php"; ?>
                    <!-- Add any additional content related to the Network graph -->
                </section>
            </section>
        </div>

        <!-- Graph Section 1 -->
        <div class="w-full break-inside-avoid">
            <section id="graph3-section" class="bg-light p-3 rounded-3 mt-4">
                <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Network Size per Country
                </h2>
                <section class="graph-container">
                    <?php //include $_SERVER['DOCUMENT_ROOT'] . "/graphs/CountryHighscore.php"; ?>
                    <!-- Add any additional content related to the Network graph -->
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
                                'startAtZero' => true
                            ]
                        ],
                        $dateKey = "date",
                        $jsonUrl = "/api/v1/metrics_monthly", // JSON URL
                        $jsonData = null,
                        $charttype = 'bar',

                        $interval = 'month',
                        $rangeslider = true,
                        $displaylegend = "false",
                        $defaultrangeinmonths = 6,
                        $displayYAxis = "false",
                        $unitType = 'bytes'
                    );
                    ?>
                </section>
            </section>
        </div>

        <!-- Graph Section 3 -->
        <div class="w-full break-inside-avoid">
            <section id="graph-section" class="bg-light p-3 rounded-3 mt-4">
                <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Host Versions</h2>
                <section class="graph-container">
                    <?php


                    renderGraph(
                        $canvasid = "hostversions",
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
                        $jsonUrl = "/api/v1/hosts?limit=5000", // JSON URL
                        $jsonData = null,
                        $charttype = 'pie',

                        $interval = 'month',
                        $rangeslider = false,
                        $displaylegend = "false",
                        $defaultrangeinmonths = 6,
                        $displayYAxis = "false",
                        $unitType = 'bytes',
                        $jsonKey = 'versions'
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



</html>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1"></script>
<link href="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.js"></script>