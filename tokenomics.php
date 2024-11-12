<?php
/* Todo; 
- add current price
- 

*/

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
                Coin growth</span>
            </h2>
            <section class="graph-container">
                <!-- Include the Chart.js graph using an iframe -->
                <?php include $_SERVER['DOCUMENT_ROOT'] . "/graphs/CoinGrowthGraph.php"; ?>
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