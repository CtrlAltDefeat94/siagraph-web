<?php
/* Todo; 
- add current price
- 

*/
require_once 'bootstrap.php';
include_once 'include/graph.php';
require_once 'include/layout.php';
$graphConfigs = require 'include/graph_configs.php';

use Siagraph\Utils\Cache;
use Siagraph\Utils\Formatter;
use Siagraph\Utils\ApiClient;

$currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur';
$recentstats = Cache::getCache(Cache::RECENT_STATS_KEY);
if ($recentstats) {
    $recentstats = json_decode($recentstats, true);
}

// Fetch monthly metrics to calculate yearly inflation
$metricsData = ApiClient::fetchJson('/api/v1/monthly/metrics');
$dataError = !is_array($metricsData);
if ($dataError) {
    $metricsData = [];
}
$yearData = [];
foreach ($metricsData as $row) {
    $year = substr($row['date'], 0, 4);
    if (!isset($yearData[$year])) {
        $yearData[$year] = ['start' => $row['circulating_supply'], 'end' => $row['circulating_supply']];
    } else {
        $yearData[$year]['end'] = $row['circulating_supply'];
    }
}
$inflationRates = [];
foreach ($yearData as $y => $vals) {
    $start = $vals['start'];
    $end = $vals['end'];
    $inflationRates[$y] = $start > 0 ? (($end - $start) / $start) * 100 : 0;
}
?>
<?php render_header("SiaGraph - Tokenomics"); ?>
    <?php if ($dataError): ?>
        <p class="text-center text-muted">Tokenomics data unavailable.</p>
    <?php endif; ?>
    <!-- Main Content Section -->
    <section id="main-content" class="sg-container">
        <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-currency-bitcoin me-2"></i>Tokenomics</h1>

        <section id="graph-section" class="card mt-4">
            <h2 class="card__heading">Tokenomics: Inflation</h2>
            <div class="card__content">
            <div class="text-center text-light my-2">
                <i class="bi bi-currency-bitcoin me-1"></i>
                <span>Price:</span>
                <span class="fw-bold">
                    <?php echo strtoupper($currencyCookie) . ' ' . (!empty($recentstats) ? $recentstats['actual']['coin_price'][$currencyCookie] : 0); ?>
                </span>
            </div>
            <section class="graph-container">
                <!-- Include the Chart.js graph using an iframe -->
                <?php include $_SERVER['DOCUMENT_ROOT'] . "/graphs/CoinGrowthGraph.php"; ?>
                <!-- Add any additional content related to the Network graph -->
            </section>
            </div>
        </section>

        <section id="marketcap-section" class="card mt-4">
            <h2 class="card__heading">Tokenomics: Coin Market Cap</h2>
            <div class="card__content">
            <section class="graph-container">
                <?php
                renderGraph(
                    $canvasid = "marketcap",
                    [
                        array_merge(
                            $graphConfigs['market_cap'],
                            ['unit' => strtoupper($currencyCookie)]
                        )
                    ],
                    $dateKey = 'date',
                    $jsonUrl = '/api/v1/daily/metrics',
                    $jsonData = null,
                    $charttype = 'line',
                    $interval = 'week',
                    $rangeslider = true,
                    $displaylegend = 'true',
                    $defaultrangeinmonths = 12,
                    $displayYAxis = 'false',
                    $unitType = $currencyCookie
                );
                ?>
            </section>
            </div>
        </section>

        <section id="inflation-table" class="card mt-4">
            <h2 class="card__heading">Tokenomics: Yearly Inflation Rate</h2>
            <div class="card__content">
            <div class="table-responsive">
                <table class="table table-dark table-clean text-white w-full border-collapse border border-gray-300">
                    <thead class="bg-gray-800">
                        <tr><th class="px-4 py-2 border border-gray-300">Year</th><th class="px-4 py-2 border border-gray-300">Inflation</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($inflationRates as $year => $rate){ ?>
                        <tr>
                            <td class="px-4 py-2 border border-gray-300 text-center"><?php echo $year; ?></td>
                            <td class="px-4 py-2 border border-gray-300 text-right"><?php echo round($rate,2); ?>%</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            </div>
        </section>

        <!-- Footer Section -->
        <?php render_footer(); ?>
