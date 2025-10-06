<?php
require_once 'bootstrap.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';
require_once 'include/layout.php';
require_once 'include/components/stat_card.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;

$aggData = ApiClient::fetchJson('/api/v1/daily/aggregates');
$dataError = !is_array($aggData);
$latestAgg = $dataError ? [] : end($aggData);
$asOf = !$dataError && isset($latestAgg['date']) ? $latestAgg['date'] : null;

$tokenomicsPath = '/opt/siagraph/rawdata/tokenomics.json';
$tokenomicsData = file_exists($tokenomicsPath) ? json_decode(file_get_contents($tokenomicsPath), true) : [];
$latestTokenomics = end($tokenomicsData);
$currentBlockReward = $latestTokenomics['block_reward'] ?? null;
?>
<?php render_header('SiaGraph - Mining Stats'); ?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-cpu me-2"></i>Mining Statistics</h1>
    <?php if ($dataError): ?>
        <p class="text-center text-muted">Mining data unavailable.</p>
    <?php endif; ?>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-speedometer2',
                'label' => 'Average Difficulty',
                'value' => isset($latestAgg['avg_difficulty']) ? Locale::integer($latestAgg['avg_difficulty']) : 'N/A',
                'context' => $asOf ? ('Daily value as of ' . $asOf) : 'Daily value',
            ]);
            ?>
            </div>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-hash',
                'label' => 'Blocks Mined',
                'value' => isset($latestAgg['blocks_mined']) ? Locale::integer($latestAgg['blocks_mined']) : 'N/A',
                'context' => $asOf ? ('Daily value as of ' . $asOf) : 'Daily value',
            ]);
            ?>
            </div>
        <?php if ($currentBlockReward !== null): ?>
            <div class="sg-container__column sg-container__column--one-fourth">
            <?php
            render_stat_card([
                'icon' => 'bi bi-coin',
                'label' => 'Block Reward',
                'value' => Locale::decimal($currentBlockReward, 0) . ' SC',
            ]);
            ?>
            </div>
        <?php endif; ?>
        </div>
    </div>
    <div class="sg-container__row">
        <div class="sg-container__row-content">
        <div class="sg-container__column">
            <section class="card">
                <h2 class="card__heading">Average Difficulty</h2>
                <?php
                renderGraph(
                    'mining-avg-difficulty',
                    [
                        $graphConfigs['avg_difficulty']
                    ],
                    'date',
                    '/api/v1/daily/aggregates',
                    null,
                    'line',
                    'week',
                    true,
                    'true',
                    12,
                    'true',
                    'difficulty',
                    null,
                    500,
                    'Average Difficulty'
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
                <h2 class="card__heading">Blocks Mined</h2>
                <?php
                renderGraph(
                    'mining-blocks-mined',
                    [
                        $graphConfigs['blocks_mined']
                    ],
                    'date',
                    '/api/v1/daily/aggregates',
                    null,
                    'line',
                    'week',
                    true,
                    'true',
                    12,
                    'false',
                    null
                );
                ?>
            </section>
        </div>
        </div>
    </div>
</section>
<div id="mining-data" data-tokenomics='<?php echo json_encode($tokenomicsData); ?>'></div>
<?php render_footer(['js/mining-stats.js']); ?>
