<?php
                include_once 'growth_graphs/graph.php';
// Assuming hostdata.json is in the same directory as this PHP file
$json_data = file_get_contents('../rawdata/hostdata.json');
$data = json_decode($json_data, true);

// Check if data was successfully loaded from JSON
if ($data !== null) {
    // Extract relevant data
    $avg_storage_price = $data['average_prices']['avg_storage_price'];
} else {
    // Handle error loading JSON data
    $avg_storage_price = $avg_download_price = $avg_upload_price = $avg_max_collateral = 0;
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
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
</head>

<body>
    <!-- Header Section -->
    <?php include "include/header.html" ?>
    <!-- Main Content Section -->
    <section id="main-content" class="container mt-4 pb-5">

        <section id="graph-section" class="bg-light p-3 rounded-3 mt-4">
            <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">
                Network size from <span id="startDate"></span> until <span id="endDate"></span>
            </h2>
            <section class="graph-container">
                <!-- Include the Chart.js graph using an iframe -->
                <?php //include $_SERVER['DOCUMENT_ROOT'] . "/growth_graphs/maxhoststorage.php"; ?>
                <?php

                // Define datasets with their keys and labels
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
                ];

                // Call the function with specific parameters
                renderGraph(
                    "/api/v1/metrics_monthly", // JSON URL
                    "date",               // JSON key for date
                    $datasets,             // Dataset configuration
                    "maxhoststorage",
                    $charttype = "bar",
                    $interval = 'month',
                    $rangeslider=false

                ); ?>
                <!-- Add any additional content related to the Network graph -->
            </section>
        </section>
        <!-- Footer Section -->
        <?php include "include/footer.html" ?>
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