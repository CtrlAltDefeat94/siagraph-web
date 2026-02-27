<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/range_controls.php';
require_once 'include/components/stat_card.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';
$currencyCookie = \Siagraph\Utils\CurrencyDisplay::selectedCurrency();

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;
use Siagraph\Utils\CurrencyDisplay;

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
$ratesByDate = [];
if ($asOfDaily && $asOfMonthly) {
    $ratesByDate = CurrencyDisplay::loadDailyRates($asOfDaily, $asOfMonthly);
} elseif ($asOfDaily) {
    $ratesByDate = CurrencyDisplay::loadDailyRates($asOfDaily, $asOfDaily);
} elseif ($asOfMonthly) {
    $ratesByDate = CurrencyDisplay::loadDailyRates($asOfMonthly, $asOfMonthly);
}
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
                'value' => $dailyError ? 'N/A' : CurrencyDisplay::formatMonetary([
                    'scValue' => $latestDailyPerSf,
                    'currency' => $currencyCookie,
                    'date' => $asOfDaily,
                    'ratesByDate' => $ratesByDate,
                    'decimals' => 6,
                    'scDecimals' => 6,
                ]),
                'context' => $asOfDaily ? ('Daily value as of ' . $asOfDaily) : 'Daily value',
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-calendar3',
                'label' => 'Latest Monthly Revenue (All Siafunds)',
                'value' => $monthlyError ? 'N/A' : CurrencyDisplay::formatMonetary([
                    'scValue' => $latestMonthlyRevenueAll,
                    'currency' => $currencyCookie,
                    'date' => $asOfMonthly,
                    'ratesByDate' => $ratesByDate,
                    'decimals' => 2,
                    'scDecimals' => 2,
                ]),
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
                                'transform' => "var bucket = entry['siafund_tax_revenue']; var sc = (window.currencyDisplay && window.currencyDisplay.normalizeScValue) ? window.currencyDisplay.normalizeScValue(bucket) : null; if (sc === null) return null; var perSf = sc / 10000; if (!useFiat) return perSf; var direct = (bucket && bucket[currency] !== undefined) ? Number(bucket[currency]) : null; if (isFinite(direct)) return direct / 10000; var r = (window.currencyDisplay && window.currencyDisplay.resolveRateForEntryDate) ? window.currencyDisplay.resolveRateForEntryDate(entry['date'], currency, null) : null; return (isFinite(r) && r > 0) ? perSf * r : perSf;",
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
                        $graphConfigs['siafund_tax_revenue']
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
