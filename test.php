<?php
include_once 'include/graph.php';
include_once 'include/redis.php';
$graphConfigs = require 'include/graph_configs.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiaGraph - Daily Metrics</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@latest/dist/tailwind.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
    <script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>
    <link rel="icon" href="img/favicon.ico" type="image/png">
</head>
<body>
<?php include 'include/header.html'; ?>
<section id="main-content" class="container mt-4 pb-5 max-w-screen-xl">
    <div class="row align-items-start mt-4">
<?php
$metrics = [
    'block_height' => ['label' => 'Block Height', 'unit' => 'count'],
    'utilized_storage' => ['label' => 'Utilized Storage', 'unit' => 'bytes'],
    'total_storage' => ['label' => 'Total Storage', 'unit' => 'bytes'],
    'active_hosts' => ['label' => 'Active Hosts', 'unit' => 'count'],
    'total_hosts' => ['label' => 'Total Hosts', 'unit' => 'count'],
    'circulating_supply' => ['label' => 'Circulating Supply', 'unit' => 'SC'],
    'active_contracts' => ['label' => 'Active Contracts', 'unit' => 'count'],
    'total_successful_contracts' => ['label' => 'Total Successful Contracts', 'unit' => 'count'],
    'total_failed_contracts' => ['label' => 'Total Failed Contracts', 'unit' => 'count'],
    'total_renewed_contracts' => ['label' => 'Total Renewed Contracts', 'unit' => 'count'],
    'total_burned_funds' => ['label' => 'Total Burned Funds', 'unit' => 'SC'],
    'renter_collateral_locked' => ['label' => 'Renter Balance Locked', 'unit' => 'SC'],
    'host_collateral_locked' => ['label' => 'Host Collateral Locked', 'unit' => 'SC'],
    'contract_filesize_total' => ['label' => 'Contract Filesize Total', 'unit' => 'bytes'],
    'blocks_mined' => ['label' => 'Blocks Mined', 'unit' => 'count'],
    'contracts_formed' => ['label' => 'Contracts Formed', 'unit' => 'count'],
    'renewed_contracts' => ['label' => 'Renewed Contracts', 'unit' => 'count'],
    'failed_contracts' => ['label' => 'Failed Contracts', 'unit' => 'count'],
    'successful_contracts' => ['label' => 'Successful Contracts', 'unit' => 'count'],
    'unique_contract_renters' => ['label' => 'Unique Contract Renters', 'unit' => 'count'],
    'contract_revenue' => ['label' => 'Contract Revenue', 'unit' => 'SC'],
    'siafund_tax_revenue' => ['label' => 'Siafunds Tax Revenue', 'unit' => 'SC'],
    'siacoin_volume' => ['label' => 'Siacoin Volume', 'unit' => 'SC'],
    'siafund_volume' => ['label' => 'Siafunds Volume', 'unit' => 'SC'],
    'total_fees' => ['label' => 'Total Fees', 'unit' => 'SC'],
    'unique_transaction_addresses' => ['label' => 'Unique Transaction Addresses', 'unit' => 'count'],
    'na_burned_funds' => ['label' => 'Burned Funds (Aggregates)', 'unit' => 'SC'],
    'avg_difficulty' => ['label' => 'Average Difficulty', 'unit' => 'count'],
];
$colorBg = 'rgba(75, 192, 192, 0.2)';
$colorBorder = 'rgba(75, 192, 192, 1)';
foreach ($metrics as $key => $info) {
    echo "<div class=\"col-md-6 mt-4\">";
    echo "<section class=\"bg-light p-3 rounded-3\">";
    echo '<h2 class="card__heading">' . htmlspecialchars($info['label']) . '</h2>';
    echo "<section class=\"graph-container\">";
    $dataset = $graphConfigs[$key] ?? [
        'label' => $info['label'],
        'key' => $key,
        'backgroundColor' => $colorBg,
        'borderColor' => $colorBorder,
        'decimalPlaces' => 0,
        'startAtZero' => false
    ];
    renderGraph(
        $canvasid = $key,
        $datasets = [ $dataset ],
        $dateKey = 'date',
        $jsonUrl = '/api/v1/daily/metrics',
        $jsonData = null,
        $charttype = 'line',
        $interval = 'week',
        $rangeslider = true,
        $displaylegend = 'false',
        $defaultrangeinmonths = 12,
        $displayYAxis = 'false',
        $unitType = $info['unit']
    );
    echo "</section>";
    echo "</section>";
    echo "</div>";
}
?>
    </div>
</section>
<?php include 'include/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@3"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1"></script>
<link href="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.js"></script>
</body>
</html>
