<?php
require_once 'bootstrap.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';
require_once 'include/layout.php';
require_once 'include/components/stat_card.php';
require_once 'include/components/host_pricing_trends.php';
use Siagraph\Utils\Formatter;
use Siagraph\Utils\ApiClient;
$months = 6; // default visible range; slider will cover all data
$data = ApiClient::fetchJson('/api/v1/daily/host_prices');
$currencyCookie = isset($_COOKIE['currency']) ? strtolower($_COOKIE['currency']) : 'eur';
$dataError = !is_array($data);
$latest = $dataError ? [] : end($data);
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;
?>
<?php render_header('SiaGraph - Host Pricing'); ?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-currency-dollar me-2"></i>Host Pricing</h1>
    <form class="mb-3">
        <!-- range dropdown removed -->
    </form>
    <?php if ($dataError): ?>
        <p class="text-center text-muted">Host pricing data unavailable.</p>
    <?php endif; ?>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-hdd-fill',
                'label' => 'Average Storage Price',
                'value' => isset($latest['avg_storage_price']) ? Formatter::formatSiacoins($latest['avg_storage_price']/1e12) : 'N/A',
                'context' => $asOf ? ('Daily snapshot as of ' . $asOf) : 'Daily snapshot',
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-upload',
                'label' => 'Average Upload Price',
                'value' => isset($latest['avg_upload_price']) ? Formatter::formatSiacoins($latest['avg_upload_price']/1e12) : 'N/A',
                'context' => $asOf ? ('Daily snapshot as of ' . $asOf) : 'Daily snapshot',
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-download',
                'label' => 'Average Download Price',
                'value' => isset($latest['avg_download_price']) ? Formatter::formatSiacoins($latest['avg_download_price']/1e12) : 'N/A',
                'context' => $asOf ? ('Daily snapshot as of ' . $asOf) : 'Daily snapshot',
            ]);
            ?>
            </div>
        </div>
    </div>
    <div class="sg-container__row">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Pricing Trends</h2>
                <?php render_host_pricing_trends('host-price-trend', $months, $currencyCookie, ['hideDownload' => false]); ?>
            </section>
        </div>
        </div>
    </div>
</section>
<?php render_footer(); ?>
