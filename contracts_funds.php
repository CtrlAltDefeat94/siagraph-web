<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/stat_card.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;

$months = 12; // default visible range; slider covers all data
$dailyMetricsEndpoint = '/api/v1/daily/metrics';
$monthlyMetricsEndpoint = '/api/v1/monthly/metrics';
$latestData = ApiClient::fetchJson('/api/v1/daily/metrics');
$dataError = !is_array($latestData);
$latest = $dataError ? [] : end($latestData);
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;
$asOfText = $asOf ? ('Daily snapshot as of ' . \Siagraph\Utils\Locale::date($asOf)) : 'Daily snapshot';
?>
<?php render_header("SiaGraph - Contracts & Collateral"); ?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-file-earmark-text me-2"></i>Contracts &amp; Collateral</h1>
    <!-- range dropdown removed -->
    <?php if ($dataError): ?>
        <p class="text-center text-muted">Latest metrics unavailable.</p>
    <?php endif; ?>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-file-earmark-text',
                'label' => 'Active Contracts',
                'value' => isset($latest['active_contracts']) ? Locale::integer($latest['active_contracts']) : 'N/A',
                'context' => $asOfText,
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-wallet',
                'label' => 'Renter Balance',
                'value' => isset($latest['renter_collateral_locked']) ? Locale::decimal($latest['renter_collateral_locked']/1e24,0).' SC' : 'N/A',
                'context' => $asOfText,
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-shield-lock',
                'label' => 'Host Collateral',
                'value' => isset($latest['host_collateral_locked']) ? Locale::decimal($latest['host_collateral_locked']/1e24,0).' SC' : 'N/A',
                'context' => $asOfText,
            ]);
            ?>
            </div>
        </div>
    </div>
    <div class="sg-container__row">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Active Contracts</h2>
                <?php
                renderGraph(
                    'contracts-active',
                    [
                        $graphConfigs['active_contracts']
                    ],
                    'date',
                    $dailyMetricsEndpoint,
                    null,
                    'line',
                    'week',
                    true,
                    'true',
                    $months,
                    'false',
                    null
                );
                ?>
            </section>
        </div>
        </div>
    </div>
    <div class="sg-container__row mt-4">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Collateral vs Supply (Raw)</h2>
                <?php
                renderGraph(
                    'collateral-percentage-inline',
                    [
                        $graphConfigs['collateral_locked_percent']
                    ],
                    'date',
                    $monthlyMetricsEndpoint,
                    null,
                    'line',
                    'month',
                    true,
                    'true',
                    $months,
                    'true',
                    'scientific',
                    null,
                    500,
                    'Collateral/Supply (ratio)',
                    'linear'
                );
                ?>
            </section>
        </div>
        </div>
    </div>
    <div class="sg-container__row mt-4">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Locked Funds</h2>
                <?php
                renderGraph(
                    'contracts-collateral',
                    [
                        $graphConfigs['renter_collateral_locked'],
                        $graphConfigs['host_collateral_locked']
                    ],
                    'date',
                    $monthlyMetricsEndpoint,
                    null,
                    'line',
                    'month',
                    true,
                    'true',
                    $months,
                    'false',
                    'sc',
                    null,
                    500,
                    null,
                    'linear',
                    true
                );
                ?>
            </section>
        </div>
        </div>
    </div>
    <div class="sg-container__row mt-4">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Circulating Supply</h2>
                <?php
                renderGraph(
                    'circulating-supply',
                    [
                        $graphConfigs['circulating_supply']
                    ],
                    'date',
                    $monthlyMetricsEndpoint,
                    null,
                    'line',
                    'month',
                    true,
                    'true',
                    $months,
                    'false',
                    'sc'
                );
                ?>
            </section>
        </div>
        </div>
    </div>
</section>
<?php render_footer(); ?>
