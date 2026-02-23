<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/range_controls.php';
require_once 'include/components/stat_card.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';
$currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;

$months = 12; // default visible range; slider covers all data
$aggEndpoint = '/api/v1/monthly/aggregates';
$intervalDefault = 'month';
$latestData = ApiClient::fetchJson($aggEndpoint);
$dataError = !is_array($latestData);
$latest = $dataError ? [] : end($latestData);
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;
render_header("SiaGraph - Token Volume"); ?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-bar-chart me-2"></i>Token Volume</h1>
    <?php if ($dataError): ?>
        <p class="text-center text-muted">Volume data unavailable.</p>
    <?php endif; ?>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-currency-bitcoin',
                'label' => 'Siacoin Volume',
                'value' => isset($latest['siacoin_volume']) ? Locale::decimal($latest['siacoin_volume']/1e24,0).' SC' : 'N/A',
                'context' => $asOf ? ('Monthly total as of ' . $asOf) : 'Monthly total',
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-cash-stack',
                'label' => 'Siafunds Volume',
                'value' => isset($latest['siafund_volume']) ? Locale::decimal($latest['siafund_volume']/1e24,0).' SF' : 'N/A',
                'context' => $asOf ? ('Monthly total as of ' . $asOf) : 'Monthly total',
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-wallet',
                'label' => 'Unique TX Addresses',
                'value' => isset($latest['unique_transaction_addresses']) ? Locale::integer($latest['unique_transaction_addresses']) : 'N/A',
                'context' => $asOf ? ('Monthly total as of ' . $asOf) : 'Monthly total',
            ]);
            ?>
            </div>
        </div>
    <div class="sg-container__row">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Siacoin Trade Volume</h2>
                <?php
                renderGraph(
                    'aggregates-siacoin-volume',
                    [
                        $graphConfigs['siacoin_volume']
                    ],
                    'date',
                    $aggEndpoint,
                    null,
                    'bar',
                    $intervalDefault,
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
    <div class="sg-container__row">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Siafunds Trade Volume</h2>
                <?php
                renderGraph(
                    'aggregates-siafund-volume',
                    [
                        array_merge($graphConfigs['siafund_volume'], ['unitDivisor' => 1, 'decimalPlaces' => 0])
                    ],
                    'date',
                    $aggEndpoint,
                    null,
                    'bar',
                    $intervalDefault,
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
                <h2 class="card__heading">Unique Transaction Addresses</h2>
                <?php
                renderGraph(
                    'aggregates-unique-tx-addresses',
                    [
                        $graphConfigs['unique_transaction_addresses']
                    ],
                    'date',
                    $aggEndpoint,
                    null,
                    'line',
                    $intervalDefault,
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
</section>
<?php render_footer(); ?>
