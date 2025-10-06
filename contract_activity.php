<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';
require_once 'include/components/stat_card.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;

$months = 12; // default visible range; slider covers all data
$aggEndpoint = '/api/v1/monthly/aggregates';
$latestData = ApiClient::fetchJson($aggEndpoint);
$dataError = !is_array($latestData);
$latest = $dataError ? [] : end($latestData);
$asOf = !$dataError && isset($latest['date']) ? $latest['date'] : null;
$asOfText = $asOf ? ('Monthly total as of ' . \Siagraph\Utils\Locale::date($asOf)) : 'Monthly total';
?>
<?php render_header("SiaGraph - Contract Activity"); ?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-clock-history me-2"></i>Contract Activity</h1>
    <!-- range dropdown removed -->
    <?php if ($dataError): ?>
        <p class="text-center text-muted">Contract data unavailable.</p>
    <?php endif; ?>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fifth">
                <?php
                render_stat_card([
                    'icon' => 'bi bi-file-earmark-text',
                    'label' => 'Contracts Formed',
                    'value' => isset($latest['contracts_formed']) ? Locale::integer($latest['contracts_formed']) : 'N/A',
                    'compact' => true,
                    'context' => $asOfText,
                ]);
                ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fifth">
                <?php
                render_stat_card([
                    'icon' => 'bi bi-arrow-repeat',
                    'label' => 'Renewed',
                    'value' => isset($latest['renewed_contracts']) ? Locale::integer($latest['renewed_contracts']) : 'N/A',
                    'compact' => true,
                    'context' => $asOfText,
                ]);
                ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fifth">
                <?php
                render_stat_card([
                    'icon' => 'bi bi-x-circle',
                    'label' => 'Failed',
                    'value' => isset($latest['failed_contracts']) ? Locale::integer($latest['failed_contracts']) : 'N/A',
                    'compact' => true,
                    'context' => $asOfText,
                ]);
                ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fifth">
                <?php
                render_stat_card([
                    'icon' => 'bi bi-check-circle',
                    'label' => 'Successful',
                    'value' => isset($latest['successful_contracts']) ? Locale::integer($latest['successful_contracts']) : 'N/A',
                    'compact' => true,
                    'context' => $asOfText,
                ]);
                ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fifth">
                <?php
                render_stat_card([
                    'icon' => 'bi bi-people-fill',
                    'label' => 'Unique Renters',
                    'value' => isset($latest['unique_contract_renters']) ? Locale::integer($latest['unique_contract_renters']) : 'N/A',
                    'compact' => true,
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
                    <h2 class="card__heading">Contracts Formed</h2>
                    <div class="card__content">
                <?php
                renderGraph(
                    'aggregates-contracts-formed',
                    [
                        $graphConfigs['contracts_formed']
                    ],
                    'date',
                    $aggEndpoint,
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
                    </div>
                </section>
            </div>
        </div>
    </div>
    <div class="sg-container__row">
        <div class="sg-container__row-content">
            <div class="sg-container__column">
                <section class="card">
                    <h2 class="card__heading">Renewed Contracts</h2>
                    <div class="card__content">
                <?php
                renderGraph(
                    'aggregates-renewed-contracts',
                    [
                        $graphConfigs['renewed_contracts']
                    ],
                    'date',
                    $aggEndpoint,
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
                    </div>
                </section>
            </div>
        </div>
    </div>
    <div class="sg-container__row mt-4">
        <div class="sg-container__row-content">
            <div class="sg-container__column">
                <section class="card">
                    <h2 class="card__heading">Failed Contracts</h2>
                    <div class="card__content">
                <?php
                renderGraph(
                    'aggregates-failed-contracts',
                    [
                        $graphConfigs['failed_contracts']
                    ],
                    'date',
                    $aggEndpoint,
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
                    </div>
                </section>
            </div>
        </div>
    </div>
    <div class="sg-container__row mt-4">
        <div class="sg-container__row-content">
            <div class="sg-container__column">
                <section class="card">
                    <h2 class="card__heading">Successful Contracts</h2>
                    <div class="card__content">
                <?php
                renderGraph(
                    'aggregates-successful-contracts',
                    [
                        $graphConfigs['successful_contracts']
                    ],
                    'date',
                    $aggEndpoint,
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
                    </div>
                </section>
            </div>
        </div>
    </div>
    <div class="sg-container__row mt-4">
        <div class="sg-container__row-content">
            <div class="sg-container__column">
                <section class="card">
                    <h2 class="card__heading">Unique Renter Identities</h2>
                    <div class="card__content">
                <?php
                renderGraph(
                    'aggregates-unique-renters',
                    [
                        $graphConfigs['unique_contract_renters']
                    ],
                    'date',
                    $aggEndpoint,
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
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>
<?php render_footer(); ?>
