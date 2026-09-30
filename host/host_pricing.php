<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
require_once dirname(__DIR__) . '/include/components/host_pricing_trends.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;
use Siagraph\Utils\CurrencyDisplay;

$months = 6; // default visible range; slider will cover all data
$data = ApiClient::fetchJson('/api/v1/daily/host_prices');
$currencyCookie = CurrencyDisplay::selectedCurrency();
$dataError = !is_array($data);
$latest = $dataError ? [] : end($data);
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;
$ratesByDate = $asOf ? CurrencyDisplay::loadDailyRates($asOf, $asOf) : [];
$asOfText = $asOf ? ('Daily snapshot as of ' . Locale::date($asOf)) : 'Daily snapshot';
$storagePriceValue = isset($latest['avg_storage_price'])
    ? CurrencyDisplay::formatMonetary([
        'scValue' => ((float) $latest['avg_storage_price'] / 1e12) * 4320,
        'currency' => $currencyCookie,
        'date' => $asOf,
        'ratesByDate' => $ratesByDate,
        'decimals' => 2,
        'scDecimals' => 2,
        'suffix' => '/TB/Month',
    ])
    : 'N/A';
$uploadPriceValue = isset($latest['avg_upload_price'])
    ? CurrencyDisplay::formatMonetary([
        'scValue' => (float) $latest['avg_upload_price'] / 1e12,
        'currency' => $currencyCookie,
        'date' => $asOf,
        'ratesByDate' => $ratesByDate,
        'decimals' => 2,
        'scDecimals' => 2,
        'suffix' => '/TB',
    ])
    : 'N/A';
$downloadPriceValue = isset($latest['avg_download_price'])
    ? CurrencyDisplay::formatMonetary([
        'scValue' => (float) $latest['avg_download_price'] / 1e12,
        'currency' => $currencyCookie,
        'date' => $asOf,
        'ratesByDate' => $ratesByDate,
        'decimals' => 2,
        'scDecimals' => 2,
        'suffix' => '/TB',
    ])
    : 'N/A';

render_header('SiaGraph - Host Pricing', 'SiaGraph - Host Pricing', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/host-pricing.css'), ENT_QUOTES, 'UTF-8') . '">'
]);
?>
<section id="main-content" class="sg-container host-pricing-page">
    <section class="card host-pricing-hero">
        <div>
            <div class="host-pricing-hero__kicker">Host market</div>
            <h1 class="host-pricing-hero__title">Host Pricing</h1>
            <p class="host-pricing-hero__copy">
                Track average advertised storage, upload, and download prices across the host network, shown first in fiat with SC values as secondary context.
            </p>
            <?php if ($dataError): ?>
                <p class="host-pricing-hero__status">Host pricing data unavailable.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="host-pricing-stat-grid" aria-label="Host pricing summary">
        <article class="host-pricing-stat-card">
            <div class="host-pricing-stat-card__label"><i class="bi bi-hdd-fill" aria-hidden="true"></i>Average Storage Price</div>
            <div class="host-pricing-stat-card__value"><?php echo $storagePriceValue; ?></div>
            <div class="host-pricing-stat-card__context"><?php echo htmlspecialchars($asOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="host-pricing-stat-card">
            <div class="host-pricing-stat-card__label"><i class="bi bi-upload" aria-hidden="true"></i>Average Upload Price</div>
            <div class="host-pricing-stat-card__value"><?php echo $uploadPriceValue; ?></div>
            <div class="host-pricing-stat-card__context"><?php echo htmlspecialchars($asOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="host-pricing-stat-card">
            <div class="host-pricing-stat-card__label"><i class="bi bi-download" aria-hidden="true"></i>Average Download Price</div>
            <div class="host-pricing-stat-card__value"><?php echo $downloadPriceValue; ?></div>
            <div class="host-pricing-stat-card__context"><?php echo htmlspecialchars($asOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
    </section>

    <section class="card host-pricing-chart-card">
        <h2 class="card__heading">Pricing Trends</h2>
        <p class="host-pricing-section-copy">Daily average host pricing for storage, upload, and download markets. <a href="/siacoin_price">View SC exchange rate history →</a></p>
        <div class="card__content">
            <?php render_host_pricing_trends('host-price-trend', $months, $currencyCookie, ['hideDownload' => false]); ?>
        </div>
    </section>
</section>
<?php render_footer(); ?>
