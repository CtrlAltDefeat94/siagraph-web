<?php
include_once 'include/graph.php';
include_once "include/redis.php";



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
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <link rel="icon" href="img/favicon.ico" type="image/png">
</head>

<body>
    <!-- Header Section -->
    <?php include "include/header.html" ?>
    <!-- Main Content Section -->
    <section id="main-content" class="container mt-4 pb-5 max-w-screen-xl">
        <!-- Row for new section and additional info section -->
        <div class="row align-items-start mt-4">
            <div class="col-md-6">
                <section id="graph-section" class="bg-light p-3 rounded-3 ">
                    <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">
                        Storage size
                    </h2>
                    <section class="graph-container">
                        <?php

                        // Define datasets with their keys and labels
                        
                        renderGraph(
                            $canvasid = "networkstorage",
                            $datasets = [
                                [
                                    'label' => 'Utilized Storage',
                                    'key' => 'total_storage', // Modify based on your data
                                    'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                                    'borderColor' => 'rgba(75, 192, 192, 1)',
                                    'transform' => "return entry['utilized_storage'];",
                                    'unit' => 'PB',
                                    'unitDivisor' => 1e15,
                                    'decimalPlaces' => 2,
                                    'startAtZero' => true
                                ],
                                [
                                    'label' => 'Total Storage',
                                    'key' => 'total_storage',
                                    'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                                    'borderColor' => 'rgba(255, 99, 132, 1)',
                                    'transform' => "return entry['total_storage'];",
                                    'unit' => 'PB',
                                    'unitDivisor' => 1e15,
                                    'decimalPlaces' => 2,
                                    'startAtZero' => true
                                ]
                            ],
                            $dateKey = "date",
                            $jsonUrl = "/api/v1/daily/growth", // JSON URL
                            $jsonData = null,#getCache($metricsKey),             // JSON key for date
                            $charttype = 'line',
                            $interval = 'week',
                            $rangeslider = true,
                            $displaylegend = "true",
                            $defaultrangeinmonths = 12,
                            $displayYAxis = "false",
                            $unitType = 'bytes'
                        );
                        ?>

                        <!-- Add any additional content related to the Network graph -->
                    </section>
                </section>
            </div>

            <div class="col-md-6">
                <section id="graph2-section" class="bg-light p-3 rounded-3">
                    <!-- Graph Section for Network -->
                    <section class="graph-container">
                        <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">
                            Hosts
                        </h2>
                        <section class="graph2-container">
                            <?php

                            // Define datasets with their keys and labels
                            
                            renderGraph(
                                $canvasid = "hostcount",
                                $datasets = [
                                    [
                                        'label' => 'Active Hosts',
                                        'key' => 'active_hosts', // Modify based on your data
                                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                                        'borderColor' => 'rgba(75, 192, 192, 1)',
                                        'decimalPlaces' => 0, // No decimal places
                                        'startAtZero' => false // Set to true to start y-axis at zero
                                    ],
                                    [
                                        'label' => 'Total Hosts',
                                        'key' => 'total_hosts',
                                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                                        'borderColor' => 'rgba(255, 99, 132, 1)',
                                        'decimalPlaces' => 0, // No decimal places
                                        'startAtZero' => false,
                                        "hidden" => true
                                    ]
                                ],
                                $dateKey = "date",
                                $jsonUrl = "/api/v1/daily/growth?start=2024-02-18", // JSON URL
                                $jsonData = null,//#getCache($metricsKey),             // JSON key for date
                                $charttype = 'line',
                                $interval = 'week',
                                $rangeslider = true,
                                $displaylegend = "true",
                                $defaultrangeinmonths = 12,
                                $displayYAxis = "false",
                                $unitType = null
                            );
                            ?>
                        </section>
                    </section>
            </div>
        </div>
    </section>

    <!-- Footer Section -->
    <?php include "include/footer.php" ?>
</body>
<!-- Import Moment.js library -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<!-- Import Chart.js 3 library with Moment adapter -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@3"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1"></script>
<!-- Include noUiSlider library -->
<link href="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.js"></script>
<script>
    // Get the span elements by their IDs
    let startDateElement = document.getElementById('startDate');
    let endDateElement = document.getElementById('endDate');

    // Set the text content of the span elements to the dates
    startDateElement.textContent = startDate;
    endDateElement.textContent = endDate;
</script>

</html>