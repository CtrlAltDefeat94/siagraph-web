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

$months = 12; // default visible range for charts

$dailyEndpoint = '/api/v1/daily/aggregates';
$monthlyEndpoint = '/api/v1/monthly/aggregates';

$dailyData = ApiClient::fetchJson($dailyEndpoint);
$monthlyData = ApiClient::fetchJson($monthlyEndpoint);
$dailyError = !is_array($dailyData);
$monthlyError = !is_array($monthlyData);
$latestDaily = $dailyError ? [] : end($dailyData);
$latestMonthly = $monthlyError ? [] : end($monthlyData);
$asOfDaily = !$dailyError && isset($latestDaily['date']) ? $latestDaily['date'] : null;
$asOfMonthly = !$monthlyError && isset($latestMonthly['date']) ? $latestMonthly['date'] : null;

// Latest KPIs
$latestDailyPerSf = isset($latestDaily["siafund_tax_revenue"]["sc"]) ? ($latestDaily["siafund_tax_revenue"]["sc"] / 1e24) / 10000 : 0;
$latestMonthlyRevenueAll = isset($latestMonthly["siafund_tax_revenue"]["sc"]) ? floatval($latestMonthly["siafund_tax_revenue"]["sc"]) / 1e24 : 0;
$latestMonthlyTradedSf = isset($latestMonthly['siafund_volume']) ? floatval($latestMonthly['siafund_volume']) / 1e24 : 0;
?>
<?php render_header('SiaGraph - Siafunds'); ?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-piggy-bank me-2"></i>Siafunds</h1>
    <?php if ($dailyError || $monthlyError): ?>
        <p class="text-center text-muted">Siafunds data unavailable.</p>
    <?php endif; ?>

    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-piggy-bank',
                'label' => 'Latest Daily Revenue per Siafund',
                'value' => $dailyError ? 'N/A' : Locale::decimal($latestDailyPerSf, 6) . ' SC',
                'context' => $asOfDaily ? ('Daily value as of ' . $asOfDaily) : 'Daily value',
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-calendar3',
                'label' => 'Latest Monthly Revenue (All Siafunds)',
                'value' => $monthlyError ? 'N/A' : Locale::decimal($latestMonthlyRevenueAll, 2) . ' SC',
                'context' => $asOfMonthly ? ('Monthly total as of ' . $asOfMonthly) : 'Monthly total',
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-cash-stack',
                'label' => 'Siafunds Traded Volume (Monthly)',
                'value' => $monthlyError ? 'N/A' : Locale::decimal($latestMonthlyTradedSf, 0) . ' SF',
                'context' => $asOfMonthly ? ('Monthly total as of ' . $asOfMonthly) : 'Monthly total',
            ]);
            ?>
            </div>
        </div>
    
    <div class="sg-container__row">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Daily Revenue per Siafund</h2>
                <?php
                renderGraph(
                    'daily-siafund-tax-per-sf',
                    [
                        array_merge(
                            $graphConfigs['siafund_tax_revenue'],
                            [
                                'transform' => "return useFiat ? entry['siafund_tax_revenue'][currency] / 10000 : entry['siafund_tax_revenue']['sc'] / (1e24 * 10000);",
                                'fiatUnit' => strtoupper($currencyCookie),
                                'decimalPlaces' => 6
                            ]
                        )
                    ],
                    'date',
                    $dailyEndpoint,
                    null,
                    'bar',
                    'week',
                    true,
                    'true',
                    $months,
                    'false',
                    $currencyCookie
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
                <h2 class="card__heading">Monthly Revenue (All Siafunds)</h2>
                <?php
                renderGraph(
                    'monthly-siafund-tax-total',
                    [
                        array_merge(
                            $graphConfigs['siafund_tax_revenue'],
                            ['fiatUnit' => strtoupper($currencyCookie)]
                        )
                    ],
                    'date',
                    $monthlyEndpoint,
                    null,
                    'bar',
                    'month',
                    true,
                    'true',
                    $months,
                    'false',
                    $currencyCookie
                );
                ?>
            </section>
        </div>
        </div>
    </div>
</section>
<?php render_footer(); ?>
