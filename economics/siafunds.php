<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
include_once dirname(__DIR__) . '/include/graph.php';
$graphConfigs = require dirname(__DIR__) . '/include/graph_configs.php';

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
$currencyCookie = CurrencyDisplay::selectedCurrency();
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
$dailyAsOfText = $asOfDaily ? ('Daily value as of ' . Locale::date($asOfDaily)) : 'Daily value';
$monthlyAsOfText = $asOfMonthly ? ('Monthly total as of ' . Locale::date($asOfMonthly)) : 'Monthly total';
$dailyPerSfValue = $dailyError ? 'N/A' : CurrencyDisplay::formatMonetary([
    'scValue' => $latestDailyPerSf,
    'currency' => $currencyCookie,
    'date' => $asOfDaily,
    'ratesByDate' => $ratesByDate,
    'decimals' => 6,
    'scDecimals' => 6,
]);
$monthlyRevenueValue = $monthlyError ? 'N/A' : CurrencyDisplay::formatMonetary([
    'scValue' => $latestMonthlyRevenueAll,
    'currency' => $currencyCookie,
    'date' => $asOfMonthly,
    'ratesByDate' => $ratesByDate,
    'decimals' => 2,
    'scDecimals' => 2,
]);
$monthlyTradedValue = $monthlyError ? 'N/A' : Locale::decimal($latestMonthlyTradedSf, 0) . ' SF';

render_header('SiaGraph - Siafunds', 'SiaGraph - Siafunds', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/siafunds.css'), ENT_QUOTES, 'UTF-8') . '">'
]);
?>
<section id="main-content" class="sg-container siafunds-page">
    <section class="card siafunds-hero">
        <div>
            <div class="siafunds-hero__kicker">Siafund economics</div>
            <h1 class="siafunds-hero__title">Siafunds</h1>
            <p class="siafunds-hero__copy">
                Track Siafund tax revenue per fund, total monthly tax revenue, and observed Siafund transfer volume.
            </p>
            <?php if ($dailyError || $monthlyError): ?>
                <p class="siafunds-hero__status">Siafunds data unavailable.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="siafunds-stat-grid" aria-label="Siafund summary">
        <article class="siafunds-stat-card">
            <div class="siafunds-stat-card__label"><i class="bi bi-piggy-bank" aria-hidden="true"></i>Daily Revenue per Siafund</div>
            <div class="siafunds-stat-card__value"><?php echo $dailyPerSfValue; ?></div>
            <div class="siafunds-stat-card__context"><?php echo htmlspecialchars($dailyAsOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="siafunds-stat-card">
            <div class="siafunds-stat-card__label"><i class="bi bi-calendar3" aria-hidden="true"></i>Monthly Revenue</div>
            <div class="siafunds-stat-card__value"><?php echo $monthlyRevenueValue; ?></div>
            <div class="siafunds-stat-card__context"><?php echo htmlspecialchars($monthlyAsOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="siafunds-stat-card">
            <div class="siafunds-stat-card__label"><i class="bi bi-cash-stack" aria-hidden="true"></i>Siafunds Traded</div>
            <div class="siafunds-stat-card__value"><?php echo htmlspecialchars($monthlyTradedValue, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="siafunds-stat-card__context"><?php echo htmlspecialchars($monthlyAsOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
    </section>

    <section class="siafunds-chart-grid">
        <section class="card siafunds-chart-card siafunds-chart-card--wide">
            <h2 class="card__heading">Daily Revenue per Siafund</h2>
            <p class="siafunds-section-copy">Daily Siafund tax revenue normalized per individual Siafund.</p>
            <div class="card__content">
                <section class="graph-container">
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
        </section>

        <section class="card siafunds-chart-card">
            <h2 class="card__heading">Monthly Revenue</h2>
            <p class="siafunds-section-copy">Monthly Siafund tax revenue across all 10,000 Siafunds.</p>
            <div class="card__content">
                <section class="graph-container">
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
        </section>

        <section class="card siafunds-chart-card">
            <h2 class="card__heading">Siafunds Traded Volume</h2>
            <p class="siafunds-section-copy">Monthly Siafund transfer volume observed on chain.</p>
            <div class="card__content">
                <section class="graph-container">
                    <?php
                    renderGraph(
                        'monthly-siafund-volume',
                        [
                            array_merge($graphConfigs['siafund_volume'], ['unitDivisor' => 1, 'decimalPlaces' => 0])
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
                        null
                    );
                    ?>
                </section>
            </div>
        </section>
    </section>
</section>
<?php render_footer(); ?>
