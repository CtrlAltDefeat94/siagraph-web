<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/stat_card.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;

// fetch latest metrics
$metricsEndpoint = '/api/v1/daily/metrics?start=' . date('Y-m-d', strtotime('-6 months'));
$latestData = ApiClient::fetchJson($metricsEndpoint);
$dataError = !is_array($latestData);
$latest = $dataError ? [] : end($latestData);
// Compute previous entry for deltas
$prev = [];
if (!$dataError && count($latestData) >= 2) {
    $prev = $latestData[count($latestData) - 2];
}
// As-of date for clarity
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;

render_header('SiaGraph - Network');
?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-globe2 me-2"></i>Network Overview</h1>

    <?php if ($dataError): ?>
        <p class="text-center text-muted">Network metrics unavailable.</p>
    <?php endif; ?>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
                <?php
                $valueUS = isset($latest['utilized_storage']) ? Locale::decimal($latest['utilized_storage']/1e15,2).' PB' : 'N/A';
                $deltaUS = (isset($prev['utilized_storage']) && isset($latest['utilized_storage'])) ? ($latest['utilized_storage'] - $prev['utilized_storage']) : null;
                render_stat_card([
                    'icon' => 'bi bi-hdd-fill',
                    'label' => 'Utilized Storage',
                    'value' => $valueUS,
                    'changeRaw' => $deltaUS,
                    'changeFormat' => $deltaUS === null ? null : 'bytes',
                    'context' => $asOf ? ('Daily snapshot as of ' . $asOf) : 'Daily snapshot',
                    'deltaLabel' => $deltaUS === null ? null : 'vs prev day',
                    'tooltip' => 'Point-in-time value and day-over-day change',
                ]);
                ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <?php
                $valueTS = isset($latest['total_storage']) ? Locale::decimal($latest['total_storage']/1e15,2).' PB' : 'N/A';
                $deltaTS = (isset($prev['total_storage']) && isset($latest['total_storage'])) ? ($latest['total_storage'] - $prev['total_storage']) : null;
                render_stat_card([
                    'icon' => 'bi bi-hdd-network',
                    'label' => 'Total Storage',
                    'value' => $valueTS,
                    'changeRaw' => $deltaTS,
                    'changeFormat' => $deltaTS === null ? null : 'bytes',
                    'context' => $asOf ? ('Daily snapshot as of ' . $asOf) : 'Daily snapshot',
                    'deltaLabel' => $deltaTS === null ? null : 'vs prev day',
                    'tooltip' => 'Point-in-time value and day-over-day change',
                ]);
                ?>
            </div>
        </div>
    </div>

    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content">
            <div class="sg-container__column">
                <section class="card">
                    <h2 class="card__heading">Network Storage</h2>
                    <div class="card__content">
                        <section class="graph-container">
                    <?php
                    renderGraph(
                        'network-storage',
                        [
                            $graphConfigs['utilized_storage'],
                            $graphConfigs['total_storage']
                        ],
                        'date',
                        '/api/v1/daily/growth',
                        null,
                        'line',
                        'week',
                        true,
                        'true',
                        6,
                        'false',
                        'bytes'
                    );
                    ?>
                        </section>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Cards grid commented out for later re-enable
    <div class="sg-container__row">
        <div class="sg-container__row-content">
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="network_growth" class="card">
                    <div class="card__icon"><i class="bi bi-graph-up-arrow"></i></div>
                    <div class="card__heading">Network growth</div>
                    <div class="card__content">Historical growth charts.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="network_storage" class="card">
                    <div class="card__icon"><i class="bi bi-hdd"></i></div>
                    <div class="card__heading">Storage</div>
                    <div class="card__content">Network storage usage.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="hosting" class="card">
                    <div class="card__icon"><i class="bi bi-hdd-network"></i></div>
                    <div class="card__heading">Hosting</div>
                    <div class="card__content">Detailed host metrics.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="network_aggregates" class="card">
                    <div class="card__icon"><i class="bi bi-stack"></i></div>
                    <div class="card__heading">Aggregates</div>
                    <div class="card__content">Aggregated metrics over time.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="peers" class="card">
                    <div class="card__icon"><i class="bi bi-people"></i></div>
                    <div class="card__heading">Peer Explorer</div>
                    <div class="card__content">Explore active peers.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="storage_forecast" class="card">
                    <div class="card__icon"><i class="bi bi-cloud"></i></div>
                    <div class="card__heading">Storage forecast</div>
                    <div class="card__content">Predict future usage.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="consensus" class="card">
                    <div class="card__icon"><i class="bi bi-sliders"></i></div>
                    <div class="card__heading">Consensus stats</div>
                    <div class="card__content">Blockchain consensus metrics.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
        </div>
    </div>
    -->
</section>
<?php render_footer(); ?>
