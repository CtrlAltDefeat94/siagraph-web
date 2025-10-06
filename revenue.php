<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/stat_card.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;

// fetch latest aggregates
$aggEndpoint = '/api/v1/daily/aggregates';
$latestData = ApiClient::fetchJson($aggEndpoint);
$dataError = !is_array($latestData);
$latest = $dataError ? [] : end($latestData);
$prev = [];
$currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur';
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;
$asOfText = $asOf ? ('Daily value as of ' . \Siagraph\Utils\Locale::date($asOf)) : 'Daily value';

render_header('SiaGraph - Revenue & Burn');
?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-currency-dollar me-2"></i>Revenue &amp; Burn</h1>

    <?php if ($dataError): ?>
        <p class="text-center text-muted">Latest aggregates unavailable.</p>
    <?php endif; ?>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
                <?php
                $rev = $latest['contract_revenue'] ?? null;
                $value = 'N/A';
                if ($rev) {
                    if ($currencyCookie === 'sc') {
                        $value = Locale::decimal(($rev['sc'] ?? 0)/1e24, 2) . ' SC';
                    } elseif ($currencyCookie === 'usd') {
                        $value = 'USD ' . Locale::decimal(($rev['usd'] ?? 0), 2);
                    } else {
                        $value = 'EUR ' . Locale::decimal(($rev['eur'] ?? 0), 2);
                    }
                }
                render_stat_card([
                    'icon' => 'bi bi-cash-coin',
                    'label' => 'Contract Revenue',
                    'value' => $value,
                    'context' => $asOfText,
                    'tooltip' => 'Latest daily aggregate',
                ]);
                ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <?php
                $burn = $latest['burned_funds'] ?? null;
                $value2 = 'N/A';
                if ($burn) {
                    if ($currencyCookie === 'sc') {
                        $value2 = Locale::decimal(($burn['sc'] ?? 0)/1e24, 2) . ' SC';
                    } elseif ($currencyCookie === 'usd') {
                        $value2 = 'USD ' . Locale::decimal(($burn['usd'] ?? 0), 2);
                    } else {
                        $value2 = 'EUR ' . Locale::decimal(($burn['eur'] ?? 0), 2);
                    }
                }
                render_stat_card([
                    'icon' => 'bi bi-fire',
                    'label' => 'Burned Funds',
                    'value' => $value2,
                    'context' => $asOfText,
                    'tooltip' => 'Latest daily aggregate',
                ]);
                ?>
            </div>
        </div>
    </div>

    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Contract Revenue &amp; Burned Funds</h2>
                <div class="card__content">
                <section class="graph-container">
                    <?php
                    $revenueDataset = $graphConfigs['contract_revenue'];
                    $revenueDataset['fiatUnit'] = strtoupper($currencyCookie);
                    $burnedDataset = $graphConfigs['burned_funds'];
                    // Align burned funds unit label with selected currency
                    $burnedDataset['fiatUnit'] = strtoupper($currencyCookie);
                    renderGraph(
                        'revenue-burned',
                        [
                            $revenueDataset,
                            $burnedDataset
                        ],
                        'date',
                        '/api/v1/monthly/aggregates',
                        null,
                        'bar',
                        'month',
                        true,
                        'true',
                        12,
                        'false',
                        $currencyCookie
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
                <a href="token_volume" class="card">
                    <div class="card__icon"><i class="bi bi-bar-chart"></i></div>
                    <div class="card__heading">Token volume</div>
                    <div class="card__content">Network volume information.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="tokenomics" class="card">
                    <div class="card__icon"><i class="bi bi-currency-dollar"></i></div>
                    <div class="card__heading">Tokenomics</div>
                    <div class="card__content">Supply and economics overview.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="contracts_funds" class="card">
                    <div class="card__icon"><i class="bi bi-file-earmark-text"></i></div>
                    <div class="card__heading">Contracts & collateral</div>
                    <div class="card__content">Contract collateral statistics.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
                <a href="contract_activity" class="card">
                    <div class="card__icon"><i class="bi bi-clock-history"></i></div>
                    <div class="card__heading">Contract activity</div>
                    <div class="card__content">Active contracts per block.</div>
                    <div class="card__footer"><div class="card__view"></div></div>
                </a>
            </div>
        </div>
    </div>
    -->
</section>
<?php render_footer(); ?>
