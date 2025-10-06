<?php

require_once 'bootstrap.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';
require_once 'include/layout.php';
use Siagraph\Services\HostOverviewService;
use Siagraph\Utils\Formatter;
use Siagraph\Utils\ApiClient;
$currencyCookie = isset($_COOKIE['currency']) ? strtolower($_COOKIE['currency']) : 'eur';

// Assuming hostdata.json is in the same directory as this PHP file
//$json_data = file_get_contents('../rawdata/hostdata.json');
# Fetch hosts
$json_data = ApiClient::fetchJson('/api/v1/hosts?limit=0');
$dataError = !is_array($json_data) || !isset($json_data['hosts']) || !is_array($json_data['hosts']);
if ($dataError) {
    $json_data = ['hosts' => []];
}

// Summaries
$summary = HostOverviewService::summarize($json_data['hosts']);
$locations = $summary['locations'];
$versions = $summary['versions'];
$countries = $summary['countries'];
$storage_prices = $summary['storage_prices'];
$upload_prices = $summary['upload_prices'];
$download_prices = $summary['download_prices'];
$fullHosts = $summary['stats']['full_hosts'];
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

$stats1a_value = $summary['stats']['avg_storage_price'];
$stats1b_value = 0;
$stats2a_value = $summary['stats']['avg_upload_price'];
$stats2b_value = 0;
$stats3a_value = $summary['stats']['avg_download_price'];
$stats3b_value = 0;
$stats4a_value = $summary['stats']['host_count'];
$stats4b_value = 0;
$stats5a_value = $summary['stats']['full_hosts'];
$stats5b_value = 0;
$stats6a_value = "N/A";
$stats6b_value = 0;

?>
<?php render_header('SiaGraph - Host Overview', 'SiaGraph - Host Overview', [
    '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />'
]); ?>

    <?php if ($dataError): ?>
        <p class="text-center text-muted">Host data unavailable.</p>
    <?php endif; ?>
    <!-- Main Content Section -->
    <section id="main-content" class="sg-container sg-container--narrow">
        <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-diagram-3 me-2"></i>Host Overview</h1>
        

        <!-- Today's Stats -->
        <div class="sg-container__row">
            <div class="sg-container__row-content">
                <div class="sg-container__column">
                    <section id="additional-info" class="card">
                        <h2 class="card__heading">Today's Stats</h2>
                        <div class="card__content">
                <!-- Used Storage -->
                <div class="row mt-4">
                    <!-- Statistics Section -->
                    <div class="col-md-12">
                        <div class="row">
                            <!-- Average Storage Price -->
                            <div class="col-md-4 mb-3">
                                <div class="p-2">
                                    <span class="fs-6"><i class="bi bi-hdd-fill me-1"></i>Average Storage Price</span>
                                    <br><span id="stats1a"
                                        class="glanceNumber fs-4"><?php echo \Siagraph\Utils\Formatter::formatSiacoins($stats1a_value); ?></span>
                                    <span id="stats1b" class="fs-6"> (<?php echo $stats1b_value; ?>)</span>
                                </div>
                            </div>
                            <!-- Average Upload Price -->
                            <div class="col-md-4 mb-3">
                                <div class="p-2">
                                    <span class="fs-6"><i class="bi bi-upload me-1"></i>Average Upload Price</span>
                                    <br><span id="stats2a"
                                        class="glanceNumber fs-4"><?php echo \Siagraph\Utils\Formatter::formatSiacoins($stats2a_value); ?></span>
                                    <span id="stats2b" class="fs-6">(<?php echo $stats2b_value; ?>)</span>
                                </div>
                            </div>
                            <!-- Average Download Price -->
                            <div class="col-md-4 mb-3">
                                <div class="p-2">
                                    <span class="fs-6"><i class="bi bi-download me-1"></i>Average Download Price</span>
                                    <br><span id="stats3a"
                                        class="glanceNumber fs-4"><?php echo \Siagraph\Utils\Formatter::formatSiacoins($stats3a_value); ?></span>
                                    <span id="stats3b" class="fs-6">(<?php echo $stats3b_value; ?>)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12 mt-4">
                        <div class="row">
                            <!-- Average Max Collateral -->
                            <div class="col-md-4 mb-3">
                                <div class="p-2">
                                    <span class="fs-6"><i class="bi bi-server me-1"></i>Active Hosts</span>
                                    <br><span id="stats4a"
                                        class="glanceNumber fs-4"><?php echo $stats4a_value . ''; ?></span>
                                </div>
                            </div>
                            <!-- Active hosts -->
                            <div class="col-md-4 mb-3">
                                <div class="p-2">
                                    <span class="fs-6"><i class="bi bi-check-circle me-1"></i>Hosts Fully Utilized</span>
                                    <br><span id="stats5a"
                                        class="glanceNumber fs-4"><?php echo $stats5a_value; ?></span>
                                </div>
                            </div>
                            <!-- Hosts With Issues -->
                            <div class="col-md-4 mb-3">
                                <div class="p-2">
                                    <span class="fs-6"><i class="bi bi-exclamation-triangle me-1"></i>Hosts with Issues</span>
                                    <br><span id="stats6a"
                                        class="glanceNumber fs-4"><?php echo $stats6a_value; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                        </div>
                    </section>
                </div>
            </div>
        </div>

        <!-- Graph Section 2
        <div class="w-full break-inside-avoid">
            <section id="graph2-section" class="card">
                <h2 class="card__heading">Host Issues</h2>
                <div class="card__content">
                    <section class="graph-container">
                        <?php //include $_SERVER['DOCUMENT_ROOT'] . "/graphs/HostErrors.php"; ?>
                    </section>
                </div>
            </section>
        </div>-->

        <!-- Country table -->
        <div class="sg-container__row">
            <div class="sg-container__row-content">
                <div class="sg-container__column">
                    <section id="graph3-section" class="card">
                <h2 class="card__heading">Network Size per Country</h2>
                <div class="card__content">
                <section class="graph-container">
                    <div class="table-scroll">
                        <?php
                        // Example data
                        

                        include_once __DIR__ . '/include/components/country_table.php';
                        render_country_table($countries, 30);
                        ?>
                    </div>
                </section>
                </div>
                    </section>
                </div>
            </div>
        </div>
        <div class="sg-container__row">
            <div class="sg-container__row-content">
                <div class="sg-container__column">
                    <section id="graph-section-1" class="card">
                        <h2 class="card__heading">Host Versions</h2>
                        <div class="card__content">
                            <section class="graph-container">
                                <?php
                                renderGraph(
                                    $canvasid = "hostversions-1",
                                    $datasets = [
                                        $graphConfigs['versions']
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
                        </div>
                    </section>
                </div>
            </div>
        </div>
    </section>


    <div id="host-overview-data"
         data-hosts='<?= htmlspecialchars(json_encode($json_data['hosts']), ENT_QUOTES, "UTF-8") ?>'
         data-locations='<?= htmlspecialchars(json_encode($locations), ENT_QUOTES, "UTF-8") ?>'></div>
<?php render_footer(['https://unpkg.com/leaflet@1.9.4/dist/leaflet.js', 'js/host-overview.js']); ?>
