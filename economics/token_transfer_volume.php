<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
include_once dirname(__DIR__) . '/include/graph.php';
$graphConfigs = require dirname(__DIR__) . '/include/graph_configs.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;
use Siagraph\Utils\CurrencyDisplay;

$months = 12; // default visible range; slider covers all data
$aggEndpoint = '/api/v1/monthly/aggregates';
$intervalDefault = 'month';
$latestData = ApiClient::fetchJson($aggEndpoint);
$dataError = !is_array($latestData);
$latest = $dataError ? [] : end($latestData);
$currencyCookie = CurrencyDisplay::selectedCurrency();
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;
$ratesByDate = $asOf ? CurrencyDisplay::loadDailyRates($asOf, $asOf) : [];
$asOfText = $asOf ? ('Monthly total as of ' . Locale::date($asOf)) : 'Monthly total';
$siacoinVolumeValue = isset($latest['siacoin_volume']) ? CurrencyDisplay::formatMonetary([
    'scValue' => (float) $latest['siacoin_volume'] / 1e24,
    'currency' => $currencyCookie,
    'date' => $asOf,
    'ratesByDate' => $ratesByDate,
    'decimals' => 0,
    'scDecimals' => 0,
]) : 'N/A';
$siafundVolumeValue = isset($latest['siafund_volume'])
    ? Locale::decimal((float) $latest['siafund_volume'] / 1e24, 0) . ' SF'
    : 'N/A';
$uniqueAddressesValue = isset($latest['unique_transaction_addresses'])
    ? Locale::integer($latest['unique_transaction_addresses'])
    : 'N/A';

render_header('SiaGraph - Token Transfer Volume', 'SiaGraph - Token Transfer Volume', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/token-transfer-volume.css'), ENT_QUOTES, 'UTF-8') . '">'
]);
?>
<section id="main-content" class="sg-container token-volume-page">
    <section class="card token-volume-hero">
        <div>
            <div class="token-volume-hero__kicker">Network activity</div>
            <h1 class="token-volume-hero__title">Token Transfer Volume</h1>
            <p class="token-volume-hero__copy">
                Track monthly Siacoin movement, Siafund transfer volume, and the number of unique addresses participating in transactions.
            </p>
            <?php if ($dataError): ?>
                <p class="token-volume-hero__status">Volume data unavailable.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="token-volume-stat-grid" aria-label="Token volume summary">
        <article class="token-volume-stat-card">
            <div class="token-volume-stat-card__label"><i class="bi bi-currency-bitcoin" aria-hidden="true"></i>Siacoin Volume</div>
            <div class="token-volume-stat-card__value"><?php echo $siacoinVolumeValue; ?></div>
            <div class="token-volume-stat-card__context"><?php echo htmlspecialchars($asOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="token-volume-stat-card">
            <div class="token-volume-stat-card__label"><i class="bi bi-cash-stack" aria-hidden="true"></i>Siafunds Volume</div>
            <div class="token-volume-stat-card__value"><?php echo htmlspecialchars($siafundVolumeValue, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="token-volume-stat-card__context"><?php echo htmlspecialchars($asOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="token-volume-stat-card">
            <div class="token-volume-stat-card__label"><i class="bi bi-wallet" aria-hidden="true"></i>Unique TX Addresses</div>
            <div class="token-volume-stat-card__value"><?php echo htmlspecialchars($uniqueAddressesValue, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="token-volume-stat-card__context"><?php echo htmlspecialchars($asOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
    </section>

    <section class="token-volume-chart-grid">
        <section class="card token-volume-chart-card token-volume-chart-card--wide">
            <h2 class="card__heading">Siacoin Transfer Volume</h2>
            <p class="token-volume-section-copy">Monthly SC transfer volume shown in the selected display currency with SC available as context.</p>
            <div class="card__content">
                <section class="graph-container">
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
                        $currencyCookie
                    );
                    ?>
                </section>
            </div>
        </section>

        <section class="card token-volume-chart-card">
            <h2 class="card__heading">Siafunds Transfer Volume</h2>
            <p class="token-volume-section-copy">Monthly Siafund movement across observed transactions.</p>
            <div class="card__content">
                <section class="graph-container">
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
        </section>

        <section class="card token-volume-chart-card">
            <h2 class="card__heading">Unique Transaction Addresses</h2>
            <p class="token-volume-section-copy">Monthly count of unique addresses seen in transactions.</p>
            <div class="card__content">
                <section class="graph-container">
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
        </section>
    </section>
</section>
<?php render_footer(); ?>
