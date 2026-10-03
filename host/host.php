<?php
require_once dirname(__DIR__) . '/bootstrap.php';
include_once dirname(__DIR__) . '/include/graph.php';
$graphConfigs = require dirname(__DIR__) . '/include/graph_configs.php';
include_once dirname(__DIR__) . '/include/config.php';
require_once dirname(__DIR__) . '/include/layout.php';
require_once dirname(__DIR__) . '/include/components/dashboard.php';
require_once dirname(__DIR__) . '/include/host_dashboard.php';

function render_score($score) {
    $score = max(0, min(10, ceil($score)));
    $hue = ($score / 10) * 120; // 0 = red, 120 = green
    return '<span class="score" style="color: hsl(' . $hue . ', 70%, 50%);">' . $score . '</span>';
}

function sc_value($val) {
    return is_array($val) ? ($val['sc'] ?? reset($val)) : $val;
}

use Siagraph\Utils\Cache;
use Siagraph\Utils\CurrencyDisplay;
use Siagraph\Utils\ApiClient;
$currencyCookie = CurrencyDisplay::selectedCurrency();
$currency = strtolower($currencyCookie);

$host_id = $_GET["id"] ?? '';
$public_key = $_GET["public_key"] ?? '';

// Host main data cache key
$hostCacheKey = 'host' . http_build_query($_GET);
$hostCacheResult = json_decode(Cache::getCache($hostCacheKey), true);
// Try to get host data from cache, else fetch it
if (!$hostCacheResult) {
   if ($public_key) {
      $url = $SETTINGS['siagraph_base_url'] . '/api/v1/host?public_key=' . $public_key;
   } elseif ($host_id) {
      $url = $SETTINGS['siagraph_base_url'] . '/api/v1/host?id=' . $host_id;
   } else {
      die("Missing host identifier.");
   }

   try {
      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $response = curl_exec($ch);
      if (curl_errno($ch)) {
         throw new Exception(curl_error($ch));
      }
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($http_code !== 200) {
         throw new Exception("Unexpected HTTP code: $http_code");
      }
      $hostdata = json_decode($response, true);
      curl_close($ch);
      try {
         $shouldCacheHost = is_array($hostdata) && (
            (isset($hostdata['known']) && $hostdata['known'] === true)
            || ((int) ($hostdata['host_id'] ?? 0) > 0)
         );
         if ($shouldCacheHost) {
            Cache::setCache(json_encode($hostdata), $hostCacheKey, 'hour');
         }
      } catch (Exception $e) {
         // ignore cache errors
      }
   } catch (Exception $err) {
      die("Error fetching host data: " . $err->getMessage());
   }
} else {
   $hostdata = $hostCacheResult;
}
if (!is_array($hostdata)) {
   $hostdata = [];
}
$isKnownHost = isset($hostdata['known']) ? ((bool) $hostdata['known']) : (((int) ($hostdata['host_id'] ?? 0)) > 0);
$hostPublicKey = (string) ($hostdata['public_key'] ?? $public_key);
$hostNetAddress = (string) ($hostdata['net_address'] ?? '');
$hostDisplayName = $isKnownHost ? ($hostNetAddress !== '' ? $hostNetAddress : $hostPublicKey) : $hostPublicKey;
$hostDisplayName = $hostDisplayName !== '' ? $hostDisplayName : 'Unknown host';
$hostDailyStatsUrl = $hostPublicKey !== ''
   ? '/api/v1/daily/hosts?public_key=' . rawurlencode($hostPublicKey) . '&limit=0&sort=date&order=asc'
   : '';
$hostDailyStatsPayload = ['hosts' => []];
if ($hostDailyStatsUrl !== '') {
   $dailyPayload = ApiClient::fetchJson($hostDailyStatsUrl, true, 'hour');
   if (is_array($dailyPayload) && isset($dailyPayload['hosts']) && is_array($dailyPayload['hosts'])) {
      $hostDailyStatsPayload = $dailyPayload;
      $dailyRows = $dailyPayload['hosts'];
      $latestDailyStats = end($dailyRows);
      if (is_array($latestDailyStats)) {
         $hostdata['used_storage'] = $latestDailyStats['used_storage'] ?? ($hostdata['used_storage'] ?? null);
         $hostdata['total_storage'] = $latestDailyStats['total_storage'] ?? ($hostdata['total_storage'] ?? null);
         if (!isset($hostdata['settings']) || !is_array($hostdata['settings'])) {
            $hostdata['settings'] = [];
         }
         $hostdata['settings']['storageprice'] = $latestDailyStats['storage_price'] ?? ($hostdata['settings']['storageprice'] ?? null);
         $hostdata['settings']['ingressprice'] = $latestDailyStats['upload_price'] ?? ($hostdata['settings']['ingressprice'] ?? null);
         $hostdata['settings']['egressprice'] = $latestDailyStats['download_price'] ?? ($hostdata['settings']['egressprice'] ?? null);
         $hostdata['settings']['contractprice'] = $latestDailyStats['contract_price'] ?? ($hostdata['settings']['contractprice'] ?? null);
         $hostdata['settings']['acceptingcontracts'] = $latestDailyStats['accepting_contracts'] ?? ($hostdata['settings']['acceptingcontracts'] ?? null);
      }
   } else {
      $hostDailyStatsPayload = ['hosts' => []];
   }
}

$parts = explode(':', $hostNetAddress);
$lastPart = end($parts);
$assumed_rhp4_port = is_numeric($lastPart) ? ((int) $lastPart + 2) : null;
$hostscorePublicKeyId = preg_replace('/^ed25519:/', '', $hostPublicKey);
$globalScores = $hostdata['node_scores']['global'] ?? [];
$latestGlobalScore = (is_array($globalScores) && !empty($globalScores)) ? end($globalScores) : [];
$totalScore = (float) ($latestGlobalScore['total_score'] ?? 0);
$ttfbScore = (float) ($latestGlobalScore['ttfb_score'] ?? 0);
$uploadScore = (float) ($latestGlobalScore['upload_score'] ?? 0);
$downloadScore = (float) ($latestGlobalScore['download_score'] ?? 0);
$benchmark = is_array($hostdata['benchmark'] ?? null) ? $hostdata['benchmark'] : [];
$ttfbMs = round(((float) ($benchmark['ttfb'] ?? 0)) / 1000 / 1000, 1);
$uploadMBs = round(((float) ($benchmark['upload_speed'] ?? 0)) / 1000 / 1000, 2);
$downloadMBs = round(((float) ($benchmark['download_speed'] ?? 0)) / 1000 / 1000, 2);

$groupedBenchmarks = ["global" => []];
if ($hostPublicKey !== '') {
   try {
      $fromDate = (new DateTime('now', new DateTimeZone('UTC')))
         ->modify('-7 days')
         ->setTime(0, 0, 0)
         ->format('Y-m-d\TH:i:s\Z');
      $benchUrl = "https://api.hostscore.info/v1/hosts/benchmarks?network="
         . rawurlencode((string) ($SETTINGS['network'] ?? 'mainnet'))
         . "&host=" . rawurlencode($hostPublicKey)
         . "&all=true&from=" . rawurlencode($fromDate);
      $benchData = ApiClient::fetchJson($benchUrl);
      if (is_array($benchData) && isset($benchData['benchmarks']) && is_array($benchData['benchmarks'])) {
         usort($benchData['benchmarks'], function ($a, $b) {
            return strtotime((string) ($b['timestamp'] ?? '')) <=> strtotime((string) ($a['timestamp'] ?? ''));
         });
         foreach ($benchData['benchmarks'] as $benchmark) {
            $groupedBenchmarks['global'][] = $benchmark;
            $node = (string) ($benchmark['node'] ?? 'unknown');
            if (!isset($groupedBenchmarks[$node])) {
               $groupedBenchmarks[$node] = [];
            }
            $groupedBenchmarks[$node][] = $benchmark;
         }
      }
   } catch (Exception $e) {
      // keep empty on failure
   }
}

// Troubleshooter cache key (based on net address)
$troubleshooterCacheKey = 'host_troubleshooter:' . $hostNetAddress;
$troubleshooterCacheResult = json_decode(Cache::getCache($troubleshooterCacheKey), true);

// Try to get troubleshooter data from cache, else fetch it
if (!$troubleshooterCacheResult && 1==2) {
   echo "hoi";
   $tsUrl = $SETTINGS['siagraph_base_url'] . '/api/v1/host_troubleshooter?net_address=' . urlencode($hostdata['net_address']);
   try {
      $ch = curl_init($tsUrl);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $response = curl_exec($ch);
      if (curl_errno($ch)) {
         throw new Exception(curl_error($ch));
      }
      $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
      if ($http_code !== 200) {
         throw new Exception("Unexpected HTTP code: $http_code");
      }
      $troubleshootData = json_decode($response, true);
      curl_close($ch);
      try {
         Cache::setCache(json_encode($troubleshootData), $troubleshooterCacheKey, 'hour');
      } catch (Exception $e) {
         // ignore cache errors
      }
   } catch (Exception $err) {
      $troubleshootData = null; // or log error
   }
} else {
   $troubleshootData = $troubleshooterCacheResult;
}

?>


<?php render_header('SiaGraph Host Explorer - ' . htmlspecialchars($hostDisplayName, ENT_QUOTES, 'UTF-8'), 'SiaGraph Host Explorer', [
   '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/components/data-page.css'), ENT_QUOTES, 'UTF-8') . '">',
   '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/components/dashboard.css'), ENT_QUOTES, 'UTF-8') . '">',
   '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />',
   '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/host.css'), ENT_QUOTES, 'UTF-8') . '">',
   '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/active-contracts.css'), ENT_QUOTES, 'UTF-8') . '">'
]); ?>
   <!-- Main Content Section -->
   <section id="main-content" class="sg-container">

      <div class="host-top-compact">
         <section class="card host-hero">
            <div class="card__content">
               <div class="host-hero__top">
                  <div>
                     <div class="host-hero__kicker">Host Explorer</div>
                     <h1 class="host-hero__title"><?php echo htmlspecialchars($hostDisplayName, ENT_QUOTES, 'UTF-8'); ?></h1>
                  </div>
                  <div class="host-hero__actions">
                     <a class="button text-sm" href="/host_explorer">Back to hosts</a>
                     <a class="button text-sm" href="/host_revenue_export?public_key=<?php echo rawurlencode($hostPublicKey); ?>"><i class="bi bi-download me-1" aria-hidden="true"></i>Export revenue</a>
                     <a class="btn btn-sm btn-brand flex items-center" href="/host_alerts?public_key=<?php echo rawurlencode($hostPublicKey); ?>">
                        🔔 Subscribe
                     </a>
                  </div>
               </div>
               <div class="host-hero__ids">
                  <div class="host-hero__idrow">
                     <span class="host-hero__label">Net address</span>
                     <span class="host-hero__value"><?php echo htmlspecialchars($hostNetAddress !== '' ? $hostNetAddress : 'Unavailable', ENT_QUOTES, 'UTF-8'); ?></span>
                     <?php if ($hostNetAddress !== ''): ?>
                     <button class="host-copy" type="button" aria-label="Copy net address" title="Copy net address" onclick='copyToClipboard(<?php echo json_encode($hostNetAddress, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, this)'>⧉</button>
                     <?php endif; ?>
                  </div>
                  <div class="host-hero__idrow">
                     <span class="host-hero__label">Public key</span>
                     <span class="host-hero__value host-hero__value--mono"><?php echo htmlspecialchars($hostPublicKey !== '' ? $hostPublicKey : 'Unavailable', ENT_QUOTES, 'UTF-8'); ?></span>
                     <?php if ($hostPublicKey !== ''): ?>
                     <button class="host-copy" type="button" aria-label="Copy public key" title="Copy public key" onclick='copyToClipboard(<?php echo json_encode($hostPublicKey, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>, this)'>⧉</button>
                     <?php endif; ?>
                  </div>
               </div>
               <div class="host-hero__chips" id="hostStatusChips"></div>
               <div id="hostAcceptingWarning" class="mt-2"></div>
            </div>
         </section>
         <section class="card host-location-top-card">
            <h2 class="card__heading">Host Location</h2>
            <div class="card__content">
               <div id="map"></div>
            </div>
         </section>
      </div>

      <?php if (!$isKnownHost): ?>
      <div class="sg-container__row mt-3">
         <div class="sg-container__row-content">
            <section class="card">
               <h2 class="card__heading">Host Pending Indexing</h2>
               <div class="card__content">
                  <div class="alert alert-warning mb-0" role="alert">
                     This public key is valid but not indexed in SiaGraph yet. Try again in a few minutes.
                     <div class="mt-2"><code><?php echo htmlspecialchars($hostPublicKey, ENT_QUOTES, 'UTF-8'); ?></code></div>
                  </div>
               </div>
            </section>
         </div>
      </div>
      <?php endif; ?>

      <section class="card host-summary">
         <div class="card__content">
            <div class="host-summary-grid">
               <article class="host-stat-card">
                  <div class="host-stat-card__label">Used Storage</div>
                  <div class="host-stat-card__value" id="hostSummaryUsed">-</div>
               </article>
               <article class="host-stat-card">
                  <div class="host-stat-card__label">Total Storage</div>
                  <div class="host-stat-card__value" id="hostSummaryTotal">-</div>
               </article>
               <article class="host-stat-card">
                  <div class="host-stat-card__label">Free Storage</div>
                  <div class="host-stat-card__value" id="hostSummaryFree">-</div>
               </article>
               <article class="host-stat-card">
                  <div class="host-stat-card__label">Utilization</div>
                  <div class="host-stat-card__value" id="hostSummaryUtilization">-</div>
               </article>
               <article class="host-stat-card">
                  <div class="host-stat-card__label">Last Announced</div>
                  <div class="host-stat-card__value host-stat-card__value--small" id="hostSummaryAnnounced">-</div>
               </article>
            </div>
         </div>
      </section>

      <div class="host-tabset" role="tablist" aria-label="Host sections">
         <input class="host-tab-radio" type="radio" name="host-tabs" id="host-tab-overview-radio" aria-hidden="true" tabindex="-1" checked>
         <input class="host-tab-radio" type="radio" name="host-tabs" id="host-tab-contracts-radio" aria-hidden="true" tabindex="-1">
         <input class="host-tab-radio" type="radio" name="host-tabs" id="host-tab-economics-radio" aria-hidden="true" tabindex="-1">
         <input class="host-tab-radio" type="radio" name="host-tabs" id="host-tab-benchmarks-radio" aria-hidden="true" tabindex="-1">
         <input class="host-tab-radio" type="radio" name="host-tabs" id="host-tab-charts-radio" aria-hidden="true" tabindex="-1">
         <div class="host-tabs">
            <label id="host-tab-overview" class="host-tab" role="tab" aria-controls="host-tabpanel-overview" aria-selected="true" for="host-tab-overview-radio">Overview</label>
            <label id="host-tab-contracts" class="host-tab" role="tab" aria-controls="host-tabpanel-contracts" aria-selected="false" for="host-tab-contracts-radio">Contracts</label>
            <label id="host-tab-economics" class="host-tab" role="tab" aria-controls="host-tabpanel-economics" aria-selected="false" for="host-tab-economics-radio">Economics</label>
            <label id="host-tab-benchmarks" class="host-tab" role="tab" aria-controls="host-tabpanel-benchmarks" aria-selected="false" for="host-tab-benchmarks-radio">Benchmarks</label>
            <label id="host-tab-history" class="host-tab" role="tab" aria-controls="host-tabpanel-history" aria-selected="false" for="host-tab-charts-radio">History</label>
         </div>
         <div class="host-tab-panels">
            <div class="host-tab-panel" id="host-tabpanel-overview" role="tabpanel" aria-labelledby="host-tab-overview" data-panel="overview">
               <div class="sg-container__row">
                  <div class="sg-container__row-content host-top-columns">
                     <div class="sg-container__column sg-container__column--half">
                        <section class="card w-full host-section-card">
                           <h2 class="card__heading">Pricing & Terms</h2>
                           <div class="card__content">
                              <div class="table-responsive">
                                 <table class="table table-dark table-clean text-white w-100 border-collapse host-stats-table">
                                    <tbody id="hostStatsPricing"></tbody>
                                 </table>
                              </div>
                           </div>
                        </section>
                     </div>
                     <div class="sg-container__column sg-container__column--half">
                        <section class="card host-section-card host-evaluation-card">
                           <h2 class="card__heading">Host Evaluation</h2>
                           <div class="card__content host-evaluation-card__content">
                              <section class="host-evaluation-block">
                                 <h3 class="host-evaluation-block__heading flex items-center gap-2">
                                    Host Insights
                                    <i class="bi bi-info-circle text-gray-300 text-sm" data-bs-toggle="tooltip" data-bs-placement="top"
                                       title="These benchmarks are sourced from hostscore.info.
Hosts are scored by percentile: the top 10% receive 10, the next 10% receive 9, and so on.
Missing benchmarks reduce the score, meaning a host with the same average could have a different score.
Each benchmark server contributes equally to the score, regardless of how many benchmarks it produced.
"></i>
                                 </h3>
                                 <div class="table-responsive">
                                    <table id="hostscoreBenchmarks" class="table table-dark table-clean text-white w-100 border-collapse">
                                       <tbody>
                                       <tr class="bg-gray-700">
                                          <td class="px-4 py-2 font-semibold">Final score</td>
                                          <td class="px-4 py-2 text-right text-lg"><span class="inline-flex items-center"><?php echo render_score($totalScore); ?></span></td>
                                       </tr>
                                       <tr class="bg-gray-900">
                                          <td class="px-4 py-2 font-semibold"><span data-bs-toggle="tooltip" data-bs-placement="top" title="Lower is better. Time until first response byte.">Time to First Byte</span></td>
                                          <td class="px-4 py-2 text-right"><span class="inline-flex items-center gap-1 justify-end"><span><?php echo $ttfbMs . " ms"; ?></span><span><?php echo render_score($ttfbScore); ?></span></span></td>
                                       </tr>
                                       <tr class="bg-gray-800">
                                          <td class="px-4 py-2 font-semibold"><span data-bs-toggle="tooltip" data-bs-placement="top" title="Ingress bandwidth from renter to host.">Ingress</span></td>
                                          <td class="px-4 py-2 text-right"><span class="inline-flex items-center gap-1 justify-end"><span><?php echo $uploadMBs . " MB/s"; ?></span><span><?php echo render_score($uploadScore); ?></span></span></td>
                                       </tr>
                                       <tr class="bg-gray-900">
                                          <td class="px-4 py-2 font-semibold"><span data-bs-toggle="tooltip" data-bs-placement="top" title="Egress bandwidth from host to renter.">Egress</span></td>
                                          <td class="px-4 py-2 text-right"><span class="inline-flex items-center gap-1 justify-end"><span><?php echo $downloadMBs . " MB/s"; ?></span><span><?php echo render_score($downloadScore); ?></span></span></td>
                                       </tr>
                                       </tbody>
                                    </table>
                                 </div>
                              </section>
                              <section class="host-evaluation-block">
                                 <h3 class="host-evaluation-block__heading" title="Compared with hosts having a similar final score.">Network Context</h3>
                                 <div class="table-responsive">
                                    <table id="hostAverages" class="table table-dark table-clean text-white w-100 border-collapse table-loading">
                                       <thead></thead>
                                       <tbody id="hostAveragesBody"><tr><td colspan="3" class="px-4 py-3"><span class="skeleton-line"></span></td></tr></tbody>
                                    </table>
                                 </div>
                              </section>
                           </div>
                        </section>
                     </div>
                  </div>
               </div>
            </div>

            <div class="host-tab-panel" id="host-tabpanel-contracts" role="tabpanel" aria-labelledby="host-tab-contracts" data-panel="contracts">
               <div class="sg-container__row">
                  <div class="sg-container__row-content">
                     <div class="sg-container__column">
                        <section class="card host-section-card">
                           <h2 class="card__heading">Contract Activity</h2>
                           <p><a class="button text-sm" href="/host_contracts?public_key=<?php echo rawurlencode($hostPublicKey); ?>">Browse contracts →</a></p>
                           <div class="card__content" data-active-contracts data-kind="host" data-identity="<?php echo htmlspecialchars($hostPublicKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
                        </section>
                     </div>
                  </div>
               </div>
            </div>

            <div class="host-tab-panel" id="host-tabpanel-benchmarks" role="tabpanel" aria-labelledby="host-tab-benchmarks" data-panel="benchmarks">
               <div class="sg-container__row">
                  <div class="sg-container__row-content">
                     <div class="sg-container__column">
                        <section class="card host-section-card">
                           <h2 class="card__heading">Recent Benchmarks</h2>
                           <div class="card__content">
                              <div class="d-flex flex-wrap mb-3 align-items-center gap-2">
                                 <label for="hostBenchNodeSelect" class="form-label mb-0 text-sm text-gray-300">Node:</label>
                                 <select id="hostBenchNodeSelect" class="form-select host-benchmark-node-select">
                                    <?php foreach ($groupedBenchmarks as $node => $benchmarks): ?>
                                       <option value="<?php echo htmlspecialchars($node, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($node, ENT_QUOTES, 'UTF-8'); ?></option>
                                    <?php endforeach; ?>
                                 </select>
                                 <a class="button text-sm" target="_blank" rel="noopener noreferrer" href='https://hostscore.info/host/<?php echo rawurlencode($hostscorePublicKeyId); ?>'>Open HostScore</a>
                              </div>
                              <div id="hostBenchSharedLegend" class="host-bench-shared-legend mb-2"></div>
                              <div class="row">
                                 <div class="col-md-6">
                                    <div class="benchmark-chart-wrap">
                                       <canvas id="hostBenchUploadChart" class="benchmark-chart-canvas"></canvas>
                                    </div>
                                 </div>
                                 <div class="col-md-6">
                                    <div class="benchmark-chart-wrap">
                                       <canvas id="hostBenchDownloadChart" class="benchmark-chart-canvas"></canvas>
                                    </div>
                                 </div>
                              </div>
                              <div class="table-responsive mt-3">
                                 <table id="hostBenchTable" class="table table-dark table-clean text-white min-w-full border-collapse">
                                    <thead>
                                       <tr>
                                          <th class="px-3 py-2">Timestamp</th>
                                          <th class="px-3 py-2">Node</th>
                                          <th class="px-3 py-2 text-end">Upload</th>
                                          <th class="px-3 py-2 text-end">Download</th>
                                          <th class="px-3 py-2 text-end">TTFB</th>
                                          <th class="px-3 py-2">Status</th>
                                       </tr>
                                    </thead>
                                    <tbody id="hostBenchTableBody"></tbody>
                                 </table>
                              </div>
                              <div class="d-flex justify-content-end mt-2">
                                 <button type="button" id="hostBenchShowMore" class="button text-sm">Show more</button>
                              </div>
                           </div>
                        </section>
                     </div>
                  </div>
               </div>
            </div>

            <div class="host-tab-panel" id="host-tabpanel-economics" role="tabpanel" aria-labelledby="host-tab-economics" data-panel="economics">
               <div class="sg-container__row">
                  <div class="sg-container__row-content">
                     <div class="sg-container__column">
                        <section class="card host-section-card">
                           <h2 class="card__heading">Host Economics</h2>
                           <div class="card__content">
                              <section class="host-locked-revenue" data-contract-economics data-identity="<?php echo htmlspecialchars($hostPublicKey, ENT_QUOTES, 'UTF-8'); ?>">
                                 <div class="host-stat-card">
                                    <div class="host-stat-card__label" title="Revenue committed in active contracts, not yet earned. Fiat uses the current available exchange rate.">Locked revenue</div>
                                    <div class="host-stat-card__value" data-locked-revenue>—</div>
                                 </div>
                                 <div>
                                    <p class="host-section-subtitle" data-locked-status role="status">Loads when Economics is opened.</p>
                                 </div>
                              </section>
                              <?php render_dashboard(host_dashboard('economics', $hostDailyStatsUrl, $hostPublicKey, $currency), true); ?>
                           </div>
                        </section>
                     </div>
                  </div>
               </div>
            </div>

            <div class="host-tab-panel" id="host-tabpanel-history" role="tabpanel" aria-labelledby="host-tab-history" data-panel="charts">
               <?php render_dashboard(host_dashboard('history', $hostDailyStatsUrl, $hostPublicKey, $currency), true); ?>
            </div>
         </div>
      </div>
   </section>
   <!-- Footer Section -->
   <div id="toast" class="sg-toast is-hidden bg-blue-600 text-white px-4 py-2 rounded bg-gradient shadow-lg">
      Copied to clipboard!
   </div>

   <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
   let hostdata = <?php echo json_encode($hostdata); ?>;
   let groupedBenchmarks = <?php echo json_encode($groupedBenchmarks); ?>;
   let exchangeRate = null;
   window.hostExchangeRate = null;
   // Function to initialize the map
   function initMap() {
      const mapEl = document.getElementById('map');
      if (!mapEl) return;
      var locationString = "<?php echo htmlspecialchars($hostdata['location'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"; // Get the location string from PHP

      // Split the location string by comma
      var coordinates = locationString.split(',');

      // Assign latitude and longitude
      var latitude = Number.parseFloat(coordinates[0]);
      var longitude = Number.parseFloat(coordinates[1]);
      if (!Number.isFinite(latitude) || !Number.isFinite(longitude)) {
         mapEl.innerHTML = '<div class="px-3 py-2 text-sm text-gray-400">Location unavailable.</div>';
         return;
      }
      var map = L.map('map', {
         center: [latitude, longitude],
         zoom: 4,
         zoomControl: false, // Disallow changing the zoom level
         scrollWheelZoom: false, // Disable zooming using the scroll wheel
         dragging: false // Disable panning (moving) the map
      });
      // Add a tile layer (you can use any tile provider, here I'm using OpenStreetMap)
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
         attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
      }).addTo(map);

      // Add a marker at the specified coordinates
      L.marker([latitude, longitude]).addTo(map)
         .bindPopup('Host Location'); // Popup message when marker is clicked
   }

   function escapeHtmlAttr(value) {
      return String(value)
         .replace(/&/g, '&amp;')
         .replace(/</g, '&lt;')
         .replace(/>/g, '&gt;')
         .replace(/"/g, '&quot;')
         .replace(/'/g, '&#39;');
   }

   function initTooltips(scope = document) {
      scope.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
         bootstrap.Tooltip.getOrCreateInstance(el, {
            container: 'body',
            customClass: 'sg-tooltip'
         });
      });
   }

   function formatRelativeAge(isoString) {
      if (!isoString) return null;
      const dt = new Date(isoString);
      if (Number.isNaN(dt.getTime())) return null;
      const seconds = Math.max(0, Math.floor((Date.now() - dt.getTime()) / 1000));
      const parts = [
         { unit: 'y', value: 31536000 },
         { unit: 'mo', value: 2592000 },
         { unit: 'd', value: 86400 },
         { unit: 'h', value: 3600 },
         { unit: 'm', value: 60 }
      ];
      for (const p of parts) {
         if (seconds >= p.value) {
            return `${Math.floor(seconds / p.value)}${p.unit} ago`;
         }
      }
      return 'just now';
   }

   function renderHostStatusChips(data) {
      const chips = document.getElementById('hostStatusChips');
      if (!chips || !data) return;
      const status = data.online ? 'Online' : 'Offline';
      const statusCls = data.online ? 'is-online' : 'is-offline';
      const accepting = data?.settings?.acceptingcontracts ? 'Accepting contracts' : 'Not accepting contracts';
      const acceptingCls = data?.settings?.acceptingcontracts ? 'is-online' : 'is-warn';
      const lastSeen = formatRelativeAge(data.last_successful_scan || data.last_updated);
      const knownSince = formatRelativeAge(data.first_seen);
      const country = data.country ? String(data.country).toUpperCase() : null;
      const version = data.software_version || data.protocol_version || null;
      const items = [
         { text: status, cls: statusCls },
         { text: accepting, cls: acceptingCls },
         lastSeen ? { text: `Last seen ${lastSeen}`, cls: 'is-neutral' } : null,
         knownSince ? { text: `Known ${knownSince}`, cls: 'is-neutral' } : null,
         country ? { text: country, cls: 'is-neutral' } : null,
         version ? { text: version, cls: 'is-neutral' } : null
      ].filter(Boolean);
      chips.innerHTML = items.map((item) => `<span class="host-chip ${item.cls}">${escapeHtmlAttr(item.text)}</span>`).join('');
   }

   function renderAcceptingContractsWarning(data) {
      const el = document.getElementById('hostAcceptingWarning');
      if (!el) return;
      const accepts = Boolean(data?.settings?.acceptingcontracts);
      if (accepts) {
         el.innerHTML = '';
         return;
      }
      el.innerHTML = `
         <div class="alert alert-warning mb-0" role="alert">
            When a host is not accepting new contracts, renters treat the host as retiring. They will stop using it and may begin migrating data immediately.
            <br><br>
            <strong class="text-danger">If this is your host and you are not retiring, re-enable this setting as soon as possible.</strong>
         </div>
      `;
   }

   function renderHostSummary(data) {
      const used = Number(data?.used_storage || 0);
      const total = Number(data?.total_storage || 0);
      const free = Math.max(0, total - used);
      const util = total > 0 ? (used / total) * 100 : 0;
      const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
      const fmtTb = (v) => `${(v / 1e12).toLocaleString(loc, { maximumFractionDigits: 2 })} TB`;
      const announced = data?.last_announced ? formatRelativeAge(data.last_announced) : null;

      const setText = (id, value) => {
         const el = document.getElementById(id);
         if (el) el.textContent = value;
      };
      setText('hostSummaryUsed', fmtTb(used));
      setText('hostSummaryTotal', fmtTb(total));
      setText('hostSummaryFree', fmtTb(free));
      setText('hostSummaryUtilization', `${util.toFixed(2)}%`);
      setText('hostSummaryAnnounced', announced || 'Unavailable');
   }

   function shortKey(value, head = 12, tail = 8) {
      const v = String(value || '');
      if (v.length <= head + tail + 3) return v;
      return `${v.slice(0, head)}...${v.slice(-tail)}`;
   }

   function humanBytes(bytes) {
      const b = Number(bytes || 0);
      if (!Number.isFinite(b) || b <= 0) return '0 B';
      const units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
      let n = b;
      let u = 0;
      while (n >= 1000 && u < units.length - 1) {
         n /= 1000;
         u++;
      }
      const d = n >= 100 ? 0 : (n >= 10 ? 1 : 2);
      return `${n.toFixed(d)} ${units[u]}`;
   }

   function setupHostTabHashRouting() {
      const hashToRadioId = {
         '#overview': 'host-tab-overview-radio',
         '#contracts': 'host-tab-contracts-radio',
         '#economics': 'host-tab-economics-radio',
         '#benchmarks': 'host-tab-benchmarks-radio',
         '#charts': 'host-tab-charts-radio', // Preserve existing links.
         '#history': 'host-tab-charts-radio'
      };

      const radioIdToHash = Object.fromEntries(
         Object.entries(hashToRadioId).map(([hash, id]) => [id, hash])
      );

         const updateTabState = (radioId) => {
            document.querySelectorAll('.host-tab[role="tab"]').forEach((tab) => {
               tab.setAttribute('aria-selected', tab.getAttribute('for') === radioId ? 'true' : 'false');
            });
         };

      const applyHash = () => {
         const hash = (window.location.hash || '').toLowerCase();
         const radioId = hashToRadioId[hash];
         if (!radioId) return;
         const radio = document.getElementById(radioId);
            if (radio) {
               radio.checked = true;
               updateTabState(radioId);
            }
      };

      const radios = Array.from(document.querySelectorAll('.host-tab-radio'));
      radios.forEach((radio) => {
         radio.addEventListener('change', () => {
            if (!radio.checked) return;
            updateTabState(radio.id);
            const hash = radioIdToHash[radio.id];
            if (!hash) return;
            const target = `${window.location.pathname}${window.location.search}${hash}`;
            if (`${window.location.pathname}${window.location.search}${window.location.hash}` !== target) {
               window.history.replaceState(null, '', target);
            }
         });
      });

      window.addEventListener('hashchange', applyHash);
      applyHash();
   }

   function setupBenchmarksTab() {
      const nodeSelect = document.getElementById('hostBenchNodeSelect');
      const tableBody = document.getElementById('hostBenchTableBody');
      const uploadCanvas = document.getElementById('hostBenchUploadChart');
      const downloadCanvas = document.getElementById('hostBenchDownloadChart');
      const showMoreBtn = document.getElementById('hostBenchShowMore');
      const sharedLegend = document.getElementById('hostBenchSharedLegend');
      if (!nodeSelect || !tableBody || !uploadCanvas || !downloadCanvas || !showMoreBtn || !sharedLegend) return;

      const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
      const fmtDate = (ts) => {
         const d = new Date(ts);
         if (Number.isNaN(d.getTime())) return String(ts || '');
         return d.toLocaleString(loc);
      };

      const statusPill = (b) => {
         if (b && b.success) return '<span class="host-chip is-online">OK</span>';
         const err = String((b && b.error) || 'Error');
         return `<span class="host-chip is-offline" title="${escapeHtmlAttr(err)}">${escapeHtmlAttr(err.slice(0, 32))}</span>`;
      };

      const benchmarkNodes = Object.keys(groupedBenchmarks || {});
      if (!benchmarkNodes.length) {
         tableBody.innerHTML = '<tr><td colspan="6" class="px-3 py-3 text-gray-300">No benchmark data available.</td></tr>';
         return;
      }

      let uploadChart = null;
      let downloadChart = null;
      const defaultRows = 15;
      let currentRowsLimit = defaultRows;
      let currentNodeRows = [];

      const drawCharts = (rows) => {
         if (typeof Chart !== 'function') return;
         const sorted = [...rows].sort((a, b) => {
            return new Date(a.timestamp).getTime() - new Date(b.timestamp).getTime();
         });
         const pointsUpload = [];
         const pointsDownload = [];
         const byDayUpload = {};
         const byDayDownload = {};
         const pointsByNodeUpload = {};
         const pointsByNodeDownload = {};

         sorted.forEach((r) => {
            const dt = new Date(r.timestamp);
            if (Number.isNaN(dt.getTime())) return;
            const nodeName = String(r.node || 'unknown');
            const upload = Number(r.uploadSpeed || 0) / 1000000;
            const download = Number(r.downloadSpeed || 0) / 1000000;
            pointsUpload.push({ x: dt, y: upload });
            pointsDownload.push({ x: dt, y: download });
            if (!pointsByNodeUpload[nodeName]) pointsByNodeUpload[nodeName] = [];
            if (!pointsByNodeDownload[nodeName]) pointsByNodeDownload[nodeName] = [];
            pointsByNodeUpload[nodeName].push({ x: dt, y: upload });
            pointsByNodeDownload[nodeName].push({ x: dt, y: download });
            const dayKey = dt.toISOString().slice(0, 10);
            if (!byDayUpload[dayKey]) byDayUpload[dayKey] = [];
            if (!byDayDownload[dayKey]) byDayDownload[dayKey] = [];
            byDayUpload[dayKey].push(upload);
            byDayDownload[dayKey].push(download);
         });

         const dailyAvgUpload = [];
         const dailyAvgUploadUpper = [];
         const dailyAvgUploadLower = [];
         const dailyAvgDownload = [];
         const dailyAvgDownloadUpper = [];
         const dailyAvgDownloadLower = [];

         const dayKeys = Object.keys(byDayUpload).sort();
         dayKeys.forEach((dayKey) => {
            const d = new Date(`${dayKey}T00:00:00Z`);
            const uVals = byDayUpload[dayKey] || [];
            const dVals = byDayDownload[dayKey] || [];
            if (!uVals.length || !dVals.length) return;
            const uAvg = uVals.reduce((a, b) => a + b, 0) / uVals.length;
            const dAvg = dVals.reduce((a, b) => a + b, 0) / dVals.length;
            dailyAvgUpload.push({ x: d, y: uAvg });
            dailyAvgUploadUpper.push({ x: d, y: uAvg * 1.25 });
            dailyAvgUploadLower.push({ x: d, y: Math.max(0, uAvg * 0.75) });
            dailyAvgDownload.push({ x: d, y: dAvg });
            dailyAvgDownloadUpper.push({ x: d, y: dAvg * 1.25 });
            dailyAvgDownloadLower.push({ x: d, y: Math.max(0, dAvg * 0.75) });
         });

         if (uploadChart) uploadChart.destroy();
         if (downloadChart) downloadChart.destroy();

         const chartBaseOptions = {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'nearest', intersect: false },
            plugins: {
               legend: {
                  labels: {
                     color: '#d8d5cf',
                     filter: (item, data) => {
                        const ds = data?.datasets?.[item.datasetIndex];
                        return !(ds && ds.hideLegend);
                     }
                  }
               },
               tooltip: {
                  callbacks: {
                     title: (items) => {
                        if (!items?.length) return '';
                        const x = items[0]?.parsed?.x;
                        const dt = x ? new Date(x) : null;
                        if (!dt || Number.isNaN(dt.getTime())) return '';
                        return dt.toLocaleDateString(loc, { month: 'short', day: 'numeric', year: 'numeric' });
                     },
                     label: (ctx) => {
                        const y = Number(ctx.parsed?.y || 0);
                        return `${ctx.dataset.label}: ${y.toFixed(2)} MB/s`;
                     }
                  }
               }
            },
            scales: {
               x: {
                  type: 'time',
                  time: { unit: 'day', tooltipFormat: 'MMM d, yyyy' },
                  ticks: { color: '#cfc9d8', maxTicksLimit: 10 },
                  grid: { color: 'rgba(255,255,255,0.05)' }
               },
               y: { ticks: { color: '#cfc9d8' }, grid: { color: 'rgba(255,255,255,0.08)' } }
            }
         };

         const nodePalette = [
            '#4ade80', '#60a5fa', '#f59e0b', '#f472b6', '#22d3ee',
            '#a78bfa', '#f87171', '#34d399', '#facc15', '#93c5fd'
         ];
         const makeNodeDatasets = (map, unitLabel) => {
            const nodes = Object.keys(map).sort();
            return nodes.map((node, idx) => {
               const color = nodePalette[idx % nodePalette.length];
               return {
                  label: `${node}`,
                  data: map[node],
                  borderColor: 'rgba(0,0,0,0)',
                  backgroundColor: color,
                  pointBackgroundColor: color,
                  pointRadius: 2.5,
                  showLine: false,
                  unitLabel
               };
            });
         };

         const isGlobal = String(nodeSelect.value || '').toLowerCase() === 'global';
         const measuredUploadDatasets = isGlobal
            ? makeNodeDatasets(pointsByNodeUpload, 'upload')
            : [{
               label: 'upload',
               data: pointsUpload,
               borderColor: 'rgba(74, 222, 128, 0)',
               backgroundColor: 'rgba(74, 222, 128, 0.9)',
               pointBackgroundColor: 'rgba(74, 222, 128, 0.9)',
               pointRadius: 2.5,
               showLine: false
            }];
         const measuredDownloadDatasets = isGlobal
            ? makeNodeDatasets(pointsByNodeDownload, 'download')
            : [{
               label: 'download',
               data: pointsDownload,
               borderColor: 'rgba(96, 165, 250, 0)',
               backgroundColor: 'rgba(96, 165, 250, 0.9)',
               pointBackgroundColor: 'rgba(96, 165, 250, 0.9)',
               pointRadius: 2.5,
               showLine: false
            }];

         uploadChart = new Chart(uploadCanvas.getContext('2d'), {
            type: 'line',
            data: {
               datasets: [...measuredUploadDatasets, {
                  label: '+25% band',
                  data: dailyAvgUploadUpper,
                  borderColor: 'rgba(74, 222, 128, 0.0)',
                  backgroundColor: 'rgba(74, 222, 128, 0.12)',
                  pointRadius: 0,
                  fill: '+1',
                  hideLegend: true
               }, {
                  label: '-25% band',
                  data: dailyAvgUploadLower,
                  borderColor: 'rgba(74, 222, 128, 0.0)',
                  backgroundColor: 'rgba(74, 222, 128, 0.0)',
                  pointRadius: 0,
                  fill: false,
                  hideLegend: true
               }, {
                  label: 'Daily average',
                  data: dailyAvgUpload,
                  borderColor: 'rgba(74, 222, 128, 1)',
                  backgroundColor: 'rgba(74, 222, 128, 0.2)',
                  pointRadius: 0,
                  borderWidth: 2,
                  tension: 0.2
               }]
            },
            options: chartBaseOptions
         });

         downloadChart = new Chart(downloadCanvas.getContext('2d'), {
            type: 'line',
            data: {
               datasets: [...measuredDownloadDatasets, {
                  label: '+25% band',
                  data: dailyAvgDownloadUpper,
                  borderColor: 'rgba(96, 165, 250, 0.0)',
                  backgroundColor: 'rgba(96, 165, 250, 0.12)',
                  pointRadius: 0,
                  fill: '+1',
                  hideLegend: true
               }, {
                  label: '-25% band',
                  data: dailyAvgDownloadLower,
                  borderColor: 'rgba(96, 165, 250, 0.0)',
                  backgroundColor: 'rgba(96, 165, 250, 0.0)',
                  pointRadius: 0,
                  fill: false,
                  hideLegend: true
               }, {
                  label: 'Daily average',
                  data: dailyAvgDownload,
                  borderColor: 'rgba(96, 165, 250, 1)',
                  backgroundColor: 'rgba(96, 165, 250, 0.2)',
                  pointRadius: 0,
                  borderWidth: 2,
                  tension: 0.2
               }]
            },
            options: chartBaseOptions
         });

         // One shared legend for both charts, since series meanings are identical.
         const legendParts = [];
         if (isGlobal) {
            Object.keys(pointsByNodeUpload).sort().forEach((node, idx) => {
               const color = nodePalette[idx % nodePalette.length];
               legendParts.push(
                 `<span class="host-bench-legend-item"><span class="host-bench-legend-swatch" style="background:${color}"></span>${escapeHtmlAttr(node)}</span>`
               );
            });
         } else {
            legendParts.push('<span class="host-bench-legend-item"><span class="host-bench-legend-swatch" style="background:rgba(74, 222, 128, 0.9)"></span>upload samples</span>');
            legendParts.push('<span class="host-bench-legend-item"><span class="host-bench-legend-swatch" style="background:rgba(96, 165, 250, 0.9)"></span>download samples</span>');
         }
         legendParts.push('<span class="host-bench-legend-item"><span class="host-bench-legend-swatch" style="background:rgba(160, 160, 170, 0.95)"></span>daily average</span>');
         legendParts.push('<span class="host-bench-legend-item"><span class="host-bench-legend-swatch" style="background:rgba(160, 160, 170, 0.2)"></span>±25% band</span>');
         sharedLegend.innerHTML = legendParts.join('');
      };

      const renderTableRows = () => {
         const rows = currentNodeRows;
         if (!rows.length) {
            tableBody.innerHTML = '<tr><td colspan="6" class="px-3 py-3 text-gray-300">No benchmarks for this node.</td></tr>';
            showMoreBtn.classList.add('is-hidden');
            return;
         }
         const shown = rows.slice(0, currentRowsLimit);
         tableBody.innerHTML = shown.map((b) => {
            const up = (Number(b.uploadSpeed || 0) / 1000000).toFixed(2);
            const down = (Number(b.downloadSpeed || 0) / 1000000).toFixed(2);
            const ttfb = (Number(b.ttfb || 0) / 1000000).toFixed(2);
            return `
               <tr>
                  <td class="px-3 py-2">${escapeHtmlAttr(fmtDate(b.timestamp))}</td>
                  <td class="px-3 py-2">${escapeHtmlAttr(String(b.node || ''))}</td>
                  <td class="px-3 py-2 text-end">${escapeHtmlAttr(up)} MB/s</td>
                  <td class="px-3 py-2 text-end">${escapeHtmlAttr(down)} MB/s</td>
                  <td class="px-3 py-2 text-end">${escapeHtmlAttr(ttfb)} ms</td>
                  <td class="px-3 py-2">${statusPill(b)}</td>
               </tr>`;
         }).join('');

         if (rows.length > shown.length) {
            showMoreBtn.classList.remove('is-hidden');
            showMoreBtn.textContent = `Show more (${shown.length}/${rows.length})`;
         } else {
            showMoreBtn.classList.add('is-hidden');
         }
      };

      const renderNode = (node) => {
         const rows = Array.isArray(groupedBenchmarks[node]) ? groupedBenchmarks[node] : [];
         currentNodeRows = rows;
         currentRowsLimit = defaultRows;
         renderTableRows();
         drawCharts(rows);
      };

      showMoreBtn.addEventListener('click', () => {
         currentRowsLimit += defaultRows;
         renderTableRows();
      });

      nodeSelect.addEventListener('change', () => renderNode(nodeSelect.value));
      if (!nodeSelect.value) nodeSelect.value = 'global';
      renderNode(nodeSelect.value);
   }

   function displayHostData(data, exchangeRate = null, currency) {
      function formatSCtoFiat(sc, decimals = 4, suffix = '') {
         const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
         const scValue = Number(sc || 0);
         const fiatRate = Number(exchangeRate);
         const fiatCurrency = String(currency || 'eur').toUpperCase();
         const scText = `${scValue.toLocaleString(loc, { minimumFractionDigits: decimals, maximumFractionDigits: decimals })} SC${suffix}`;

         // For a storage-network-first presentation, keep fiat primary and SC as secondary.
         if (Number.isFinite(fiatRate) && fiatRate > 0 && String(currency || '').toLowerCase() !== 'sc') {
            const fiatValue = scValue * fiatRate;
            const fiatText = `${fiatCurrency} ${fiatValue.toLocaleString(loc, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}${suffix}`;
            return `<span class="value-primary">${fiatText}</span><span class="value-secondary">${scText}</span>`;
         }

         if (window.currencyDisplay && typeof window.currencyDisplay.formatFiatWithScTooltip === 'function') {
            return window.currencyDisplay.formatFiatWithScTooltip({
               scValue: scValue,
               currency: currency,
               rate: exchangeRate,
               decimals: decimals,
               scDecimals: decimals,
               suffix: suffix
            });
         }

         return scText;
      }

      // Responsive helpers for hostAverages table
      function isMobile() { return window.innerWidth < 768; }
      function showTable(el){ if (el) el.classList.remove('table-loading'); }
      function renderAveragesHeader(){
         const thead = document.querySelector('#hostAverages thead');
         if (!thead) return;
         if (isMobile()) {
            thead.innerHTML = `
               <tr>
                 <th class="px-4 py-2">Metric</th>
                 <th class="px-4 py-2">Average</th>
               </tr>`;
         } else {
            thead.innerHTML = `
               <tr>
                 <th class="px-4 py-2">Metric</th>
                 <th class="px-4 py-2">Average</th>
                 <th class="px-4 py-2 text-right">This Host</th>
               </tr>`;
         }
      }

      function populateHostAverages(){
         const tbl = document.getElementById('hostAverages');
         const body = document.getElementById('hostAveragesBody');
         if (!tbl || !body) return;
         renderAveragesHeader();
         const avg = data.segment_averages || {};
         const settings = data.settings || {};
         const ratioPercent = (num, den) => {
            const n = Number(num);
            const d = Number(den);
            if (!Number.isFinite(n) || !Number.isFinite(d) || d === 0) return null;
            return (n / d) * 100;
         };
             const averageMoney = (value, divisor, decimals, suffix = '') => {
                  const raw = sc_value(value);
                  return raw == null ? 'Unavailable' : formatSCtoFiat(Number(raw) / divisor, decimals, suffix);
             };
         const rows = [];
         const lines = [
           {
             label: 'Contract price',
                   avgText: averageMoney(avg.contractprice, 1e24, 2),
             pct: ratioPercent(sc_value(settings.contractprice), sc_value(avg.contractprice))
           },
           {
             label: 'Storage price',
                   avgText: averageMoney(avg.storageprice, 1e12 / 4320, 2, '/TB/Month'),
             pct: ratioPercent(sc_value(settings.storageprice), sc_value(avg.storageprice))
           },
           {
             label: 'Upload price',
                   avgText: averageMoney(avg.uploadprice, 1e12, 2, '/TB'),
             pct: ratioPercent(sc_value(settings.ingressprice), sc_value(avg.uploadprice))
           },
           {
             label: 'Egress price',
                   avgText: averageMoney(avg.downloadprice, 1e12, 2, '/TB'),
             pct: ratioPercent(sc_value(settings.egressprice), sc_value(avg.downloadprice))
           },
           {
             label: 'Stored data',
                   avgText: avg.used_storage == null ? 'Unavailable' : `${(avg.used_storage / 1e12).toFixed(2)} TB`,
             pct: ratioPercent(data.used_storage, avg.used_storage)
           }
         ];
         const mobile = isMobile();
         lines.forEach((l, idx) => {
            const zebra = idx % 2 === 0 ? 'bg-gray-800' : 'bg-gray-900';
            const pctText = (l.pct === null) ? 'N/A' : `${l.pct.toFixed(0)}%`;
            if (mobile) {
               rows.push(`
                 <tr class="${zebra}">
                   <td class="px-4 py-2 font-semibold">${l.label}</td>
                   <td class="px-4 py-2">
                     ${l.avgText}
                     <div class="text-xs text-gray-300 mt-1">This host: ${pctText}</div>
                   </td>
                 </tr>`);
            } else {
               rows.push(`
                 <tr class="${zebra}">
                   <td class="px-4 py-2 font-semibold">${l.label}</td>
                   <td class="px-4 py-2">${l.avgText}</td>
                   <td class="px-4 py-2 text-right">${pctText}</td>
                 </tr>`);
            }
         });
         body.innerHTML = rows.join('');
         showTable(tbl);
      }

      // Helper to unwrap SC values shaped like { sc: number }
      function sc_value(v){ return (v && typeof v === 'object' && 'sc' in v) ? v.sc : v; }
      const hostStatsPricing = document.getElementById("hostStatsPricing");
      //const resultsSection = document.getElementById("resultsSection");

      if (hostStatsPricing) hostStatsPricing.innerHTML = "";
      if (data && Object.keys(data).length > 0) {
         renderHostStatusChips(data);
         renderAcceptingContractsWarning(data);
         renderHostSummary(data);
         const storagePriceRaw = Number(sc_value(data.settings.storageprice) ?? 0);
         const collateralRaw = Number(sc_value(data.settings.collateral) ?? 0);
         const collateralRatio = storagePriceRaw > 0 ? (collateralRaw / storagePriceRaw) : NaN;

         const hostPricingRows = [
            {
               id: 'storage_price',
               label: 'Storage price',
               value: () => formatSCtoFiat(((data.settings.storageprice.sc ?? data.settings.storageprice) / 1e12) * 4320, 6, '/TB/Month'),
               warningWhen: () => storagePriceRaw <= 0,
               warningText: () => 'Storage price should not be zero.',
               warningClass: 'text-warning'
            },
            { id: 'ingress_price', label: 'Ingress price', value: () => formatSCtoFiat(((data.settings.ingressprice.sc ?? data.settings.ingressprice) / 1e12), 4, '/TB') },
            { id: 'egress_price', label: 'Egress price', value: () => formatSCtoFiat(((data.settings.egressprice.sc ?? data.settings.egressprice) / 1e12), 4, '/TB') },
            { id: 'contract_price', label: 'Contract price', value: () => formatSCtoFiat(((data.settings.contractprice.sc ?? data.settings.contractprice) / 1e24), 4) },
            { id: 'sector_access_price', label: 'Sector access price', value: () => formatSCtoFiat(((data.settings.freesectorprice.sc ?? data.settings.freesectorprice) / 1e18), 4, '/million') },
            {
               id: 'collateral',
               label: 'Collateral',
               value: () => ((data.settings.collateral.sc ?? data.settings.collateral) / (data.settings.storageprice.sc ?? data.settings.storageprice)).toFixed(2) + '× storage price',
               warningWhen: () => collateralRaw <= 0 || !isFinite(collateralRatio) || collateralRatio < 2,
               warningText: () => 'Collateral minimum requirement is 2x storage price and it must be greater than zero.',
               warningClass: 'text-warning'
            },
            {
               id: 'max_collateral',
               label: 'Max collateral',
               value: () => formatSCtoFiat(((data.settings.maxcollateral.sc ?? data.settings.maxcollateral) / 1e24), 4)
            },
            { id: 'max_contract_duration', label: 'Max contract duration', value: () => (data.settings.maxduration / 4320).toFixed(0) + ' Months' }
         ];

         const renderRows = (rows, targetEl, startIndex = 0) => {
            if (!targetEl) return startIndex;
            let rowIndex = startIndex;
            rows.forEach((rowDef) => {
            const zebra = rowIndex % 2 === 0 ? 'bg-gray-800' : 'bg-gray-900';
            const value = rowDef.value(data);
            const showWarning = typeof rowDef.warningWhen === 'function' ? rowDef.warningWhen(data) : false;
            const warningHtml = showWarning && typeof rowDef.warningHtml === 'function'
               ? String(rowDef.warningHtml(data)).replace(/&/g, '&amp;').replace(/"/g, '&quot;')
               : '';
            const warningText = showWarning && !warningHtml
               ? escapeHtmlAttr(typeof rowDef.warningText === 'function' ? rowDef.warningText(data) : (rowDef.warningText || ''))
               : '';
            const warningClass = rowDef.warningClass || 'text-danger';
            const warningIcon = rowDef.warningIcon || 'bi bi-info-circle';
            const warningStyle = rowDef.warningStyle || 'default';
            let renderedValue = value;
            if (showWarning && warningStyle === 'critical') {
               renderedValue = `<span class="badge bg-danger text-white fw-semibold">${value}</span>
                  <span class="ms-2 text-danger align-middle" data-bs-toggle="tooltip" data-bs-placement="top" ${warningHtml ? 'data-bs-html="true"' : ''} title="${warningHtml || warningText}" aria-label="${escapeHtmlAttr(rowDef.label)} warning">
                     <i class="${warningIcon}"></i>
                  </span>`;
            } else if (showWarning) {
               renderedValue = `<span class="${warningClass}">${value}</span>
                  <span class="ms-1 ${warningClass} align-middle" data-bs-toggle="tooltip" data-bs-placement="top" ${warningHtml ? 'data-bs-html="true"' : ''} title="${warningHtml || warningText}" aria-label="${escapeHtmlAttr(rowDef.label)} warning">
                     <i class="${warningIcon}"></i>
                  </span>`;
            }
            const row = `
              <tr class="${zebra}">
                <th scope="row" class="px-3 py-2 host-stats-label">${rowDef.label}</th>
                <td class="px-3 py-2 text-end host-stats-value">${renderedValue}</td>
              </tr>`;
            targetEl.innerHTML += row;
            rowIndex++;
         });
            return rowIndex;
         };

         renderRows(hostPricingRows, hostStatsPricing, 0);

         if (hostStatsPricing) initTooltips(hostStatsPricing);

         // Populate averages responsively
         populateHostAverages();

         /*
          * Connectivity panel rendering is commented out for now.
          * When re-enabling, restore the block that populates #connectionStatus
          * using troubleshootData.
          */
      }


   }
   function copyToClipboard(text, button) {
      navigator.clipboard.writeText(text).then(() => {
         if (button) {
            button.textContent = '✓';
            button.dataset.state = 'done';
            setTimeout(() => {
               button.textContent = '⧉';
               delete button.dataset.state;
            }, 2000);
         }
         showToast("Copied to clipboard!");
      }).catch(err => {
         if (button) {
            button.textContent = '!';
            button.dataset.state = 'error';
         }
         console.error("Error copying text: ", err);
      });
   }

  let toastTimer = null;
  function showToast(message) {
     const toast = document.getElementById("toast");
     toast.textContent = message;
     toast.classList.remove("is-hidden");

      // Hide after 2 seconds
     if (toastTimer) clearTimeout(toastTimer);
     toastTimer = setTimeout(() => {
        toast.classList.add("is-hidden");
     }, 2000);
  }

   async function fetchCurrentRate(curr) {
      exchangeRate = null;
      if (curr === 'sc') {
         exchangeRate = 1;
         window.hostExchangeRate = exchangeRate;
         return;
      }
      try {
         // Forecasts and current host values use the site's current coin price,
         // never a rate selected from the host's historical reporting window.
         const metrics = await fetchWithCache('/api/v1/daily/compare_metrics', {}, 3600000);
         const rate = Number(metrics?.actual?.coin_price?.[curr]);
         if (Number.isFinite(rate) && rate > 0) exchangeRate = rate;
      } catch (err) {
         console.warn('Failed to fetch exchange rate:', err);
      }
      window.hostExchangeRate = exchangeRate;
   }
   // Call the initMap function when the page has finished loading
   document.addEventListener("DOMContentLoaded", async function () {
      initTooltips(document);
      setupHostTabHashRouting();
      setupBenchmarksTab();
      const pageCurrency = "<?php echo strtolower($currencyCookie); ?>";
      await fetchCurrentRate(pageCurrency);
      initMap();
      displayHostData(hostdata, exchangeRate, pageCurrency);

   });

  document.addEventListener("currencyChange", async function(e) {
     const currency = e.detail.toLowerCase();
     await fetchCurrentRate(currency);
     displayHostData(hostdata, exchangeRate, currency);

   });

</script>
<?php render_footer(['js/dashboard/ranges.js', 'js/dashboard/sources.js', 'js/dashboard/host-sources.js', 'js/dashboard/dashboard.js', 'js/renter-format.js', 'js/renter-currency.js', 'js/active-contracts.js']); ?>
