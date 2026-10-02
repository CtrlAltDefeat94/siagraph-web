<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/include/layout.php';
require_once __DIR__ . '/include/graph.php';

use Siagraph\Utils\ApiClient;
use Siagraph\Utils\Cache;
use Siagraph\Utils\CurrencyDisplay;
use Siagraph\Utils\Formatter;
use Siagraph\Utils\Locale;

$graphConfigs = require __DIR__ . '/include/graph_configs.php';

const HOME_EXPLORER_TIP_TTL = 30;
const HOME_EXPLORER_TXPOOL_TTL = 30;
const HOME_EXPLORER_BLOCK_TTL = 300;
const HOME_EXPLORER_DASHBOARD_TTL = 30;

function fetch_explorer_json_for_home(string $url, int $timeoutMs = 700): ?array
{
    if (!function_exists('curl_init')) {
        return null;
    }
    $ch = curl_init($url);
    if (!$ch) {
        return null;
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT_MS => $timeoutMs,
        CURLOPT_TIMEOUT_MS => $timeoutMs,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ]);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($response === false || $status < 200 || $status >= 300) {
        return null;
    }
    $decoded = json_decode((string) $response, true, 512, JSON_BIGINT_AS_STRING);
    return is_array($decoded) ? $decoded : null;
}

function cached_explorer_json_for_home(string $path, int $ttlSeconds): ?array
{
    global $SETTINGS;

    $base = rtrim((string) ($SETTINGS['explorer'] ?? ''), '/');
    if ($base === '') {
        return null;
    }

    $url = $base . $path;
    $cacheKey = 'home_explorer:' . md5($url);
    $cached = Cache::getCache($cacheKey);
    if (is_string($cached) && $cached !== '') {
        $decoded = json_decode($cached, true, 512, JSON_BIGINT_AS_STRING);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $fetched = fetch_explorer_json_for_home($url);
    if (is_array($fetched)) {
        Cache::setCacheSeconds(json_encode($fetched), $cacheKey, $ttlSeconds);
        return $fetched;
    }

    return null;
}

function home_explorer_seed(): array
{
    $dashboardCacheKey = 'home_explorer:dashboard';
    $cachedDashboard = Cache::getCache($dashboardCacheKey);
    if (is_string($cachedDashboard) && $cachedDashboard !== '') {
        $decoded = json_decode($cachedDashboard, true, 512, JSON_BIGINT_AS_STRING);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    $tip = cached_explorer_json_for_home('/consensus/tip', HOME_EXPLORER_TIP_TTL);
    $txpool = cached_explorer_json_for_home('/txpool/transactions', HOME_EXPLORER_TXPOOL_TTL);
    $blocks = [];

    $current = is_array($tip) ? (string) ($tip['id'] ?? '') : '';
    for ($i = 0; $i < 10 && $current !== ''; $i++) {
        $block = cached_explorer_json_for_home('/blocks/' . rawurlencode($current), HOME_EXPLORER_BLOCK_TTL);
        if (!is_array($block)) {
            break;
        }
        $blocks[] = $block;
        $current = (string) ($block['parentID'] ?? '');
    }

    $payload = [
        'tip' => $tip,
        'txpool' => $txpool,
        'blocks' => $blocks,
        'cached_at' => gmdate('c'),
    ];
    if ($tip !== null || $txpool !== null || !empty($blocks)) {
        Cache::setCacheSeconds(json_encode($payload), $dashboardCacheKey, HOME_EXPLORER_DASHBOARD_TTL);
    }

    return $payload;
}

$recentstats = ApiClient::fetchJson('/api/v1/daily/compare_metrics', true, 'hour') ?? [];

$athData = ApiClient::fetchJson('/api/v1/storage/ath', true, 'day');
$athPayload = (is_array($athData) && isset($athData['utilized_storage']) && is_array($athData['utilized_storage']))
    ? $athData['utilized_storage']
    : [];
$daysSinceAth = isset($athPayload['days_since_ath']) && $athPayload['days_since_ath'] !== null
    ? (string) $athPayload['days_since_ath']
    : 'N/A';

$currencyCookie = CurrencyDisplay::selectedCurrency();
$dailyChartStart = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
    ->modify('-4 months')
    ->format('Y-m-d');
$monthlyChartStart = (new DateTimeImmutable('first day of this month', new DateTimeZone('UTC')))
    ->modify('-7 months')
    ->format('Y-m-d');
$growthEndpoint = '/api/v1/daily/growth?start=' . rawurlencode($dailyChartStart);
$monthlyAggregatesEndpoint = '/api/v1/monthly/aggregates?start=' . rawurlencode($monthlyChartStart);

function value_at(array $arr = null, array $path = [], $default = null)
{
    $cur = $arr;
    foreach ($path as $k) {
        if (!is_array($cur) || !array_key_exists($k, $cur)) return $default;
        $cur = $cur[$k];
    }
    return $cur;
}
function ensure_plus_prefix(string $value): string
{
    $trimmed = trim($value);
    if ($trimmed === '' || stripos($trimmed, 'n/a') === 0) {
        return $value;
    }
    if ($trimmed[0] === '+' || $trimmed[0] === '-') {
        return $value;
    }
    return '+' . $value;
}

$utilizedStorage = !empty($recentstats) ? Formatter::formatBytes(value_at($recentstats, ['actual', 'utilized_storage'], 0)) : 'N/A';
$utilizedStorageChange = !empty($recentstats)
    ? Formatter::prependPlusIfNeeded(Formatter::formatBytes(value_at($recentstats, ['change', 'utilized_storage'], 0)))
    : 'N/A';
$networkCapacity = !empty($recentstats) ? Formatter::formatBytes(value_at($recentstats, ['actual', 'total_storage'], 0)) : 'N/A';
$networkCapacityChange = !empty($recentstats)
    ? Formatter::prependPlusIfNeeded(Formatter::formatBytes(value_at($recentstats, ['change', 'total_storage'], 0)))
    : 'N/A';
$onlineHosts = !empty($recentstats) ? Locale::integer(value_at($recentstats, ['actual', 'online_hosts'], 0)) : 'N/A';
$onlineHostsChange = !empty($recentstats)
    ? ensure_plus_prefix(Locale::signedDecimal(value_at($recentstats, ['change', 'online_hosts'], 0), 0))
    : 'N/A';
$activeContracts = !empty($recentstats) ? Locale::integer(value_at($recentstats, ['actual', 'active_contracts'], 0)) : 'N/A';
$activeContractsChange = !empty($recentstats)
    ? ensure_plus_prefix(Locale::signedDecimal(value_at($recentstats, ['change', 'active_contracts'], 0), 0))
    : 'N/A';

$revenueActualSc = !empty($recentstats) && value_at($recentstats, ['actual', '30_day_revenue', 'sc']) !== null
    ? ((float) value_at($recentstats, ['actual', '30_day_revenue', 'sc']) / 1e24)
    : null;
$revenueActualFiat = !empty($recentstats) && value_at($recentstats, ['actual', '30_day_revenue', $currencyCookie]) !== null
    ? (float) value_at($recentstats, ['actual', '30_day_revenue', $currencyCookie])
    : null;
$revenueChangeSc = !empty($recentstats) && value_at($recentstats, ['change', '30_day_revenue', 'sc']) !== null
    ? ((float) value_at($recentstats, ['change', '30_day_revenue', 'sc']) / 1e24)
    : null;
$revenueChangeFiat = !empty($recentstats) && value_at($recentstats, ['change', '30_day_revenue', $currencyCookie]) !== null
    ? (float) value_at($recentstats, ['change', '30_day_revenue', $currencyCookie])
    : null;
$revenueActualText = CurrencyDisplay::formatMonetary([
    'scValue' => $revenueActualSc,
    'fiatValue' => $revenueActualFiat,
    'currency' => $currencyCookie,
    'decimals' => 2,
    'scDecimals' => 2,
]);
$revenueChangeText = CurrencyDisplay::formatMonetary([
    'scValue' => $revenueChangeSc,
    'fiatValue' => $revenueChangeFiat,
    'currency' => $currencyCookie,
    'decimals' => 2,
    'scDecimals' => 2,
]);
$revenueActualPlain = trim(strip_tags((string) $revenueActualText));
$revenueChangePlain = ensure_plus_prefix(trim(strip_tags((string) $revenueChangeText)));
$utilizedStorageRaw = (float) value_at($recentstats, ['actual', 'utilized_storage'], 0);
$networkCapacityRaw = (float) value_at($recentstats, ['actual', 'total_storage'], 0);
$utilizationPercent = ($networkCapacityRaw > 0)
    ? Locale::decimal(($utilizedStorageRaw / $networkCapacityRaw) * 100, 1) . '%'
    : 'N/A';

$explorerDashboardSeed = home_explorer_seed();

function home_relative_time_short(?string $timestamp): string
{
    if (!$timestamp) {
        return 'Loading…';
    }
    $ts = strtotime($timestamp);
    if ($ts === false) {
        return 'Loading…';
    }
    $seconds = max(0, time() - $ts);
    if ($seconds < 60) {
        return $seconds . 's';
    }
    if ($seconds < 3600) {
        return floor($seconds / 60) . 'm';
    }
    if ($seconds < 86400) {
        return floor($seconds / 3600) . 'h';
    }
    return floor($seconds / 86400) . 'd';
}

function home_local_block_time(?string $timestamp): string
{
    if (!$timestamp) {
        return '';
    }
    $ts = strtotime($timestamp);
    return $ts === false ? '' : gmdate('M j, H:i', $ts);
}

function home_duration_short(float $seconds): string
{
    if ($seconds <= 0) {
        return 'Loading...';
    }
    if ($seconds < 60) {
        return round($seconds) . 's';
    }
    $minutes = floor($seconds / 60);
    $remaining = round(fmod($seconds, 60));
    return $remaining > 0 ? $minutes . 'm ' . $remaining . 's' : $minutes . 'm';
}

function home_block_tx_count(array $block): int
{
    $v1 = isset($block['transactions']) && is_array($block['transactions']) ? count($block['transactions']) : 0;
    $v2 = isset($block['v2']['transactions']) && is_array($block['v2']['transactions']) ? count($block['v2']['transactions']) : 0;
    return $v1 + $v2;
}

$explorerTip = is_array($explorerDashboardSeed['tip'] ?? null) ? $explorerDashboardSeed['tip'] : [];
$explorerTxpool = is_array($explorerDashboardSeed['txpool'] ?? null) ? $explorerDashboardSeed['txpool'] : [];
$explorerBlocks = is_array($explorerDashboardSeed['blocks'] ?? null) ? $explorerDashboardSeed['blocks'] : [];
$explorerTipHeight = isset($explorerTip['height']) ? Locale::integer((int) $explorerTip['height']) : 'Loading…';
$explorerTipTimestamp = (string) (($explorerBlocks[0]['timestamp'] ?? null) ?: ($explorerTip['timestamp'] ?? ''));
$explorerLastBlockAge = home_relative_time_short($explorerTipTimestamp);
$explorerPoolV1 = isset($explorerTxpool['transactions']) && is_array($explorerTxpool['transactions']) ? count($explorerTxpool['transactions']) : 0;
$explorerPoolV2 = isset($explorerTxpool['v2transactions']) && is_array($explorerTxpool['v2transactions']) ? count($explorerTxpool['v2transactions']) : 0;
$explorerTxpoolTotal = $explorerPoolV1 + $explorerPoolV2;
$explorerTxCount = 0;
$explorerBlockDeltas = [];
foreach ($explorerBlocks as $i => $block) {
    if (!is_array($block)) {
        continue;
    }
    $explorerTxCount += home_block_tx_count($block);
    if ($i < count($explorerBlocks) - 1 && is_array($explorerBlocks[$i + 1] ?? null)) {
        $t1 = strtotime((string) ($block['timestamp'] ?? ''));
        $t2 = strtotime((string) ($explorerBlocks[$i + 1]['timestamp'] ?? ''));
        if ($t1 !== false && $t2 !== false && $t1 > $t2) {
            $explorerBlockDeltas[] = $t1 - $t2;
        }
    }
}
$explorerAvgTxPerBlock = count($explorerBlocks) > 0 ? (int) round($explorerTxCount / count($explorerBlocks)) : null;
$explorerAvgBlockTime = !empty($explorerBlockDeltas)
    ? home_duration_short(array_sum($explorerBlockDeltas) / count($explorerBlockDeltas))
    : 'Loading...';

render_header('SiaGraph - Sia Storage Network Dashboard', 'SiaGraph storage network and explorer dashboard', []);
?>
<section id="main-content" class="sg-container sg-container--compact-top sg-container--compact-bottom">
    <div class="sg-container__row">
        <div class="sg-container__row-content">
            <div class="sg-container__column sg-container__column--full">
                <div class="exp-page" id="explorer-dashboard">
                    <section class="exp-card exp-hero">
                        <div class="exp-hero-grid">
                            <div>
                                <div class="exp-top-summaries">
                                    <section class="exp-summary exp-summary--storage">
                                        <div class="exp-summary-head">
                                            <h2 id="exp-summary-range-title" class="exp-summary-title">Last 24h at a glance</h2>
                                            <div class="exp-range-tabs" role="group" aria-label="Metric range">
                                                <button type="button" class="exp-range-tab is-active" data-range="24h">24h</button>
                                                <button type="button" class="exp-range-tab" data-range="7d">Week</button>
                                                <button type="button" class="exp-range-tab" data-range="30d">Month</button>
                                                <button type="button" class="exp-range-tab" data-range="90d">3M</button>
                                                <button type="button" class="exp-range-tab" data-range="365d">Year</button>
                                            </div>
                                        </div>
                                        <div class="exp-summary-grid">
                                            <article class="exp-summary-item">
                                                <p class="exp-stat-label">Utilized Storage</p>
                                                <p id="exp-kpi-utilized-storage" class="exp-stat-value"><?php echo htmlspecialchars($utilizedStorage, ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p id="exp-kpi-utilized-storage-change" class="exp-stat-meta"><?php echo htmlspecialchars($utilizedStorageChange, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </article>
                                            <article class="exp-summary-item">
                                                <p class="exp-stat-label">Network Capacity</p>
                                                <p id="exp-kpi-network-capacity" class="exp-stat-value"><?php echo htmlspecialchars($networkCapacity, ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p id="exp-kpi-network-capacity-change" class="exp-stat-meta"><?php echo htmlspecialchars($networkCapacityChange, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </article>
                                            <article class="exp-summary-item">
                                                <p class="exp-stat-label">Active storage contracts</p>
                                                <p id="exp-kpi-active-contracts" class="exp-stat-value"><?php echo htmlspecialchars($activeContracts, ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p id="exp-kpi-active-contracts-change" class="exp-stat-meta"><?php echo htmlspecialchars($activeContractsChange, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </article>
                                            <article class="exp-summary-item">
                                                <p class="exp-stat-label">Hosts currently online</p>
                                                <p id="exp-kpi-online-hosts" class="exp-stat-value"><?php echo htmlspecialchars($onlineHosts, ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p id="exp-kpi-online-hosts-change" class="exp-stat-meta"><?php echo htmlspecialchars($onlineHostsChange, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </article>
                                            <article class="exp-summary-item">
                                                <p class="exp-stat-label">30-day Network Revenue</p>
                                                <p id="exp-kpi-network-revenue" class="exp-stat-value"><?php echo htmlspecialchars($revenueActualPlain, ENT_QUOTES, 'UTF-8'); ?></p>
                                                <p id="exp-kpi-network-revenue-change" class="exp-stat-meta"><?php echo htmlspecialchars($revenueChangePlain, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </article>
                                            <article class="exp-summary-item">
                                                <p class="exp-stat-label">Average Block Time</p>
                                                <p id="exp-avg-block-time" class="exp-stat-value"><?php echo htmlspecialchars($explorerAvgBlockTime, ENT_QUOTES, 'UTF-8'); ?></p>
                                            </article>
                                        </div>
                                    </section>

                                    <section class="exp-summary exp-summary--chain">
                                        <h2 class="exp-summary-title">Blockchain Explorer</h2>
                                        <div class="exp-chain-layout">
                                            <div class="exp-chain-head">
                                                <div>
                                                    <p class="exp-stat-label">Current Height</p>
                                                    <p class="exp-stat-value exp-tip-value" id="exp-kpi-block-height"><?php echo htmlspecialchars($explorerTipHeight, ENT_QUOTES, 'UTF-8'); ?></p>
                                                    <p class="exp-stat-meta">Time since last block: <span id="exp-kpi-last-block-time"><?php echo htmlspecialchars($explorerLastBlockAge, ENT_QUOTES, 'UTF-8'); ?></span></p>
                                                </div>
                                            </div>
                                            <div id="exp-telemetry-inline" class="exp-telemetry-list">
                                                <?php if (!empty($explorerTxpool) || $explorerAvgTxPerBlock !== null): ?>
                                                    <div class="exp-telemetry-item">
                                                        <span class="exp-telemetry-key">Pending Transactions</span>
                                                        <a class="exp-telemetry-val exp-telemetry-link" href="/txpool"><?php echo htmlspecialchars(Locale::integer($explorerTxpoolTotal), ENT_QUOTES, 'UTF-8'); ?></a>
                                                    </div>
                                                    <div class="exp-telemetry-item">
                                                        <span class="exp-telemetry-key">Avg Tx / Block</span>
                                                        <span class="exp-telemetry-val"><?php echo htmlspecialchars($explorerAvgTxPerBlock !== null ? Locale::integer($explorerAvgTxPerBlock) : 'Loading…', ENT_QUOTES, 'UTF-8'); ?></span>
                                                    </div>
                                                <?php else: ?>
                                                <div class="exp-skeleton exp-skeleton-row"></div>
                                                <div class="exp-skeleton exp-skeleton-row"></div>
                                                <?php endif; ?>
                                            </div>
                                            <aside class="exp-recent-box">
                                                <h3 class="exp-recent-title">Recent Blocks</h3>
                                                <div id="exp-top-block-pulse" class="exp-top-block-pulse">
                                                    <?php if (count($explorerBlocks) > 1): ?>
                                                        <?php foreach (array_slice($explorerBlocks, 1, 6) as $block): ?>
                                                            <?php
                                                            if (!is_array($block)) {
                                                                continue;
                                                            }
                                                            $blockRef = trim((string) (($block['id'] ?? '') ?: ($block['height'] ?? '')));
                                                            if ($blockRef === '') {
                                                                continue;
                                                            }
                                                            $blockHeight = isset($block['height']) ? '#' . Locale::integer((int) $block['height']) : '#N/A';
                                                            $blockTime = home_local_block_time((string) ($block['timestamp'] ?? ''));
                                                            $blockTxCount = home_block_tx_count($block);
                                                            ?>
                                                            <a class="exp-top-block-chip" href="/block/<?php echo rawurlencode($blockRef); ?>">
                                                                <b><?php echo htmlspecialchars($blockHeight, ENT_QUOTES, 'UTF-8'); ?></b>
                                                                <span class="exp-block-time"><?php echo htmlspecialchars($blockTime, ENT_QUOTES, 'UTF-8'); ?></span>
                                                                <span class="exp-block-tx">TX <?php echo htmlspecialchars(Locale::integer($blockTxCount), ENT_QUOTES, 'UTF-8'); ?></span>
                                                            </a>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                    <div class="exp-skeleton exp-skeleton-row"></div>
                                                    <div class="exp-skeleton exp-skeleton-row"></div>
                                                    <div class="exp-skeleton exp-skeleton-row"></div>
                                                    <?php endif; ?>
                                                </div>
                                            </aside>
                                        </div>
                                        <p class="sg-exchange-context" data-sc-rate data-currency="<?php echo htmlspecialchars($currencyCookie, ENT_QUOTES, 'UTF-8'); ?>">
                                            Siacoin exchange rate · <span data-sc-value>Loading…</span>
                                            <a href="/siacoin_price">Price history →</a>
                                            <small data-sc-updated></small>
                                        </p>
                                    </section>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section class="exp-analytics-grid">
                        <article class="exp-card exp-card--immersive">
                            <div class="exp-section-head">
                                <h2>Utilized Storage</h2>
                                <a href="/network_storage">View more</a>
                            </div>
                            <div class="exp-chart-wrap">
                                <?php
                                renderGraph(
                                    'networkstorage',
                                    [array_merge($graphConfigs['utilized_storage'], ['startAtZero' => false])],
                                    'date', $growthEndpoint, null, 'line', 'week', false, false,
                                    3, 'false', 'bytes', null
                                );
                                ?>
                            </div>
                        </article>
                        <article class="exp-card exp-card--soft">
                            <div class="exp-section-head">
                                <h2>Host Count</h2>
                                <a href="/hosting">View more</a>
                            </div>
                            <div class="exp-chart-wrap">
                                <?php
                                renderGraph(
                                    'hostcount',
                                    [array_merge($graphConfigs['active_hosts'], ['startAtZero' => false])],
                                    'date', $growthEndpoint, null, 'line', 'week', false, false,
                                    3, 'false', 'none', null
                                );
                                ?>
                            </div>
                        </article>
                        <article class="exp-card exp-card--soft">
                            <div class="exp-section-head">
                                <h2>Monthly Revenue <i class="bi bi-info-circle text-sm" tabindex="0" data-bs-toggle="tooltip" data-bs-placement="top" aria-label="About monthly revenue" title="Earned revenue and anticipated revenue from unresolved contracts. Anticipated revenue is paid only if contracts succeed; unlock months are estimates."></i></h2>
                                <a href="/revenue">View more</a>
                            </div>
                            <p id="networkRevenueStatus" class="text-sm" role="status"></p>
                            <div class="exp-chart-wrap">
                                <div id="canvasContainer-monthlyrevenue">
                                    <canvas id="monthlyrevenue" class="sg-chart-canvas" style="height:500px !important; width:100% !important;"></canvas>
                                </div>
                                <script>
                                document.addEventListener('DOMContentLoaded', function () {
                                    window.graphInstances = window.graphInstances || {};
                                    window.graphInstances.monthlyrevenue = window.createNetworkRevenueChart({
                                        canvasId: 'monthlyrevenue',
                                        jsonUrl: <?php echo json_encode($monthlyAggregatesEndpoint); ?>,
                                        datasets: [
                                            <?php echo json_encode(array_replace($graphConfigs['contract_revenue'], ['label' => 'Earned revenue', 'stack' => 'rev'])); ?>,
                                            { label: 'Anticipated revenue', key: 'anticipated_revenue_sc',
                                              backgroundColor: 'rgba(251, 191, 36, 0.75)', borderColor: 'rgba(251, 191, 36, 1)',
                                              fiatUnit: '__CURRENCY__', scUnit: 'SC', fiatUnitDivisor: 1, scUnitDivisor: 1,
                                              decimalPlaces: 2, startAtZero: true, stack: 'rev' }
                                        ],
                                        dateKey: 'date', charttype: 'bar', interval: 'month', stacked: true,
                                        rangeslider: false, displaylegend: false, defaultrangeinmonths: 6,
                                        displayYAxis: false,
                                        unitType: <?php echo json_encode($currencyCookie); ?>,
                                        currency: <?php echo json_encode($currencyCookie); ?>,
                                        useFiat: <?php echo $currencyCookie === 'sc' ? 'false' : 'true'; ?>
                                    });
                                });
                                </script>
                            </div>
                        </article>
                    </section>

                </div>
            </div>
        </div>
    </div>
</section>

<style>
.exp-page {
  --exp-bg-1: var(--sg-control-bg-strong);
  --exp-bg-2: var(--sg-card-bg);
  --exp-surface: var(--sg-panel-bg);
  --exp-border: var(--sg-border-subtle);
  --exp-border-strong: var(--sg-border-accent);
  --exp-text: var(--sg-text);
  --exp-text-soft: var(--sg-text-muted);
  --exp-text-muted: var(--sg-text-faint);
  --exp-brand: var(--sg-heading-accent-strong);
  --exp-brand-strong: var(--sg-heading-accent);
  --exp-success: #8bc3a4;
  --exp-danger: #f2a0a7;
  position: relative;
  display: grid;
  gap: 0.75rem;
  padding-block: 0.08rem 0.32rem;
  color: var(--exp-text);
  background: transparent;
  border-radius: 0;
}

.exp-page::before {
  content: none;
}

.exp-card {
  border: 0;
  border-radius: 1.25rem;
  background-color: var(--sg-panel-bg);
  padding: 1.05rem;
  box-shadow: none;
}
.exp-card--soft {
  border-color: var(--sg-border-subtle);
  background-color: var(--sg-panel-bg);
  box-shadow: none;
}
.exp-card--immersive {
  border-color: var(--sg-border-subtle);
  background: var(--sg-panel-bg);
  box-shadow: none;
}
.exp-hero {
  padding: 0;
  background: transparent;
  border-radius: 0;
}
.exp-hero-grid { display: grid; grid-template-columns: 1fr; gap: 0.78rem; align-items: start; }
.exp-title { margin: 0.08rem 0 0.28rem; color: var(--sg-text-strong); font-size: clamp(1.28rem, 2.2vw, 1.72rem); line-height: 1.15; font-weight: 700; letter-spacing: 0; }
.exp-title { margin: 0.04rem 0 0.22rem; color: var(--sg-text-strong); font-size: clamp(1.12rem, 1.8vw, 1.42rem); line-height: 1.15; font-weight: 700; letter-spacing: 0; }
.exp-search {
  display: grid;
  grid-template-columns: minmax(320px, 980px) auto;
  justify-content: start;
  gap: 0.7rem;
}
.exp-search input { min-height: 2.55rem; padding: 0.52rem 0.78rem; border-radius: 0.72rem; border: 1px solid var(--exp-border-strong); background: var(--sg-control-bg-strong); color: var(--sg-text-strong); font-size: 0.9rem; }
.exp-search input { min-height: 2.35rem; padding: 0.44rem 0.72rem; font-size: 0.86rem; }
.exp-search input:focus { outline: 0; border-color: var(--exp-brand); box-shadow: 0 0 0 3px rgba(230, 106, 82, 0.24); }
.exp-search button { min-height: 2.55rem; border: 1px solid rgba(225, 120, 100, 0.62); border-radius: 0.72rem; padding-inline: 0.85rem; font-weight: 700; color: #fff3ef; background: linear-gradient(180deg, #c95c45, #a33d2f); box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.03) inset; font-size: 0.82rem; letter-spacing: 0.01em; }
.exp-search button { min-height: 2.35rem; padding-inline: 0.8rem; font-size: 0.8rem; }
.exp-search button:hover { background: linear-gradient(180deg, #d05d46, #b14535); }
.exp-search-help { margin: 0.52rem 0 0; color: var(--exp-text-muted); font-size: 0.75rem; }
.exp-search-status { margin: 0.16rem 0 0; min-height: 1rem; font-size: 0.74rem; color: var(--exp-text-muted); }
.exp-top-summaries {
  margin-top: 0.2rem;
  display: grid;
  grid-template-columns: 1fr 1fr;
  align-items: stretch;
  gap: 0.65rem;
  background: transparent;
  border: 0;
  border-radius: 0;
  overflow: visible;
}
.exp-summary {
  border: 0;
  border-radius: 1.05rem;
  background: var(--sg-panel-bg);
  padding: 1rem;
  height: 100%;
}
.exp-summary-head {
  display: grid;
  grid-template-columns: repeat(2, minmax(0, 1fr));
  align-items: start;
  gap: 1rem 1.3rem;
  margin-bottom: 0.6rem;
}
.exp-summary-head .exp-summary-title {
  grid-column: 1;
  min-width: 0;
}
.exp-summary-title { margin: 0; color: var(--sg-heading-accent); font-size: 1rem; font-weight: 700; letter-spacing: 0.01em; line-height: 1.7rem; }
.exp-range-tabs {
  display: inline-flex;
  align-items: center;
  gap: 0.18rem;
  padding: 0.16rem;
  border: 1px solid var(--sg-border-subtle);
  border-radius: 999px;
  background: rgba(20, 17, 17, 0.32);
  grid-column: 2;
  justify-self: start;
}
.exp-range-tab {
  min-height: 1.7rem;
  padding: 0.18rem 0.48rem;
  border: 0;
  border-radius: 999px;
  background: transparent;
  color: var(--sg-text-muted);
  font-size: 0.72rem;
  font-weight: 700;
  line-height: 1;
}
.exp-range-tab:hover,
.exp-range-tab:focus-visible {
  color: var(--sg-action-text-hover);
  outline: 0;
}
.exp-range-tab.is-active {
  color: var(--sg-text-strong);
  background: var(--sg-action-bg);
}
.exp-range-tab:disabled {
  cursor: wait;
  opacity: 0.72;
}
.exp-summary-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1rem 1.3rem; }
.exp-summary-item { border: 0; border-radius: 0.72rem; background: transparent; padding: 0.05rem 0; }
.exp-chain-layout {
  display: grid;
  grid-template-columns: minmax(0, 0.72fr) minmax(0, 1.28fr);
  grid-template-areas:
    "head recent"
    "telemetry recent";
  gap: 0.65rem 0.9rem;
  align-items: start;
}
.exp-chain-head {
  grid-area: head;
  margin-bottom: 0;
  padding: 0.18rem 0.08rem;
  border-radius: 0;
  background: transparent;
  border: 0;
}
.exp-tip-value { font-size: 3rem; line-height: 0.98; margin: 0 0 0.24rem; letter-spacing: 0.01em; }
.exp-chain-head .exp-stat-meta { font-size: 0.86rem; line-height: 1.24; }
.exp-telemetry-list { display: grid; gap: 0.16rem; margin-top: 0.12rem; grid-area: telemetry; }
.exp-telemetry-item {
  display: flex;
  justify-content: space-between;
  align-items: baseline;
  gap: 0.5rem;
  padding: 0.38rem 0.08rem;
  border-radius: 0;
  background: transparent;
  border: 0;
  border-bottom: 0;
}
.exp-telemetry-key { color: var(--sg-heading-accent); font-size: 0.68rem; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 600; }
.exp-telemetry-val { color: var(--sg-text); font-size: 0.88rem; font-weight: 650; }
.exp-telemetry-link { color: inherit; text-decoration: none; }
.exp-telemetry-link:hover { color: var(--sg-text-strong); text-decoration: underline; }
.exp-telemetry-val.exp-telemetry-link {
  color: var(--sg-action-text);
  text-decoration: underline;
  text-underline-offset: 2px;
}
.exp-telemetry-val.exp-telemetry-link:hover {
  color: var(--sg-action-text-hover);
}
.exp-recent-box {
  grid-area: recent;
  border-radius: 0.8rem;
  background: var(--sg-panel-bg-soft);
  padding: 0.62rem 0.72rem;
  align-self: start;
}
.exp-recent-title {
  margin: 0 0 0.52rem;
  color: var(--sg-heading-accent);
  font-size: 0.78rem;
  text-transform: uppercase;
  letter-spacing: 0.045em;
  font-weight: 700;
}
.exp-top-block-pulse { display: grid; gap: 0.34rem; }
.exp-top-block-chip {
  display: grid;
  grid-template-columns: auto 1fr auto;
  align-items: baseline;
  gap: 0.5rem;
  padding: 0.28rem 0;
  border: 0;
  background: transparent;
  color: #cdbfbb;
  font-size: 0.86rem;
  line-height: 1.2;
  text-decoration: none;
}
.exp-top-block-chip:hover { color: var(--sg-text-strong); text-decoration: underline; }
.exp-top-block-chip b { color: #fff4ef; font-weight: 700; }
.exp-top-block-chip .exp-block-time { color: #bcaea9; font-size: 0.8rem; }
.exp-top-block-chip .exp-block-tx { color: #dfd3cf; font-size: 0.82rem; font-weight: 600; }
.exp-section-head { display: flex; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.72rem; }
.exp-section-head h2 { margin: 0; color: var(--sg-heading-accent); font-size: 1.02rem; font-weight: 700; letter-spacing: 0.01em; text-transform: none; }
.exp-section-head a { color: var(--exp-brand); text-decoration: none; font-size: 0.8rem; }
.exp-section-head a:hover { color: #f19683; text-decoration: underline; }
.exp-stats-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 0.55rem; }
.exp-stat { border: 1px solid var(--exp-border); border-radius: 12px; background: var(--exp-surface); padding: 0.75rem; transition: border-color 130ms ease, background-color 130ms ease; }
.exp-stat:hover { border-color: var(--exp-border-strong); background: rgba(42, 33, 33, 0.9); }
.exp-kpi-card {
  background-color: hsla(0,0%,100%,.05);
  border-color: rgba(255, 255, 255, 0.1);
  border-radius: 0.95rem;
  padding: 0.56rem 0.6rem;
}
.exp-kpi-card--primary {
  padding: 0.78rem 0.82rem;
  background:
    radial-gradient(100% 120% at 10% 0%, rgba(234, 98, 71, 0.16), transparent 54%),
    linear-gradient(180deg, rgba(55, 34, 33, 0.86), rgba(35, 25, 24, 0.82));
  border-color: rgba(234, 98, 71, 0.34);
}
.exp-kpi-card--primary .exp-stat-value {
  font-size: 1.16rem;
}
.exp-stat-label {
  margin: 0;
  color: #ef7f68;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  font-size: 0.7rem;
  font-weight: 600;
}
.exp-stat-value {
  margin: 0.3rem 0 0.18rem;
  color: #fffaf8;
  font-size: 2.7rem;
  font-weight: 700;
  line-height: 0.98;
  word-break: break-word;
}
.exp-summary-grid .exp-summary-item .exp-stat-value {
  font-size: 2rem;
}
.exp-summary-grid .exp-summary-item .exp-stat-label {
  font-size: 0.76rem;
}
.exp-stat-meta { margin: 0; color: #cec0bc; font-size: 0.95rem; line-height: 1.34; font-weight: 500; }
.exp-analytics-grid { display: grid; grid-template-columns: 1.1fr 0.9fr 0.9fr; gap: 0.75rem; align-items: stretch; }
.exp-chart-wrap { min-height: 138px; border: 0; border-radius: 0.85rem; padding: 0.2rem; background: rgba(74, 55, 55, 0.24); }
.exp-card--immersive .exp-chart-wrap {
  min-height: 206px;
  background: rgba(74, 55, 55, 0.24);
}
.exp-chart-wrap .graph-container { min-height: 138px; }
.exp-card--immersive .exp-chart-wrap .graph-container { min-height: 206px; }
.exp-chart-wrap canvas { max-height: 320px; }
#networkRevenueStatus:empty { display: none; }
.exp-empty, .exp-error { border: 1px dashed var(--exp-border-strong); border-radius: 0.75rem; padding: 0.72rem; color: var(--exp-text-soft); font-size: 0.82rem; }
.exp-error { border-color: rgba(242, 160, 167, 0.52); background: rgba(95, 35, 48, 0.25); color: #f8d2d7; }
.exp-skeleton { border-radius: 10px; background: linear-gradient(90deg, rgba(140, 125, 125, 0.14), rgba(168, 150, 150, 0.24), rgba(140, 125, 125, 0.14)); background-size: 200% 100%; animation: expPulse 1.2s linear infinite; }
.exp-skeleton-card { height: 94px; }
.exp-skeleton-row { height: 42px; }
@keyframes expPulse { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
@media (max-width: 1200px) {
  .exp-top-summaries { grid-template-columns: 1fr; }
  .exp-chain-layout {
    grid-template-columns: 1fr;
    grid-template-areas:
      "head"
      "telemetry"
      "recent";
  }
  .exp-summary-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .exp-analytics-grid { grid-template-columns: 1fr; }
  .exp-stats-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (max-width: 900px) {
  .exp-summary-head { grid-template-columns: 1fr; align-items: flex-start; }
  .exp-summary-head .exp-summary-title,
  .exp-range-tabs { grid-column: 1; }
  .exp-range-tabs { max-width: 100%; overflow-x: auto; }
  .exp-summary-grid { grid-template-columns: 1fr; }
  .exp-analytics-grid { grid-template-columns: 1fr; }
  .exp-stat-value { font-size: 2.05rem; }
  .exp-summary-grid .exp-summary-item:not(.exp-summary-item--primary) .exp-stat-value { font-size: 1.48rem; }
  .exp-tip-value { font-size: 2.4rem; }
}
@media (max-width: 640px) {
  .exp-page { gap: 0.85rem; }
  .exp-card { padding: 0.88rem; border-radius: 1rem; }
  .exp-search { grid-template-columns: 1fr; }
  .exp-search input, .exp-search button { min-height: 2.8rem; }
  .exp-stats-grid { grid-template-columns: 1fr; }
}
</style>

<script>
window.EXPLORER_API_BASE = <?php echo json_encode(rtrim((string) ($SETTINGS['explorer'] ?? ''), '/'), JSON_UNESCAPED_SLASHES); ?>;
window.SG_SELECTED_CURRENCY = <?php echo json_encode($currencyCookie, JSON_UNESCAPED_SLASHES); ?>;
window.HOME_EXPLORER_SEED = <?php echo json_encode($explorerDashboardSeed, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
</script>
<script>
(() => {
  const API_BASE = String(window.EXPLORER_API_BASE || '').replace(/\/+$/, '');
  const SELECTED_CURRENCY = String(window.SG_SELECTED_CURRENCY || 'usd').toLowerCase();
  const EXPLORER_SEED = (window.HOME_EXPLORER_SEED && typeof window.HOME_EXPLORER_SEED === 'object') ? window.HOME_EXPLORER_SEED : {};
  const RANGE_METRICS_TTL_MS = 5 * 60 * 1000;
  const EXPLORER_TIP_TTL_MS = 30 * 1000;
  const EXPLORER_TXPOOL_TTL_MS = 30 * 1000;
  const EXPLORER_BLOCK_TTL_MS = 5 * 60 * 1000;
  const $ = (id) => document.getElementById(id);
  const els = {
    searchForm: $('exp-search-form'),
    searchInput: $('exp-search-input'),
    searchStatus: $('exp-search-status'),
    searchSubmit: $('exp-search-submit'),
    telemetry: $('exp-telemetry-inline'),
    topBlockPulse: $('exp-top-block-pulse'),
    kpiBlockHeight: $('exp-kpi-block-height'),
    kpiLastBlockTime: $('exp-kpi-last-block-time'),
    summaryRangeTitle: $('exp-summary-range-title'),
    rangeTabs: Array.from(document.querySelectorAll('.exp-range-tab')),
    utilizedStorage: $('exp-kpi-utilized-storage'),
    utilizedStorageChange: $('exp-kpi-utilized-storage-change'),
    networkCapacity: $('exp-kpi-network-capacity'),
    networkCapacityChange: $('exp-kpi-network-capacity-change'),
    activeContracts: $('exp-kpi-active-contracts'),
    activeContractsChange: $('exp-kpi-active-contracts-change'),
    onlineHosts: $('exp-kpi-online-hosts'),
    onlineHostsChange: $('exp-kpi-online-hosts-change'),
    networkRevenue: $('exp-kpi-network-revenue'),
    networkRevenueChange: $('exp-kpi-network-revenue-change'),
  };
  let tipTimestampMs = null;
  let tipTicker = null;

  function esc(v) {
    return String(v ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }
  async function fetchJsonCached(url, options = {}, ttl = 60000) {
    const version = (typeof window !== 'undefined' && window.FETCH_CACHE_VERSION) ? window.FETCH_CACHE_VERSION : 'v1';
    const cacheKey = `fetchCache:${version}:${url}`;
    try {
      const cached = localStorage.getItem(cacheKey);
      if (cached) {
        const { timestamp, data } = JSON.parse(cached);
        if (Date.now() - Number(timestamp) < ttl) {
          return data;
        }
      }
    } catch (_cacheReadErr) {}

    const res = await fetch(url, options);
    const data = await res.json();
    if (!res.ok) {
      throw new Error(data?.error || `Unexpected HTTP code: ${res.status}`);
    }
    try {
      localStorage.setItem(cacheKey, JSON.stringify({ timestamp: Date.now(), data }));
    } catch (_cacheWriteErr) {}
    return data;
  }
  function formatInt(v, fb = 'N/A') {
    const n = Number(v);
    return Number.isFinite(n) ? n.toLocaleString() : fb;
  }
  function plus(v) {
    const s = String(v ?? '').trim();
    return s && s !== 'N/A' && !/^[+-]/.test(s) ? `+${s}` : s;
  }
  function formatBytes(bytes, fb = 'N/A') {
    const n = Number(bytes);
    if (!Number.isFinite(n)) return fb;
    const sign = n < 0 ? '-' : '';
    const units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB'];
    let value = Math.abs(n), unitIndex = 0;
    while (value >= 1000 && unitIndex < units.length - 1) { value /= 1000; unitIndex++; }
    const decimals = unitIndex === 0 ? 0 : 3;
    return `${sign}${value.toLocaleString(undefined, { minimumFractionDigits: decimals, maximumFractionDigits: decimals })} ${units[unitIndex]}`;
  }
  function formatMoney(value, currency = SELECTED_CURRENCY, fb = 'N/A') {
    const n = Number(value);
    if (!Number.isFinite(n)) return fb;
    try {
      return new Intl.NumberFormat(undefined, {
        style: 'currency', currency: currency.toUpperCase(), currencyDisplay: 'code',
        minimumFractionDigits: 2, maximumFractionDigits: 2
      }).format(n).replace(/\u00a0/g, ' ');
    } catch (_err) {
      return `${currency.toUpperCase()} ${n.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
    }
  }
  function ymd(date) { return date.toISOString().slice(0, 10); }
  function compareDatesForRange(range) {
    const end = new Date();
    end.setUTCHours(0, 0, 0, 0);
    const start = new Date(end);
    const days = ({ '24h': 1, '7d': 7, '30d': 30, '90d': 90, '365d': 365 })[range] || 1;
    start.setUTCDate(start.getUTCDate() - days);
    return { actualDate: ymd(end), compareDate: ymd(start) };
  }
  function setRangeLoading(isLoading) {
    els.rangeTabs.forEach((button) => { button.disabled = isLoading; });
  }
  function setActiveRange(range) {
    els.rangeTabs.forEach((button) => { button.classList.toggle('is-active', button.dataset.range === range); });
    const labels = { '24h': '24h', '7d': 'week', '30d': 'month', '90d': '3 months', '365d': 'year' };
    if (els.summaryRangeTitle) els.summaryRangeTitle.textContent = `Last ${labels[range] || '24h'} at a glance`;
  }
  function renderRangeMetrics(data) {
    const actual = data?.actual || {};
    const change = data?.change || {};
    if (els.utilizedStorage) els.utilizedStorage.textContent = formatBytes(actual.utilized_storage);
    if (els.utilizedStorageChange) els.utilizedStorageChange.textContent = plus(formatBytes(change.utilized_storage));
    if (els.networkCapacity) els.networkCapacity.textContent = formatBytes(actual.total_storage);
    if (els.networkCapacityChange) els.networkCapacityChange.textContent = plus(formatBytes(change.total_storage));
    if (els.activeContracts) els.activeContracts.textContent = formatInt(actual.active_contracts);
    if (els.activeContractsChange) els.activeContractsChange.textContent = plus(formatInt(change.active_contracts));
    if (els.onlineHosts) els.onlineHosts.textContent = formatInt(actual.online_hosts);
    if (els.onlineHostsChange) els.onlineHostsChange.textContent = plus(formatInt(change.online_hosts));
    const revenue = actual['30_day_revenue'] || {};
    const revenueChange = change['30_day_revenue'] || {};
    if (els.networkRevenue) els.networkRevenue.textContent = formatMoney(revenue[SELECTED_CURRENCY]);
    if (els.networkRevenueChange) els.networkRevenueChange.textContent = plus(formatMoney(revenueChange[SELECTED_CURRENCY]));
  }
  async function loadRangeMetrics(range) {
    setActiveRange(range); setRangeLoading(true);
    const { actualDate, compareDate } = compareDatesForRange(range);
    try {
      const params = new URLSearchParams({ actual_date: actualDate, compare_date: compareDate });
      const data = await fetchJsonCached(`/api/v1/daily/compare_metrics?${params.toString()}`, { headers: { Accept: 'application/json' } }, RANGE_METRICS_TTL_MS);
      if (data?.error) throw new Error(data.error);
      renderRangeMetrics(data);
    } catch (_err) {
      if (els.utilizedStorageChange) els.utilizedStorageChange.textContent = 'Range unavailable';
    } finally { setRangeLoading(false); }
  }
  function relTime(ts) {
    const d = new Date(ts);
    if (Number.isNaN(d.getTime())) return 'Unknown';
    const s = Math.floor((Date.now() - d.getTime()) / 1000);
    if (s < 60) return `${s}s ago`;
    if (s < 3600) return `${Math.floor(s / 60)}m ago`;
    if (s < 86400) return `${Math.floor(s / 3600)}h ago`;
    return `${Math.floor(s / 86400)}d ago`;
  }
  function relTimeFromMs(ms) {
    const t = Number(ms);
    if (!Number.isFinite(t)) return 'Unknown';
    const s = Math.floor((Date.now() - t) / 1000);
    if (s < 60) return `${s}s`;
    if (s < 3600) return `${Math.floor(s / 60)}m`;
    if (s < 86400) return `${Math.floor(s / 3600)}h`;
    return `${Math.floor(s / 86400)}d`;
  }
  function startTipTicker() {
    if (tipTicker) clearInterval(tipTicker);
    tipTicker = setInterval(() => {
      if (els.kpiLastBlockTime) els.kpiLastBlockTime.textContent = relTimeFromMs(tipTimestampMs);
    }, 1000);
  }
  function formatLocalTimestamp(ts) {
    const d = new Date(ts);
    if (Number.isNaN(d.getTime())) return 'Unknown';
    return d.toLocaleString(undefined, {
      month: 'short',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit'
    });
  }
  function formatDuration(seconds) {
    const s = Number(seconds);
    if (!Number.isFinite(s) || s <= 0) return 'N/A';
    if (s < 60) return `${Math.round(s)}s`;
    const m = Math.floor(s / 60);
    const rem = Math.round(s % 60);
    return rem > 0 ? `${m}m ${rem}s` : `${m}m`;
  }
  function shortId(v, head = 14, tail = 10) {
    const s = String(v || '');
    if (!s) return 'N/A';
    if (s.length <= head + tail + 3) return esc(s);
    return `${esc(s.slice(0, head))}...${esc(s.slice(-tail))}`;
  }
  function status(msg, kind = 'idle') {
    if (!els.searchStatus) return;
    const colors = { idle: '#988d89', loading: '#e6a08f', error: '#f2a0a7', success: '#8bc3a4' };
    els.searchStatus.style.color = colors[kind] || colors.idle;
    els.searchStatus.textContent = msg;
  }
  async function getJson(path) {
    if (!API_BASE) throw new Error('Explorer API is not configured');
    const res = await fetch(API_BASE + path, { headers: { Accept: 'application/json' } });
    const txt = await res.text();
    let body = null;
    if (txt) { try { body = JSON.parse(txt); } catch { body = txt; } }
    if (!res.ok) throw new Error(`API ${res.status}`);
    return body;
  }
  async function getExplorerJson(path, ttl = EXPLORER_TIP_TTL_MS) {
    if (!API_BASE) throw new Error('Explorer API is not configured');
    return fetchJsonCached(API_BASE + path, { headers: { Accept: 'application/json' } }, ttl);
  }
  async function resolveSearch(value) {
    if (/^\d+$/.test(value)) {
      const idx = await getJson(`/consensus/tip/${encodeURIComponent(value)}`);
      if (idx?.id) return `/block/${encodeURIComponent(idx.id)}`;
    }
    const type = await getJson(`/search/${encodeURIComponent(value)}`);
    const routes = {
      block: `/block/${encodeURIComponent(value)}`,
      transaction: `/tx/${encodeURIComponent(value)}`,
      v2Transaction: `/tx/${encodeURIComponent(value)}`,
      address: `/address/${encodeURIComponent(value)}`,
      contract: `/contract/${encodeURIComponent(value)}`,
      v2Contract: `/contract/${encodeURIComponent(value)}`,
      siacoinElement: `/output/siacoin/${encodeURIComponent(value)}`,
      siafundElement: `/output/siafund/${encodeURIComponent(value)}`,
      host: `/host?public_key=${encodeURIComponent(value)}`,
    };
    return routes[type] || null;
  }
  function renderTelemetryCards({ txpoolTotal, avgTxPerBlock }) {
    const cards = [
      ['Pending Transactions', formatInt(txpoolTotal), '/txpool'],
      ['Avg Tx / Block', formatInt(avgTxPerBlock), null],
    ];
    return cards.map(([label, value, href]) => `
      <div class="exp-telemetry-item">
        <span class="exp-telemetry-key">${esc(label)}</span>
        ${href
          ? `<a class="exp-telemetry-val exp-telemetry-link" href="${esc(href)}">${esc(value)}</a>`
          : `<span class="exp-telemetry-val">${esc(value)}</span>`
        }
      </div>
    `).join('');
  }
  function renderTopBlockPulse(blocks) {
    const rows = Array.isArray(blocks) ? blocks.slice(1, 7) : [];
    if (!rows.length) return `<div class="exp-empty">Recent blocks unavailable.</div>`;
    return rows.map((b) => {
      const blockRef = String(b?.id ?? b?.height ?? '').trim();
      if (!blockRef) return '';
      const txCount = (Array.isArray(b?.transactions) ? b.transactions.length : 0) + (Array.isArray(b?.v2?.transactions) ? b.v2.transactions.length : 0);
      return `
        <a class="exp-top-block-chip" href="/block/${encodeURIComponent(blockRef)}">
          <b>#${formatInt(b.height)}</b>
          <span class="exp-block-time">${esc(formatLocalTimestamp(b.timestamp))}</span>
          <span class="exp-block-tx">TX ${formatInt(txCount)}</span>
        </a>
      `;
    }).join('') || `<div class="exp-empty">Recent blocks unavailable.</div>`;
  }
  async function latestBlocksFromTip(tip, count = 8) {
    const out = [];
    let current = tip?.id;
    for (let i = 0; i < count && current; i++) {
      const b = await getExplorerJson(`/blocks/${encodeURIComponent(current)}`, EXPLORER_BLOCK_TTL_MS);
      out.push(b);
      current = b?.parentID || '';
    }
    return out;
  }
  function flattenTx(blocks) {
    const out = [];
    blocks.forEach((b) => {
      (b?.transactions || []).forEach((t) => out.push({ id: t.id, version: 'v1', timestamp: b.timestamp, height: b.height }));
      (b?.v2?.transactions || []).forEach((t) => out.push({ id: t.id, version: 'v2', timestamp: b.timestamp, height: b.height }));
    });
    return out;
  }
  function renderBlockchainDashboard(tip, txpool, blocks) {
      const txRows = flattenTx(blocks);
      const poolV1 = Array.isArray(txpool?.transactions) ? txpool.transactions.length : 0;
      const poolV2 = Array.isArray(txpool?.v2transactions) ? txpool.v2transactions.length : 0;
      const txpoolTotal = poolV1 + poolV2;
      const avgTxPerBlock = blocks.length ? Math.round(txRows.length / blocks.length) : 0;
      let avgBlockSeconds = 0;
      if (blocks.length > 1) {
        const deltas = [];
        for (let i = 0; i < blocks.length - 1; i++) {
          const t1 = new Date(blocks[i]?.timestamp).getTime();
          const t2 = new Date(blocks[i + 1]?.timestamp).getTime();
          if (Number.isFinite(t1) && Number.isFinite(t2) && t1 > t2) {
            deltas.push((t1 - t2) / 1000);
          }
        }
        if (deltas.length) {
          avgBlockSeconds = deltas.reduce((sum, d) => sum + d, 0) / deltas.length;
        }
      }

      if (els.kpiBlockHeight) els.kpiBlockHeight.textContent = formatInt(tip?.height);
      const tipTimestamp = blocks?.[0]?.timestamp || tip?.timestamp;
      tipTimestampMs = new Date(tipTimestamp).getTime();
      if (els.kpiLastBlockTime) els.kpiLastBlockTime.textContent = relTimeFromMs(tipTimestampMs);
      startTipTicker();
      const avgBlockEl = $('exp-avg-block-time');
      if (avgBlockEl) avgBlockEl.textContent = formatDuration(avgBlockSeconds);
      if (els.telemetry) {
        els.telemetry.innerHTML = renderTelemetryCards({
          txpoolTotal,
          avgTxPerBlock
        });
      }
      if (els.topBlockPulse) els.topBlockPulse.innerHTML = renderTopBlockPulse(blocks);
  }
  function hasUsableExplorerSeed() {
    return !!(EXPLORER_SEED?.tip && EXPLORER_SEED?.txpool && Array.isArray(EXPLORER_SEED?.blocks) && EXPLORER_SEED.blocks.length);
  }
  function seedBrowserExplorerCache() {
    if (!hasUsableExplorerSeed() || !API_BASE) return;
    try {
      const version = (typeof window !== 'undefined' && window.FETCH_CACHE_VERSION) ? window.FETCH_CACHE_VERSION : 'v1';
      const put = (url, data) => localStorage.setItem(`fetchCache:${version}:${url}`, JSON.stringify({ timestamp: Date.now(), data }));
      put(API_BASE + '/consensus/tip', EXPLORER_SEED.tip);
      put(API_BASE + '/txpool/transactions', EXPLORER_SEED.txpool);
      EXPLORER_SEED.blocks.forEach((block) => {
        const id = String(block?.id || '').trim();
        if (id) put(API_BASE + `/blocks/${encodeURIComponent(id)}`, block);
      });
    } catch (_err) {}
  }
  async function loadDashboard() {
    try {
      if (hasUsableExplorerSeed()) {
        seedBrowserExplorerCache();
        renderBlockchainDashboard(EXPLORER_SEED.tip, EXPLORER_SEED.txpool, EXPLORER_SEED.blocks);
        return;
      }

      const [tip, txpool] = await Promise.all([
        getExplorerJson('/consensus/tip', EXPLORER_TIP_TTL_MS),
        getExplorerJson('/txpool/transactions', EXPLORER_TXPOOL_TTL_MS),
      ]);
      const blocks = await latestBlocksFromTip(tip, 10);
      renderBlockchainDashboard(tip, txpool, blocks);
    } catch (_e) {
      if (els.kpiBlockHeight) els.kpiBlockHeight.textContent = 'Unavailable';
      if (els.telemetry) els.telemetry.innerHTML = `<div class="exp-error">Unable to load blockchain telemetry.</div>`;
      if (els.topBlockPulse) els.topBlockPulse.innerHTML = `<div class="exp-error">Unable to load recent block pulse.</div>`;
    }
  }
  function wireSearch() {
    if (!els.searchForm || !els.searchInput || !els.searchSubmit) return;
    status('');
    document.addEventListener('keydown', (e) => {
      if (e.key === '/' && document.activeElement !== els.searchInput) {
        e.preventDefault();
        els.searchInput.focus();
        els.searchInput.select();
      }
    });
    els.searchInput.addEventListener('keydown', (e) => {
      if (e.key === 'Escape') {
        els.searchInput.value = '';
        status('Search cleared.');
      }
    });
    els.searchForm.addEventListener('submit', async (e) => {
      e.preventDefault();
      const query = String(els.searchInput.value || '').trim();
      if (!query) {
        status('Enter a value to search.', 'error');
        return;
      }
      els.searchSubmit.disabled = true;
      status('Searching explorer…', 'loading');
      try {
        const target = await resolveSearch(query);
        if (!target) {
          status('No result found for that query.', 'error');
          els.searchSubmit.disabled = false;
          return;
        }
        status('Found. Redirecting…', 'success');
        window.location.href = target;
      } catch (_err) {
        status('Search failed. Please try again.', 'error');
        els.searchSubmit.disabled = false;
      }
    });
  }
  function wireRangeTabs() {
    els.rangeTabs.forEach((button) => {
      button.addEventListener('click', () => {
        const range = String(button.dataset.range || '24h');
        loadRangeMetrics(range);
      });
    });
  }

  wireRangeTabs();
  wireSearch();
  loadDashboard();
})();
</script>
<?php render_footer(['/js/siacoin-price.js', '/js/network-revenue.js']); ?>
