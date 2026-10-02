<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
include_once dirname(__DIR__) . '/include/graph.php';
$graphConfigs = require dirname(__DIR__) . '/include/graph_configs.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Locale;
use Siagraph\Utils\CurrencyDisplay;

$aggData = ApiClient::fetchJson('/api/v1/daily/aggregates');
$currencyCookie = CurrencyDisplay::selectedCurrency();
$dataError = !is_array($aggData);
$latestAgg = $dataError ? [] : end($aggData);
$asOf = !$dataError && isset($latestAgg['date']) ? $latestAgg['date'] : null;
$asOfText = $asOf ? ('Daily value as of ' . Locale::date($asOf)) : 'Daily value';

$tokenomicsPath = '/opt/siagraph/rawdata/tokenomics.json';
$tokenomicsData = file_exists($tokenomicsPath) ? json_decode(file_get_contents($tokenomicsPath), true) : [];
$tokenomicsData = is_array($tokenomicsData) ? $tokenomicsData : [];
$latestTokenomics = !empty($tokenomicsData) ? end($tokenomicsData) : [];
$currentBlockReward = $latestTokenomics['block_reward'] ?? null;
$difficultyValue = isset($latestAgg['avg_difficulty']) ? Locale::integer($latestAgg['avg_difficulty']) : 'N/A';
$blocksMinedValue = isset($latestAgg['blocks_mined']) ? Locale::integer($latestAgg['blocks_mined']) : 'N/A';
$blockRewardValue = $currentBlockReward !== null ? CurrencyDisplay::formatMonetary([
    'scValue' => (float) $currentBlockReward,
    'currency' => $currencyCookie,
    'decimals' => 0,
    'scDecimals' => 0,
]) : 'N/A';

render_header('SiaGraph - Mining Stats', 'SiaGraph - Mining Stats', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/mining-stats.css'), ENT_QUOTES, 'UTF-8') . '">'
]);
?>
<section id="main-content" class="sg-container mining-stats-page">
    <section class="card mining-stats-hero">
        <div>
            <div class="mining-stats-hero__kicker">Consensus security</div>
            <h1 class="mining-stats-hero__title">Mining Statistics</h1>
            <p class="mining-stats-hero__copy">
                Track mining difficulty, observed block production, and the current block reward that secures the Sia network.
            </p>
            <?php if ($dataError): ?>
                <p class="mining-stats-hero__status">Mining data unavailable.</p>
            <?php endif; ?>
        </div>
    </section>

    <section class="mining-stats-stat-grid" aria-label="Mining summary">
        <article class="mining-stats-stat-card">
            <div class="mining-stats-stat-card__label"><i class="bi bi-speedometer2" aria-hidden="true"></i>Average Difficulty</div>
            <div class="mining-stats-stat-card__value"><?php echo htmlspecialchars($difficultyValue, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="mining-stats-stat-card__context"><?php echo htmlspecialchars($asOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="mining-stats-stat-card">
            <div class="mining-stats-stat-card__label"><i class="bi bi-hash" aria-hidden="true"></i>Blocks Mined</div>
            <div class="mining-stats-stat-card__value"><?php echo htmlspecialchars($blocksMinedValue, ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="mining-stats-stat-card__context"><?php echo htmlspecialchars($asOfText, ENT_QUOTES, 'UTF-8'); ?></div>
        </article>
        <article class="mining-stats-stat-card">
            <div class="mining-stats-stat-card__label"><i class="bi bi-coin" aria-hidden="true"></i>Block Reward</div>
            <div class="mining-stats-stat-card__value"><?php echo $blockRewardValue; ?></div>
            <div class="mining-stats-stat-card__context">Current emission per mined block</div>
        </article>
    </section>

    <section class="mining-stats-chart-grid">
        <section class="card mining-stats-chart-card mining-stats-chart-card--wide">
            <h2 class="card__heading">Average Difficulty</h2>
            <p class="mining-stats-section-copy">Daily network mining difficulty over time.</p>
            <div class="card__content">
                <section class="graph-container">
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
                        'false',
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
        </section>

        <section class="card mining-stats-chart-card">
            <h2 class="card__heading">Blocks Mined</h2>
            <p class="mining-stats-section-copy">Observed blocks mined per daily aggregate period.</p>
            <div class="card__content">
                <section class="graph-container">
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
                        'false',
                        12,
                        'false',
                        null
                    );
                    ?>
                </section>
            </div>
        </section>

        <section class="card mining-stats-chart-card">
            <h2 class="card__heading">Cumulative Block Rewards</h2>
            <p class="mining-stats-section-copy">Historical cumulative mining reward from tokenomics snapshots.</p>
            <div class="card__content">
                <?php if (!empty($tokenomicsData)): ?>
                    <div class="mining-stats-canvas-wrap">
                        <canvas id="blockRewardChart"></canvas>
                    </div>
                <?php else: ?>
                    <p class="mining-stats-empty">Tokenomics snapshots unavailable.</p>
                <?php endif; ?>
            </div>
        </section>
    </section>
</section>
<div id="mining-data" data-tokenomics='<?php echo htmlspecialchars(json_encode($tokenomicsData, JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_TAG | JSON_HEX_QUOT), ENT_QUOTES, 'UTF-8'); ?>'></div>
<?php render_footer(['js/mining-stats.js']); ?>
