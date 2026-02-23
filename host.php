<?php
require_once 'bootstrap.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';
include_once 'include/config.php';
require_once 'include/layout.php';

function render_score($score) {
    $score = max(0, min(10, ceil($score)));
    $hue = ($score / 10) * 120; // 0 = red, 120 = green
    return '<span class="score" style="color: hsl(' . $hue . ', 70%, 50%);">' . $score . '</span>';
}

function sc_value($val) {
    return is_array($val) ? ($val['sc'] ?? reset($val)) : $val;
}

use Siagraph\Utils\Cache;
$currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur';
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
         Cache::setCache(json_encode($hostdata), $hostCacheKey, 'hour');
      } catch (Exception $e) {
         // ignore cache errors
      }
   } catch (Exception $err) {
      die("Error fetching host data: " . $err->getMessage());
   }
} else {
   $hostdata = $hostCacheResult;
}
$parts = explode(':', $hostdata['net_address']);
$assumed_rhp4_port = end($parts) + 2;
$hostscorePublicKeyId = preg_replace('/^ed25519:/', '', $hostdata['public_key'] ?? '');
// Troubleshooter cache key (based on net address)
$troubleshooterCacheKey = 'host_troubleshooter:' . $hostdata['net_address'];
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


<?php render_header('SiaGraph Host Explorer - ' . htmlspecialchars($hostdata['net_address'], ENT_QUOTES, 'UTF-8')); ?>
   <!-- Main Content Section -->
   <section id="main-content" class="sg-container">

      <div class="flex justify-between items-center flex-wrap">
         <div class="flex justify-start gap-2 items-center">
            <a class="hover:underline cursor-pointer flex items-center font-medium text-lg" href='/host_explorer'>Top
               Hosts</a>
            <span class="flex items-center font-medium text-lg ">/</span>
            <span class="flex items-center font-bold text-xl"><?php echo htmlspecialchars($hostdata['net_address'], ENT_QUOTES, 'UTF-8'); ?></span>
         </div>
         <div class="flex flex-col sm:flex-row items-start sm:items-center gap-2 w-full sm:w-auto sm:justify-end">
            <span class="text-xs sm:text-sm text-gray-300">Subscribe to receive alerts about this host</span>
            <a class="btn btn-sm btn-brand flex items-center" data-bs-toggle="modal" data-bs-target="#subscribeModal">
               🔔 Subscribe
            </a>
         </div>
      </div>

      <div class="sg-container__row">
         <div class="sg-container__row-content host-top-columns">
            <div class="sg-container__column sg-container__column--half">
               <section class="card w-full">
                  <h2 class="card__heading">Host stats</h2>
                  <div class="card__content">
                     <div class="table-responsive">
                        <table id="storageStatsTable" class="table table-dark table-clean text-white w-100 border-collapse host-stats-table">
                           <tbody id="hostStats"></tbody>
                        </table>
                     </div>

                  </div>
               </section>
            </div>
            <div class="sg-container__column sg-container__column--half">
               <div class="sg-stack">
               <section class="card">
                  <div class="card__content">
                     <div id="map" style="width:100%;height:18rem;"></div>
                  </div>
               </section>

               <!--
               TODO: Connectivity panel (commented out for future implementation)
               <section class="card mb-8">
                  <h2 class="card__heading">Connectivity</h2>
                  <div class="card__content">
                     <div id="connectionStatus"></div>
                  </div>
               </section>
               -->

               <section class="card">
                  <h2 class="card__heading flex items-center gap-2">
                     HostScore Benchmarks
                     <i class="bi bi-info-circle text-gray-300 text-sm" data-bs-toggle="tooltip" data-bs-placement="top"
                        title="These benchmarks are sourced from hostscore.info.
Hosts are scored by percentile: the top 10% receive 10, the next 10% receive 9, and so on.
Missing benchmarks reduce the score, meaning a host with the same average could have a different score.
Each benchmark server contributes equally to the score, regardless of how many benchmarks it produced.
"></i>
                  </h2>
                  <div class="card__content">
                     <div class="table-responsive">
                        <table id="hostscoreBenchmarks" class="table table-dark table-clean text-white w-100 border-collapse">
                           <tbody>
                           <tr class="bg-gray-700">
                              <td class="px-4 py-2 font-semibold">Final score</td>
                              <td class="px-4 py-2 text-right text-lg">
                                 <span class="inline-flex items-center"><?php echo render_score(end($hostdata['node_scores']['global'])['total_score'] ?? 0); ?></span>
                              </td>
                           </tr>
                           <tr class="bg-gray-900">
                              <td class="px-4 py-2 font-semibold">
                                 <span data-bs-toggle="tooltip" data-bs-placement="top" title="Lower is better. Time until first response byte.">Time to First Byte</span>
                              </td>
                              <td class="px-4 py-2 text-right">
                                 <span class="inline-flex items-center gap-1 justify-end">
                                    <span><?php echo round($hostdata['benchmark']['ttfb'] / 1000 / 1000, 1) . " ms"; ?></span>
                                    <span><?php echo render_score(end($hostdata['node_scores']['global'])['ttfb_score'] ?? 0); ?></span>
                                 </span>
                              </td>
                           </tr>
                           <tr class="bg-gray-800">
                              <td class="px-4 py-2 font-semibold">
                                 <span data-bs-toggle="tooltip" data-bs-placement="top" title="Ingress bandwidth from renter to host.">Ingress</span>
                              </td>
                              <td class="px-4 py-2 text-right">
                                 <span class="inline-flex items-center gap-1 justify-end">
                                    <span><?php echo round($hostdata['benchmark']['upload_speed'] / 1000 / 1000, 2) . " MB/s"; ?></span>
                                    <span><?php echo render_score(end($hostdata['node_scores']['global'])['upload_score'] ?? 0); ?></span>
                                 </span>
                              </td>
                           </tr>
                           <tr class="bg-gray-900">
                              <td class="px-4 py-2 font-semibold">
                                 <span data-bs-toggle="tooltip" data-bs-placement="top" title="Egress bandwidth from host to renter.">Egress</span>
                              </td>
                              <td class="px-4 py-2 text-right">
                                 <span class="inline-flex items-center gap-1 justify-end">
                                    <span><?php echo round($hostdata['benchmark']['download_speed'] / 1000 / 1000, 2) . " MB/s"; ?></span>
                                    <span><?php echo render_score(end($hostdata['node_scores']['global'])['download_score'] ?? 0); ?></span>
                                 </span>
                              </td>
                           </tr>
                           </tbody>
                        </table>
                     </div>
                     <div class="hostscore-actions mt-3 pt-2 border-t border-gray-700 w-full">
                        <a id='recentbenchmarks' class="button text-sm"
                           href='/host_benchmarks?id=<?php echo $host_id; ?>'>View recent benchmarks</a>
                        <a class="button text-sm" target="_blank" rel="noopener noreferrer"
                           href='https://hostscore.info/host/<?php echo rawurlencode($hostscorePublicKeyId); ?>'>View on HostScore</a>
                     </div>
                  </div>
               </section>

               <section class="card">
                  <h2 class="card__heading">Averages of hosts with
                     final score <?php echo (end($hostdata['node_scores']['global'])['total_score'] ?? 0); ?></h2>
                  <div class="card__content">
                     <div class="table-responsive">
                        <table id="hostAverages" class="table table-dark table-clean text-white w-100 border-collapse" style="visibility: hidden;">
                           <thead></thead>
                           <tbody id="hostAveragesBody"></tbody>
                        </table>
                     </div>
                  </div>
               </section>
               </div>
            </div>
         </div>
      </div>

      <div class="sg-container__row mt-2">
         <div class="sg-container__row-content">
            <div class="sg-container__column">
               <section id="graph-section" class="card">
                  <h2 class="card__heading">Daily storage stats</h2>
                  <div class="card__content">
                     <section class="graph-container">
                     <?php


                     renderGraph(
                        $canvasid = "dailystoragestats",
                        $datasets = [
                           $graphConfigs['used_storage'],
                           array_merge($graphConfigs['total_storage'], ['unit' => 'TB', 'unitDivisor' => 1e12])
                        ],
                        $dateKey = "date",
                        $jsonUrl = "/api/v1/host?id=" . $host_id, // JSON URL
                        $jsonData = $hostdata,
                        $charttype = 'line',

                        $interval = 'week',
                        $rangeslider = true,
                        $displaylegend = "true",
                        $defaultrangeinmonths = 6,
                        $displayYAxis = "false",
                        $unitType = 'bytes',
                        $jsonKey = 'dailydata',
                     );
                     ?>
                     </section>
                  </div>
               </section>
            </div>
         </div>
      </div>
      <div class="sg-container__row mt-2">
         <div class="sg-container__row-content">
            <div class="sg-container__column">
               <section id="graph-section2" class="card">
                  <h2 class="card__heading">Daily pricing stats</h2>
                  <div class="card__content">
                     <section class="graph-container">
                     <?php


                     renderGraph(
                        $canvasid = "dailypricestats",
                        $datasets = [
                           array_merge($graphConfigs['storage_price'], ['fiatUnit' => strtoupper($currencyCookie)]),
                           array_merge($graphConfigs['upload_price'], ['fiatUnit' => strtoupper($currencyCookie)]),
                           array_merge($graphConfigs['download_price'], ['fiatUnit' => strtoupper($currencyCookie)])
                        ],
                        $dateKey = "date",
                        $jsonUrl = "/api/v1/host?id=" . $host_id, // JSON URL
                        $jsonData = $hostdata,

                        $charttype = 'line',

                        $interval = 'week',
                        $rangeslider = true,
                        $displaylegend = "false",
                        $defaultrangeinmonths = 6,
                        $displayYAxis = "false",
                        // Honor cookie unit for axis formatting
                        $unitType = strtolower($currencyCookie),
                        $jsonKey = 'dailydata',
                        $height = 500,
                        $yAxisTitle = null,
                        $yScale = 'linear',
                        $stacked = false,
                        // Use fiat initially if cookie != SC, but defer initialization until rates ready
                        $useFiatInitial = strtolower($currencyCookie) !== 'sc',
                        $initAfterEvent = (strtolower($currencyCookie) !== 'sc') ? 'hostRatesReady' : null,
                     );
                     ?>
                  </section>
               </div>
            </section>
           </div>
        </div>
      </div>
   </section>
   <!-- Footer Section -->
   <div id="toast" class="bg-blue-600 text-white px-4 py-2 rounded bg-gradient shadow-lg" style="display:none; position:fixed; right:1rem; bottom:1rem; z-index:1080;">
      Copied to clipboard!
   </div>
   <!-- Subscription Modal -->
   <div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered">
         <div class="modal-content bg-dark text-white subscribe-modal-content">
            <div class="modal-header">
               <h5 class="modal-title" id="subscribeModalLabel">Subscribe to Alerts</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="subscribeForm"> <!-- ✅ Proper form opening -->
               <div class="modal-body">
                  <div class="mb-3">
                     <label for="service" class="form-label">Delivery Method</label>
                     <select class="form-select" id="service" required>
   <option value="email">Email</option>
   <option value="pushover">Pushover</option>
   <option value="telegram">Telegram</option>
</select>
                  </div>

                  <!-- Recipient Input -->
                  <div class="mb-3">
                     <label for="recipient" class="form-label">Recipient</label>
                     <input type="text" class="form-control" id="recipient" placeholder="you@example.com or user token"
                        required>
                     <!-- Telegram Instructions -->
                     <div id="telegramInstructions" class="form-text text-muted mt-1" style="display:none;">
                        Start a chat with <a href="https://t.me/Siagraph_bot"
                           target="_blank"><strong>@Siagraph_bot</strong></a> and type <code>/start</code> to get your
                        chat ID.
                     </div>

                  </div>
                  <div id="subscriptionStatus" class="mt-2 text-center small"></div>
               </div>
               <div class="modal-footer px-3 py-2 justify-content-between">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-sm btn-brand" id="submitSubscriptionBtn">Subscribe</button>

               </div>
            </form> <!-- ✅ Proper form closing -->
         </div>
      </div>
   </div>

   <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
   <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<style>
   #storageStatsTable.host-stats-table {
      table-layout: fixed;
      width: 100%;
   }

   #storageStatsTable.host-stats-table .host-stats-label {
      width: 28%;
      vertical-align: top;
      white-space: normal;
   }

   #storageStatsTable.host-stats-table .host-stats-value {
      width: 72%;
      vertical-align: top;
      min-width: 0;
      overflow-wrap: anywhere;
      word-break: break-word;
   }

   #storageStatsTable.host-stats-table .host-public-key-value {
      display: block;
      max-width: 100%;
      white-space: normal;
      overflow-wrap: anywhere;
      word-break: break-word;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
      font-size: 0.85rem;
      line-height: 1.35;
   }

   #storageStatsTable.host-stats-table .host-public-key-copy {
      display: inline-flex;
      margin-top: 0.35rem;
   }

   @media (max-width: 767.98px) {
      #storageStatsTable.host-stats-table .host-stats-label { width: 34%; }
      #storageStatsTable.host-stats-table .host-stats-value { width: 66%; }
   }

   @media (min-width: 1024px) {
      .host-top-columns > .sg-container__column.sg-container__column--half:first-child {
         width: calc(55% - 1rem);
         flex: 0 0 calc(55% - 1rem);
      }

      .host-top-columns > .sg-container__column.sg-container__column--half:last-child {
         width: calc(45% - 1rem);
         flex: 0 0 calc(45% - 1rem);
      }
   }
</style>

<script>
   let hostdata = <?php echo json_encode($hostdata); ?>;
   let exchangeRate = null;
   window.hostExchangeRate = null;
   window.hostHistoricalRates = null;
   window.hostStartIso = null;
   window.hostEndIso = null;
   // Function to initialize the map
   function initMap() {
      var locationString = "<?php echo htmlspecialchars($hostdata['location'], ENT_QUOTES, 'UTF-8'); ?>"; // Get the location string from PHP

      // Split the location string by comma
      var coordinates = locationString.split(',');

      // Assign latitude and longitude
      var latitude = coordinates[0];
      var longitude = coordinates[1];
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

   function displayHostData(data, exchangeRate = null, currency) {
      const currencySymbols = {
         'eur': '€',
         'usd': '$',
         'gbp': '£'
      };
      const symbol = currencySymbols[currency] || currency.toUpperCase();

      function formatSCtoFiat(sc, decimals = 4) {
         if (!exchangeRate || isNaN(exchangeRate)) return sc.toFixed(decimals) + " SC";
         return `${symbol}${(sc * exchangeRate).toFixed(2)}`;
      }

      // Responsive helpers for hostAverages table
      function isMobile() { return window.innerWidth < 768; }
      function showTable(el){ if (el) el.style.visibility = 'visible'; }
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
         const rows = [];
         const lines = [
           {
             label: 'Contract price',
             avgText: `${(sc_value(avg.contractprice) / 1e24).toFixed(2)} SC`,
             pct: (sc_value(settings.contractprice) / sc_value(avg.contractprice) * 100)
           },
           {
             label: 'Storage price',
             avgText: `${(sc_value(avg.storageprice) / 1e12 * 4320).toFixed(2)} SC/TB/Month`,
             pct: (sc_value(settings.storageprice) / sc_value(avg.storageprice) * 100)
           },
           {
             label: 'Upload price',
             avgText: `${(sc_value(avg.uploadprice) / 1e12).toFixed(2)} SC/TB`,
             pct: (sc_value(settings.ingressprice) / sc_value(avg.uploadprice) * 100)
           },
           {
             label: 'Egress price',
             avgText: `${(sc_value(avg.downloadprice) / 1e12).toFixed(2)} SC/TB`,
             pct: (sc_value(settings.egressprice) / sc_value(avg.downloadprice) * 100)
           },
           {
             label: 'Stored data',
             avgText: `${(avg.used_storage / 1e12).toFixed(2)} TB`,
             pct: (data.used_storage / avg.used_storage * 100)
           }
         ];
         const mobile = isMobile();
         lines.forEach((l, idx) => {
            const zebra = idx % 2 === 0 ? 'bg-gray-800' : 'bg-gray-900';
            if (mobile) {
               rows.push(`
                 <tr class="${zebra}">
                   <td class="px-4 py-2 font-semibold">${l.label}</td>
                   <td class="px-4 py-2">
                     ${l.avgText}
                     <div class="text-xs text-gray-300 mt-1">This host: ${l.pct.toFixed(0)}%</div>
                   </td>
                 </tr>`);
            } else {
               rows.push(`
                 <tr class="${zebra}">
                   <td class="px-4 py-2 font-semibold">${l.label}</td>
                   <td class="px-4 py-2">${l.avgText}</td>
                   <td class="px-4 py-2 text-right">${l.pct.toFixed(0)}%</td>
                 </tr>`);
            }
         });
         body.innerHTML = rows.join('');
         showTable(tbl);
      }

      // Helper to unwrap SC values shaped like { sc: number }
      function sc_value(v){ return (v && typeof v === 'object' && 'sc' in v) ? v.sc : v; }
      function ratioWithin(actual, expected, tolerance = 0.01) {
         if (!isFinite(actual) || !isFinite(expected) || expected === 0) return false;
         return Math.abs(actual - expected) <= tolerance;
      }

      const hostStats = document.getElementById("hostStats");
      //const resultsSection = document.getElementById("resultsSection");

      hostStats.innerHTML = "";
      if (data && Object.keys(data).length > 0) {
         const storagePriceRaw = Number(sc_value(data.settings.storageprice) ?? 0);
         const collateralRaw = Number(sc_value(data.settings.collateral) ?? 0);
         const maxCollateralRaw = Number(sc_value(data.settings.maxcollateral) ?? 0);
         const collateralRatio = storagePriceRaw > 0 ? (collateralRaw / storagePriceRaw) : NaN;
         const maxCollateralRatio = collateralRaw > 0 ? (maxCollateralRaw / collateralRaw) : NaN;

         const hostStatRows = [
            { id: 'host_id', label: 'SiaGraph ID', value: () => data.host_id },
            { id: 'netaddress', label: 'Netaddress', value: () => data.net_address },
            {
               id: 'public_key',
               label: 'Public Key',
               value: () => `<span class="host-public-key-value">${data.public_key}</span>
                <button class='btn btn-sm btn-outline-light host-public-key-copy' aria-label='Copy public key'
                onclick='copyToClipboard("${data.public_key}")'>Copy</button>`
            },
            { id: 'v2', label: 'V2', value: () => data.v2 ? 'Yes' : 'No' },
            { id: 'online', label: 'Online', value: () => data.online ? 'Yes' : 'No' },
            {
               id: 'accepting_contracts',
               label: 'Accepting contracts',
               value: () => data.settings.acceptingcontracts ? 'Yes' : 'No',
               warningWhen: d => !d.settings.acceptingcontracts,
               warningText: () => 'Only disable this while retiring a host. If it is disabled, renters can immediately stop using active contracts with this host.'
            },
            { id: 'first_seen', label: 'First seen', value: () => data.first_seen },
            { id: 'last_announced', label: 'Last announced', value: () => data.last_announced },
            { id: 'country', label: 'Country', value: () => data.country },
            { id: 'software_version', label: 'Software version', value: () => data.software_version },
            { id: 'protocol_version', label: 'Protocol version', value: () => data.protocol_version },
            { id: 'used_storage', label: 'Used storage', value: () => (data.used_storage / 1e12).toFixed(2) + ' TB' },
            { id: 'total_storage', label: 'Total storage', value: () => (data.total_storage / 1e12).toFixed(2) + ' TB' },
            {
               id: 'storage_price',
               label: 'Storage price',
               value: () => formatSCtoFiat(((data.settings.storageprice.sc ?? data.settings.storageprice) / 1e12) * 4320, 6) + '/TB/Month',
               warningWhen: () => storagePriceRaw <= 0,
               warningText: () => 'Storage price should not be zero.',
               warningClass: 'text-warning'
            },
            { id: 'ingress_price', label: 'Ingress price', value: () => formatSCtoFiat(((data.settings.ingressprice.sc ?? data.settings.ingressprice) / 1e12)) + '/TB' },
            { id: 'egress_price', label: 'Egress price', value: () => formatSCtoFiat(((data.settings.egressprice.sc ?? data.settings.egressprice) / 1e12)) + '/TB' },
            { id: 'contract_price', label: 'Contract price', value: () => ((data.settings.contractprice.sc ?? data.settings.contractprice) / 1e24).toFixed(4) + ' SC' },
            { id: 'sector_access_price', label: 'Sector access price', value: () => ((data.settings.freesectorprice.sc ?? data.settings.freesectorprice) / 1e18).toFixed(4) + ' SC/million' },
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
               value: () => (isFinite(maxCollateralRatio) ? `${maxCollateralRatio.toFixed(2)}× collateral` : 'N/A'),
               warningWhen: () => maxCollateralRaw <= 0 || !ratioWithin(maxCollateralRatio, 10, 0.01),
               warningText: () => 'Max collateral should be set to 10x collateral.',
               warningClass: 'text-warning'
            },
            { id: 'max_contract_duration', label: 'Max contract duration', value: () => (data.settings.maxduration / 4320).toFixed(0) + ' Months' }
         ];

         if (!data.v2) {
            hostStatRows.push(
               { id: 'base_rpc_price', label: 'Base RPC price', value: () => (data.settings.baserpcprice.sc ?? data.settings.baserpcprice) },
               { id: 'ephemeral_account_expiry', label: 'Emperheral Account Expiry', value: () => data.settings.ephemeralaccountexpiry },
               { id: 'max_download_batch_size', label: 'Max Download Batch Size', value: () => data.settings.maxdownloadbatchsize / 1024 / 1024 + 'MB' },
               { id: 'max_ephemeral_account_balance', label: 'Max ephemeral account balance', value: () => (data.settings.max_ephemeral_account_balance / 1e24).toFixed(4) + ' SC' },
               { id: 'max_revise_batch_size', label: 'maxrevisebatchsize', value: () => data.settings.maxrevisebatchsize },
               { id: 'sector_size', label: 'Sector size', value: () => data.settings.sectorsize.toLocaleString(window.APP_LOCALE || undefined) + ' bytes' },
               { id: 'siamux_port', label: 'siamuxport', value: () => data.settings.siamuxport },
               { id: 'window_size', label: 'Window size', value: () => data.settings.windowsize + ' Blocks' }
            );
         }

         let rowIndex = 0;
         hostStatRows.forEach((rowDef) => {
            const zebra = rowIndex % 2 === 0 ? 'bg-gray-800' : 'bg-gray-900';
            const value = rowDef.value(data);
            const showWarning = typeof rowDef.warningWhen === 'function' ? rowDef.warningWhen(data) : false;
            const warningText = showWarning
               ? escapeHtmlAttr(typeof rowDef.warningText === 'function' ? rowDef.warningText(data) : (rowDef.warningText || ''))
               : '';
            const warningClass = rowDef.warningClass || 'text-danger';
            const renderedValue = showWarning
               ? `<span class="${warningClass}">${value}</span>
                  <span class="ms-1 ${warningClass} align-middle" data-bs-toggle="tooltip" data-bs-placement="top" title="${warningText}" aria-label="${escapeHtmlAttr(rowDef.label)} warning">
                     <i class="bi bi-info-circle"></i>
                  </span>`
               : value;
            const row = `
              <tr class="${zebra}">
                <th scope="row" class="px-3 py-2 host-stats-label">${rowDef.label}</th>
                <td class="px-3 py-2 text-end host-stats-value">${renderedValue}</td>
              </tr>`;
            hostStats.innerHTML += row;
            rowIndex++;
         });

         initTooltips(hostStats);

         // Populate averages responsively
         populateHostAverages();

         /*
          * Connectivity panel rendering is commented out for now.
          * When re-enabling, restore the block that populates #connectionStatus
          * using troubleshootData.
          */
      }


   }
   function copyToClipboard(text) {
      navigator.clipboard.writeText(text).then(() => {
         showToast("Copied to clipboard!");
      }).catch(err => {
         console.error("Error copying text: ", err);
      });
   }

  let toastTimer = null;
  function showToast(message) {
     const toast = document.getElementById("toast");
     toast.textContent = message;
     toast.style.display = "block";

      // Hide after 2 seconds
     if (toastTimer) clearTimeout(toastTimer);
     toastTimer = setTimeout(() => {
        toast.style.display = "none";
     }, 2000);
  }

   async function fetchHistoricalRates(startIso, endIso) {
      window.hostHistoricalRates = {};
      try {
         const url = `/api/v1/daily/exchange_rate?start=${encodeURIComponent(startIso)}&end=${encodeURIComponent(endIso)}`;
         const json = await fetchWithCache(url);
         json.forEach(e => {
            window.hostHistoricalRates[e.date] = e;
         });
      } catch (err) {
         console.warn('Failed to fetch historical exchange rates:', err);
      }
   }

   async function fetchCurrentRate(curr) {
      exchangeRate = null;
      if (curr === 'sc') {
         exchangeRate = 1;
         window.hostExchangeRate = exchangeRate;
         return;
      }
      try {
         const text = await fetchWithCache(`https://explorer.siagraph.info/api/exchange-rate/siacoin/${curr}`, {}, 86400000, 'text');
         const rate = parseFloat(text);
         if (!isNaN(rate)) {
            exchangeRate = rate;
         }
      } catch (err) {
         console.warn('Failed to fetch exchange rate:', err);
      }
      window.hostExchangeRate = exchangeRate;
   }
  document.addEventListener("DOMContentLoaded", function () {
      const subscribeForm = document.getElementById("subscribeModal");
      const submitBtn = document.getElementById("submitSubscriptionBtn");

      const serviceInput = document.getElementById("service");
      const recipientInput = document.getElementById("recipient");
      const statusDiv = document.getElementById("subscriptionStatus");
      const telegramInstructions = document.getElementById("telegramInstructions");

      const publicKey = "<?php echo htmlspecialchars($hostdata['public_key'], ENT_QUOTES, 'UTF-8'); ?>";

      initTooltips(document);

      function updateFormFields() {
         const selectedService = serviceInput.value.trim();

         // Toggle Telegram instructions
         if (selectedService === "telegram") {
            telegramInstructions.style.display = "block";
            recipientInput.placeholder = "Telegram Chat ID (e.g. 12345678)";
         } else if (selectedService === "pushover") {
            telegramInstructions.style.display = "none";
            recipientInput.placeholder = "Pushover user token";
         } else {
            telegramInstructions.style.display = "none";
            recipientInput.placeholder = "you@example.com";
         }
      }

      // Run on load and on service change
      updateFormFields();
      serviceInput.addEventListener("change", updateFormFields);

      submitBtn.addEventListener("click", async function () {
         const service = serviceInput.value.trim();
         const recipient = recipientInput.value.trim();

         // Basic validation
         if (!service || !recipient) {
            statusDiv.textContent = "Please complete all fields.";
            statusDiv.classList.remove("text-success");
            statusDiv.classList.add("text-danger");
            return;
         }

         // Clear status
         statusDiv.textContent = "Submitting...";
         statusDiv.classList.remove("text-danger", "text-success");

         try {
            const response = await fetch("<?php echo $SETTINGS['siagraph_base_url']; ?>/api/v1/alerts/subscribe", {
               method: "POST",
               headers: {
                  "Content-Type": "application/json",
               },
               body: JSON.stringify({
                  public_key: publicKey,
                  service: service,
                  recipient: recipient,
               }),
            });

            const data = await response.json();

            if (response.ok) {
               statusDiv.textContent = "Successfully subscribed!";
               statusDiv.classList.remove("text-danger");
               statusDiv.classList.add("text-success");
               recipientInput.value = "";
               setTimeout(() => {
                  const modal = bootstrap.Modal.getInstance(document.getElementById('subscribeModal'));
                  if (modal) modal.hide();
               }, 1500);
            } else {
               statusDiv.textContent = data.error || "Subscription failed.";
               statusDiv.classList.remove("text-success");
               statusDiv.classList.add("text-danger");
            }
         } catch (err) {
            statusDiv.textContent = "An error occurred. Please try again.";
            statusDiv.classList.remove("text-success");
            statusDiv.classList.add("text-danger");
            console.error("Subscription error:", err);
         }
      });
   });
   // Call the initMap function when the page has finished loading
   document.addEventListener("DOMContentLoaded", async function () {
      const pageCurrency = "<?php echo strtolower($currencyCookie); ?>";
      const daily = hostdata.dailydata || [];
      if (daily.length) {
         const startIso = (daily[0].date.split('T')[0]) + 'T00:00:00Z';
         const endIso = (daily[daily.length - 1].date.split('T')[0]) + 'T00:00:00Z';
         window.hostStartIso = startIso;
         window.hostEndIso = endIso;
         await fetchHistoricalRates(startIso, endIso);
      }
      await fetchCurrentRate(pageCurrency);
      initMap();
      displayHostData(hostdata, exchangeRate, pageCurrency);
      // Re-render averages on breakpoint change
      (function setupResize(){
         let last = window.innerWidth < 768;
         window.addEventListener('resize', () => {
            const now = window.innerWidth < 768;
            if (now !== last){ last = now; populateHostAverages(); }
         });
      })();
      // Initialize the pricing graph appropriately based on currency
      if (pageCurrency === 'sc') {
         const getGraph = () => new Promise(resolve => {
            const check = () => {
               const g = window.graphInstances && window.graphInstances["dailypricestats"];
               if (g) return resolve(g);
               setTimeout(check, 50);
            };
            check();
         });
         const inst = await getGraph();
         inst.setCurrency('sc');
         inst.setFiat(false);
      } else {
         // Defer creation of the graph until historical rates are ready
         document.dispatchEvent(new CustomEvent('hostRatesReady', { detail: { currency: pageCurrency } }));
      }
   });

  document.addEventListener("currencyChange", async function(e) {
     const currency = e.detail.toLowerCase();
     if (window.hostStartIso && window.hostEndIso) {
        await fetchHistoricalRates(window.hostStartIso, window.hostEndIso);
     }
     await fetchCurrentRate(currency);
   displayHostData(hostdata, exchangeRate, currency);
   populateHostAverages();
      const getGraph = () => new Promise(resolve => {
         const check = () => {
            const g = window.graphInstances && window.graphInstances["dailypricestats"];
            if (g) return resolve(g);
            setTimeout(check, 50);
         };
         check();
      });
      const inst = await getGraph();
      inst.setCurrency(currency);
      inst.setFiat(currency !== 'sc');
   });

</script>
<?php render_footer(); ?>
