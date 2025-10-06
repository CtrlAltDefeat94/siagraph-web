<?php
require_once 'bootstrap.php';
include_once 'include/graph.php';
require_once 'include/layout.php';
$graphConfigs = require 'include/graph_configs.php';

use Siagraph\Utils\Cache;
use Siagraph\Utils\Formatter;
use Siagraph\Utils\Locale;

$recentstats = Cache::getCache(Cache::RECENT_STATS_KEY);
if (is_string($recentstats) && $recentstats !== '') {
    $decoded = json_decode($recentstats, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        $recentstats = $decoded;
    } else {
        $recentstats = null;
    }
}
// Fallback: if cache is missing or invalid, try fetching from API server-side
if (empty($recentstats)) {
    $apiUrl = rtrim($SETTINGS['siagraph_base_url'] ?? '', '/') . '/api/v1/daily/compare_metrics';
    if (!empty($apiUrl)) {
        $apiJson = @file_get_contents($apiUrl);
        if ($apiJson !== false) {
            $decoded = json_decode($apiJson, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $recentstats = $decoded;
                // Seed cache for subsequent loads
                Cache::setCache($apiJson, Cache::RECENT_STATS_KEY, 'hour');
            }
        }
    }
}


// Cache keys for network highlight data
$metricsHighlightKey = md5('metrics.php-noagg');
$aggregatesHighlightKey = md5('aggregates.php');

$cachedMetrics = json_decode(Cache::getCache($metricsHighlightKey) ?? '', true);
$cachedAggregates = json_decode(Cache::getCache($aggregatesHighlightKey) ?? '', true);

$latestMetrics = !empty($cachedMetrics) ? end($cachedMetrics) : null;
$latestAggregates = !empty($cachedAggregates) ? end($cachedAggregates) : null;

$explorerData = json_decode(Cache::getCache(Cache::EXPLORER_METRICS_KEY) ?? '', true);

$timeSince = 'Time since: 00:00:00';
$timeAverage = 'Recent average: 00:00:00';
$foundTimeText = 'Found at: 1970-01-01';
$nextBlock = 0;
if (!empty($explorerData)) {
    $found = new DateTime($explorerData['blockFoundTime']);
    $now = new DateTime('now');
    $diff = $now->getTimestamp() - $found->getTimestamp();
    $days = floor($diff / 86400);
    $hours = floor(($diff % 86400) / 3600);
    $minutes = floor(($diff % 3600) / 60);
    $seconds = $diff % 60;
    $timeSince = $days > 0 ?
        sprintf('Time since: %d days %02d:%02d:%02d', $days, $hours, $minutes, $seconds) :
        sprintf('Time since: %02d:%02d:%02d', $hours, $minutes, $seconds);

    $avgMinutes = floor($explorerData['averageFoundSeconds'] / 60);
    $avgSeconds = $explorerData['averageFoundSeconds'] % 60;
    $timeAverage = sprintf('Recent average: %d minutes %d seconds', $avgMinutes, $avgSeconds);
    $foundTimeText = 'Found at: ' . $found->format('Y-m-d\TH:i:s\Z');
    $nextBlock = $explorerData['blockHeight'] + 1;
}

$currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur';
$resolution = (isset($_GET['resolution']) && $_GET['resolution'] === 'monthly') ? 'monthly' : 'daily';
$aggEndpoint = '/api/v1/' . $resolution . '/aggregates';
$metricsEndpoint = '/api/v1/' . $resolution . '/metrics';
$growthEndpoint = '/api/v1/' . $resolution . '/growth';
$intervalDefault = $resolution === 'monthly' ? 'month' : 'week';

?>
<?php render_header('SiaGraph'); ?>
<!-- Main Content Section -->
<section id="main-content" class="sg-container">
    <!-- Row for new section and additional info section -->
    <div class="sg-container__row">
        <div class="sg-container__row-content">
            <!-- Additional Information Section -->
            <div class="sg-container__column sg-container__column--half">
                <section class="card">
                    <h2 class="card__heading">
                        Last 24h Change
                    </h2>
                    <!-- Used Storage -->
                    <div class="card__content">
                        <!-- Statistics Section -->
                        <div class="col-md-12">
                            <div class="row">
                                <!-- Used Storage -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">Utilized Storage</span>
                                        <br><span id="stats1a"
                                            class="glanceNumber fs-4"><?php echo !empty($recentstats) ? Formatter::formatBytes($recentstats['actual']['utilized_storage']) : 0; ?></span>
                                        <br><span id="stats1b" style="opacity: 0.3"
                                            class="fs-6">(<?php echo !empty($recentstats) ? Formatter::prependPlusIfNeeded(Formatter::formatBytes($recentstats['change']['utilized_storage'])) : 0; ?>)</span>
                                    </div>
                                </div>

                                <!-- Active Contracts -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">Active Contracts</span>
                                        <br><span id="stats2a"
                                            class="glanceNumber fs-4"><?php echo !empty($recentstats) ? $recentstats['actual']['active_contracts'] : 0; ?></span>
                                        <span id="stats2b" style="opacity: 0.3"
                                            class="fs-6">(<?php echo !empty($recentstats) ? Siagraph\Utils\Locale::signedDecimal($recentstats['change']['active_contracts'], 0) : 0; ?>)</span>
                                    </div>
                                </div>

                                <!-- 30-day Network Revenue -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">30-day Network Revenue</span>
                                        <br>
                                        <?php
                                        $revenueActual = !empty($recentstats) ? $recentstats['actual']['30_day_revenue'][$currencyCookie] : 0;
                                        $revenueChange = !empty($recentstats) ? $recentstats['change']['30_day_revenue'][$currencyCookie] : 0;
                                        if ($currencyCookie === 'sc') {
                                            $revenueActual /= 1e24;
                                            $revenueChange /= 1e24;
                                        }
                                        ?>
                                        <span id="stats3a"
                                            class="glanceNumber fs-4"><?php echo strtoupper($currencyCookie) . " " . Locale::decimal($revenueActual, ($currencyCookie==='sc'?2:2)); ?></span>
                                        <span id="stats3b" style="opacity: 0.3"
                                            class="fs-6">(<?php echo strtoupper($currencyCookie) . " " . Siagraph\Utils\Locale::signedDecimal($revenueChange,  ($currencyCookie==='sc'?2:2)); ?>)</span>
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
                                            class="glanceNumber fs-4"><?php echo !empty($recentstats) ? Formatter::formatBytes($recentstats['actual']['total_storage']) : 0; ?></span>
                                        <br><span id="stats4b" style="opacity: 0.3"
                                            class="fs-6">(<?php echo !empty($recentstats) ? Formatter::prependPlusIfNeeded(Formatter::formatBytes($recentstats['change']['total_storage'])) : 0; ?>)</span>
                                    </div>
                                </div>
                                <!-- Online Hosts -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">Online Hosts</span>
                                        <br><span id="stats5a"
                                            class="glanceNumber fs-4"><?php echo !empty($recentstats) ? Locale::integer($recentstats['actual']['online_hosts']) : 0; ?></span>
                                        <span id="stats5b" style="opacity: 0.3"
                                            class="fs-6">(<?php echo !empty($recentstats) ? Siagraph\Utils\Locale::signedDecimal($recentstats['change']['online_hosts'], 0) : 0; ?>)</span>
                                    </div>
                                </div>

                                <!-- Siacoin Market Value -->
                                <div class="col-md-4">
                                    <div class="p-2">
                                        <span class="fs-6">Siacoin Market Value</span>
                                        <br>
                                        <span id="stats6a"
                                            class="glanceNumber fs-4"><?php echo strtoupper($currencyCookie) . " " . (!empty($recentstats) ? Locale::decimal($recentstats['actual']['coin_price'][$currencyCookie], 6) : 0); ?>
                                        </span>
                                        <span id="stats6b" style="opacity: 0.3"
                                            class="fs-6">(<?php echo strtoupper($currencyCookie) . " ";
                                            echo !empty($recentstats) ? Siagraph\Utils\Locale::signedDecimal($recentstats['change']['coin_price'][$currencyCookie], 6) : 0; ?>)</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card__footer">
                        <!--<a href="network_storage" class="button">View details</a>-->
                    </div>
                </section>
            </div>

            <div class="sg-container__column sg-container__column--half">
                <section id="blockchain-explorer" class="card">
                    <h2 class="card__heading">
                        Blockchain Explorer
                    </h2>
                    <div class="card__content">
                        <div class="text-right space-y-2">
                            <!-- Connected peers at the top -->
                            <div class="text-gray-400 text-xs">
                                Connected peers: <span id="connected-peers"
                                    class="font-semibold"><?php echo !empty($explorerData) ? Locale::integer($explorerData['connectedPeers']) : 0; ?></span>
                            </div>
                        </div>
                        <!-- Block height, found time, and Connected peers -->
                        <div class="p-2 rounded-lg flex justify-between text-sm">
                            <!-- Block height and time on the left -->
                            <div>
                                <span id="block-height"
                                    class="font-bold text-5xl"><?php echo !empty($explorerData) ? Locale::integer($explorerData['blockHeight']) : 0; ?></span>
                                <!-- Time block was found -->
                                <span id="block-found-time" class="text-gray-400 text-sm block mt-1"
                                    data-time="<?php echo !empty($explorerData) ? $explorerData['blockFoundTime'] : ''; ?>">
                                    <?php echo $foundTimeText; ?>
                                </span>
                                <!-- Time since the block was found (HH:MM:SS format) -->
                                <span id="time-since-found"
                                    class="text-gray-300 text-sm block"><?php echo $timeSince; ?></span>
                                <span id="time-average"
                                    class="text-gray-300 text-sm block"><?php echo $timeAverage; ?></span>

                            </div>

                            <div class="text-right space-y-2">

                                <div class="flex justify-end items-center space-x-2">
                                    <span class="fs-6">New contracts:</span>
                                    <span id="new-contracts"
                                        class="glanceNumber fs-4"><?php echo !empty($explorerData) ? Locale::integer($explorerData['newContracts']) : 0; ?></span>
                                </div>
                                <div class="flex justify-end items-center space-x-2">
                                    <span class="fs-6">Completed contracts:</span>
                                    <span id="completed-contracts"
                                        class="glanceNumber fs-4"><?php echo !empty($explorerData) ? Locale::integer($explorerData['completedContracts']) : 0; ?></span>
                                </div>
                                <div class="flex justify-end items-center space-x-2">
                                    <span class="fs-6">New hosts:</span>
                                    <span id="new-hosts"
                                        class="glanceNumber fs-4"><?php echo !empty($explorerData) ? Locale::integer($explorerData['newHosts']) : 0; ?></span>
                                </div>
                            </div>
                        </div>

                        <div class="p-2 rounded-lg flex justify-between text-sm mt-2">
                            <div>
                                <span class="block fs-6">Next block</span>
                                <span id="next-block"
                                    class="block font-semibold text-lg"><?php echo $nextBlock ?: 0; ?></span>
                            </div>
                            <div class="flex flex-col items-end">
                                <span class="block fs-6">Unconfirmed transactions</span>
                                <span id=unconfirmed-transactions
                                    class="block font-semibold text-lg"><?php echo !empty($explorerData) ? Locale::integer($explorerData['unconfirmedTransactions']) : 0; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="card__footer">
                        <!--<a href="https://explorer.sia.tech" class="button">View details</a>-->
                    </div>
                </section>
            </div>

            <div class="sg-container__column sg-container__column--half">
                <section id="graph-section" class="card">
                    <h2 class="card__heading">
                        Utilized Storage
                    </h2>
                    <div class="card__content">
                        <!-- Graph Section for Network -->
                        <section class="graph-container">
                            <?php
                            renderGraph(
                                $canvasid = "networkstorage",
                                $datasets = [
                                    array_merge(
                                        $graphConfigs['utilized_storage'],
                                        ['startAtZero' => false]
                                    )
                                ],
                                $dateKey = "date",
                                $jsonUrl = $growthEndpoint, // JSON URL
                                $jsonData = null,
                                $charttype = 'line',
                                $interval = $intervalDefault,
                                $rangeslider = false,
                                $displaylegend = false,
                                $defaultrangeinmonths = 3,
                                $displayYAxis = "false",
                                $unitType = 'bytes',
                                $jsonKey = null
                            );
                            ?>

                        </section>
                    </div>
                    <div class="card__footer">
                        <!--<a href="network_storage" class="button">View details</a>-->
                    </div>
                </section>
            </div>

            <div class="sg-container__column sg-container__column--half">
                <section id="graph2-section" class="card">
                    <h2 class="card__heading">
                        Monthly Revenue
                    </h2>
                    <div class="card__content">
                        <!-- Graph Section for Network -->
                        <section class="graph-container">
                            <?php
                            // Call the function with specific parameters
                            renderGraph(
                                $canvasid = "monthlyrevenue",
                                $datasets = [
                                    array_merge(
                                        $graphConfigs['contract_revenue'],
                                        ['fiatUnit' => strtoupper($currencyCookie)]
                                    )
                                ],
                                $dateKey = "date",
                                $jsonUrl = '/api/v1/monthly/aggregates', // JSON URL
                                $jsonData = null,
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
                    </div>
                    <div class="card__footer">
                        <!--<a href="financials_overview" class="button">View details</a>-->
                    </div>
                </section>
            </div>
        </div>

    </div>

    <!-- Cards grid commented out for later re-enable
        <div class="sg-container__row">
            <div class="sg-container__row-header">
                <h2 class="sg-container__heading">Overview</h2>
            </div>
            <div class="sg-container__row-content">
                <div class="sg-container__column sg-container__column--one-fourth">
                    <a href="mining_stats" class="card">
                        <div class="card__icon">
                            <i class="bi bi-hammer"></i>
                        </div>
                        <div class="card__heading">
                            Mining stats
                        </div>
                        <div class="card__content">
                            Lorem ipsum dolor sit amet, consectetur adipisicing elit. Dicta explicabo.
                        </div>
                        <div class="card__footer">
                            <div class="card__view"></div>
                        </div>
                    </a>
                </div>
                <div class="sg-container__column sg-container__column--one-fourth">
                    <a href="hosting" class="card">
                        <div class="card__icon"><i class="bi bi-hdd-network"></i></div>
                        <div class="card__heading">Hosting</div>
                        <div class="card__content">View host stats, tools and pricing.</div>
                        <div class="card__footer"><div class="card__view"></div></div>
                    </a>
                </div>
                <div class="sg-container__column sg-container__column--one-fourth">
                    <a href="network_overview" class="card">
                        <div class="card__icon"><i class="bi bi-globe2"></i></div>
                        <div class="card__heading">Network</div>
                        <div class="card__content">View network statistics and tools.</div>
                        <div class="card__footer"><div class="card__view"></div></div>
                    </a>
                </div>
                <div class="sg-container__column sg-container__column--one-fourth">
                    <a href="revenue" class="card">
                        <div class="card__icon"><i class="bi bi-currency-dollar"></i></div>
                        <div class="card__heading">Revenue</div>
                        <div class="card__content">Contract revenue and burned funds.</div>
                        <div class="card__footer"><div class="card__view"></div></div>
                    </a>
                </div>
                <div class="sg-container__column sg-container__column--one-fourth">
                    <a href="siafunds_overview" class="card">
                        <div class="card__icon"><i class="bi bi-piggy-bank"></i></div>
                        <div class="card__heading">Siafunds</div>
                        <div class="card__content">Siafunds metrics and revenue information.</div>
                        <div class="card__footer"><div class="card__view"></div></div>
                    </a>
                </div>
            </div>
        </div>
        -->
</section>

<div id="index-data" data-cached-data='<?= htmlspecialchars(json_encode($recentstats), ENT_QUOTES, "UTF-8") ?>'
    data-cached-highlights='<?= htmlspecialchars(json_encode(['metrics' => $cachedMetrics, 'aggregates' => $cachedAggregates]), ENT_QUOTES, "UTF-8") ?>'
    data-cached-explorer='<?= htmlspecialchars(json_encode($explorerData), ENT_QUOTES, "UTF-8") ?>'></div>
<?php render_footer(['js/index.js']); ?>
