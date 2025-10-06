<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/stat_card.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;

// fetch latest metrics for quick stats
$metricsEndpoint = '/api/v1/daily/metrics?start=' . date('Y-m-d', strtotime('-6 months'));
$latestData = ApiClient::fetchJson($metricsEndpoint);
$dataError = !is_array($latestData);
$latest = $dataError ? [] : end($latestData);
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;

render_header('SiaGraph - Hosting');
?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-hdd-network me-2"></i>Hosting</h1>

    <?php if ($dataError): ?>
        <p class="text-center text-muted">Latest metrics unavailable.</p>
    <?php endif; ?>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
                <?php
                render_stat_card([
                    'icon' => 'bi bi-hdd-network',
                    'label' => 'Active Hosts',
                'value' => isset($latest['active_hosts']) ? Locale::integer($latest['active_hosts']) : 'N/A',
                    'context' => $asOf ? ('Daily snapshot as of ' . $asOf) : 'Daily snapshot',
                ]);
                ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <?php
                render_stat_card([
                    'icon' => 'bi bi-hash',
                    'label' => 'Block Height',
                'value' => isset($latest['block_height']) ? Locale::integer($latest['block_height']) : 'N/A',
                    'context' => $asOf ? ('Daily snapshot as of ' . $asOf) : 'Daily snapshot',
                ]);
                ?>
            </div>
        </div>
    </div>

    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content">
            <div class="sg-container__column">
                <section class="card">
                    <h2 class="card__heading">Host Counts</h2>
                    <div class="card__content">
                    <?php
                    renderGraph(
                        'host-count-trend',
                        [
                            $graphConfigs['active_hosts'],
                            $graphConfigs['total_hosts']
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
                        null
                    );
                    ?>
                    </div>
                </section>
            </div>
        </div>

    <!-- Cards grid commented out for later re-enable
    <div class="sg-container__row">
        <div class="sg-container__row-content">
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="host_overview" class="card">
                    <div class="card__icon"><i class="bi bi-diagram-3"></i></div>
                    <div class="card__heading">Host Overview</div>
                    <div class="card__content">Distribution and reliability charts.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="host_explorer" class="card">
                    <div class="card__icon"><i class="bi bi-search"></i></div>
                    <div class="card__heading">Host Explorer</div>
                    <div class="card__content">Search and inspect a host.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="host_troubleshooter" class="card">
                    <div class="card__icon"><i class="bi bi-wrench"></i></div>
                    <div class="card__heading">Host Troubleshooter</div>
                    <div class="card__content">Diagnose hosting issues.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="host_pricing" class="card">
                    <div class="card__icon"><i class="bi bi-currency-dollar"></i></div>
                    <div class="card__heading">Host Pricing</div>
                    <div class="card__content">Historical price charts.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
        </div>
    </div>
    -->
</section>
<?php render_footer(); ?>
