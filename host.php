<?php
require_once 'bootstrap.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';
include_once 'include/config.php';
require_once 'include/layout.php';

function render_score($score) {
    $score = max(0, min(10, round($score)));
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
// Troubleshooter cache key (based on net address)
$troubleshooterCacheKey = 'host_troubleshooter:' . $hostdata['net_address'];
$troubleshooterCacheResult = json_decode(Cache::getCache($troubleshooterCacheKey), true);

// Try to get troubleshooter data from cache, else fetch it
if (!$troubleshooterCacheResult) {
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

      <div class="flex justify-start mt-4 mb-2 gap-2">
         <a class="hover:underline cursor-pointer flex items-center font-bold text-xl" href='/host_explorer'>Top
            Hosts</a>
         <span class="flex items-center font-bold text-xl ">/</span>
         <span class="flex items-center font-bold text-xl"><?php echo htmlspecialchars($hostdata['net_address'], ENT_QUOTES, 'UTF-8'); ?></span>
      </div>

      <div class="sg-container__row">
         <div class="sg-container__row-content">
            <div class="sg-container__column sg-container__column--half">
               <section class="card w-full overflow-x-auto">
                  <h2 class="card__heading">Host stats</h2>
                  <div class="card__content">
                     <table id="storageStatsTable" class="table table-dark table-clean text-white w-100 border-collapse">
                        <tbody id="hostStats"></tbody>
                     </table>

                     <div class="flex justify-center items-center mt-3">
                        <a class="cursor-pointer hover:underline text-blue-500 font-bold rounded" data-bs-toggle="modal"
                           data-bs-target="#subscribeModal">
                           🔔 Subscribe to alerts
                        </a>
                     </div>
                  </div>
               </section>
            </div>
            <div class="sg-container__column sg-container__column--half">
               <div class="sg-stack">
               <section class="card">
                  <div class="card__content">
                     <div id="map" style="width:100%;height:16rem;"></div>
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
                  <h2 class="card__heading">HostScore Benchmarks</h2>
                  <div class="card__content">
                     <table id="hostscoreBenchmarks" class="table table-dark table-clean text-white w-100 border-collapse">
                        <tbody>
                           <tr class="bg-gray-800">
                              <td class="px-4 py-2 font-semibold">Final score</td>
                              <td class="px-4 py-2 text-right">
                                 <?php echo render_score(end($hostdata['node_scores']['global'])['total_score'] ?? 0); ?>
                              </td>
                           </tr>
                           <tr class="bg-gray-900">
                              <td class="px-4 py-2 font-semibold">Time to First Byte</td>
                              <td class="px-4 py-2 text-right">
                                 <?php echo round($hostdata['benchmark']['ttfb'] / 1000 / 1000, 2) . " ms "; ?>
                                 <span class="ms-1"><?php echo render_score(end($hostdata['node_scores']['global'])['ttfb_score'] ?? 0); ?></span>
                              </td>
                           </tr>
                           <tr class="bg-gray-800">
                              <td class="px-4 py-2 font-semibold">Renter upload</td>
                              <td class="px-4 py-2 text-right">
                                 <?php echo round($hostdata['benchmark']['upload_speed'] / 1000 / 1000, 2) . " MB/s "; ?>
                                 <span class="ms-1"><?php echo render_score(end($hostdata['node_scores']['global'])['upload_score'] ?? 0); ?></span>
                              </td>
                           </tr>
                           <tr class="bg-gray-900">
                              <td class="px-4 py-2 font-semibold">Renter download</td>
                              <td class="px-4 py-2 text-right">
                                 <?php echo round($hostdata['benchmark']['download_speed'] / 1000 / 1000, 2) . " MB/s "; ?>
                                 <span class="ms-1"><?php echo render_score(end($hostdata['node_scores']['global'])['download_score'] ?? 0); ?></span>
                              </td>
                           </tr>
                        </tbody>
                     </table>
                     <div class="flex justify-center items-center">
                        <a id='recentbenchmarks' class="cursor-pointer hover:underline text-blue-500 font-bold rounded"
                           href='/host_benchmarks?id=<?php echo $host_id; ?>'>View recent benchmarks</a>
                     </div>
                  </div>
               </section>

               <section class="card">
                  <h2 class="card__heading">Averages of hosts with
                     final score <?php echo (end($hostdata['node_scores']['global'])['total_score'] ?? 0); ?></h2>
                  <div class="card__content">
                     <table id="hostAverages" class="table table-dark table-clean text-white w-100 border-collapse" style="visibility: hidden;">
                        <thead></thead>
                        <tbody id="hostAveragesBody"></tbody>
                     </table>
                  </div>
               </section>
               </div>
            </div>
         </div>
      </div>

      <div class="sg-container__row mt-4">
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
      <div class="sg-container__row mt-4">
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
   <div id="toast" class="fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded bg-gradient shadow-lg hidden z-50">
      Copied to clipboard!
   </div>
   <!-- Subscription Modal -->
   <div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered">
         <div class="modal-content bg-dark text-white">
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
                     <div id="telegramInstructions" class="form-text text-muted mt-1 d-none">
                        Start a chat with <a href="https://t.me/Siagraph_bot"
                           target="_blank"><strong>@Siagraph_bot</strong></a> and type <code>/start</code> to get your
                        chat ID.
                     </div>

                  </div>
                  <div id="subscriptionStatus" class="mt-2 text-center small"></div>
               </div>
               <div class="modal-footer p-0 pt-2 justify-content-between">
                  <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                  <button type="button" class="btn btn-primary btn-sm" id="submitSubscriptionBtn">Subscribe</button>

               </div>
            </form> <!-- ✅ Proper form closing -->
         </div>
      </div>
   </div>

   <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
   <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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

      const hostStats = document.getElementById("hostStats");
      //const resultsSection = document.getElementById("resultsSection");

      hostStats.innerHTML = "";
      if (data && Object.keys(data).length > 0) {
         // Start with the common structure
         const structuredData = {
            "SiaGraph ID": data.host_id,
            "Netaddress": data.net_address,
            "Public Key": `<span>${data.public_key.substring(0, 25)}...</span>
                <button class='btn btn-sm btn-outline-light ms-2' aria-label='Copy public key'
                onclick='copyToClipboard("${data.public_key}")'>Copy</button>`,
            "V2": data.v2 ? 'Yes' : 'No',
            "Online": data.online ? 'Yes' : 'No',
            "Accepting contracts": data.settings.acceptingcontracts ? 'Yes' : 'No',
            "First seen": data.first_seen,
            "Last announced": data.last_announced,
            "Country": data.country,
            "Software version": data.software_version,
            "Protocol version": data.protocol_version,
            "Used storage": (data.used_storage / 1e12).toFixed(2) + " TB",
            "Total storage": (data.total_storage / 1e12).toFixed(2) + " TB",
            "Storage price": formatSCtoFiat(((data.settings.storageprice.sc ?? data.settings.storageprice) / 1e12) * 4320, 6) + "/TB/Month",
            "Ingress price": formatSCtoFiat(((data.settings.ingressprice.sc ?? data.settings.ingressprice) / 1e12)) + "/TB",
            "Egress price": formatSCtoFiat(((data.settings.egressprice.sc ?? data.settings.egressprice) / 1e12)) + "/TB",
            "Contract price": ((data.settings.contractprice.sc ?? data.settings.contractprice) / 1e24).toFixed(4) + " SC",
            "Sector access price": ((data.settings.freesectorprice.sc ?? data.settings.freesectorprice) / 1e18).toFixed(4) + " SC/million",
            "Collateral": ((data.settings.collateral.sc ?? data.settings.collateral) / (data.settings.storageprice.sc ?? data.settings.storageprice)).toFixed(2) + "× storage price",
            "Max collateral": ((data.settings.maxcollateral.sc ?? data.settings.maxcollateral) / 1e24).toFixed(4) + " SC",
            "Max contract duration": (data.settings.maxduration / 4320).toFixed(0) + " Months"
         };
         // Append additional fields based on whether V2 is true or false
         if (!data.v2) {
            // V1
            Object.assign(structuredData, {
               "Base RPC price": (data.settings.baserpcprice.sc ?? data.settings.baserpcprice),
               "Emperheral Account Expiry": data.settings.ephemeralaccountexpiry,
               "Max Download Batch Size": data.settings.maxdownloadbatchsize / 1024 / 1024 + "MB",
               "Max ephemeral account balance": (data.settings.max_ephemeral_account_balance / 1e24).toFixed(4) + " SC",
               "maxrevisebatchsize": data.settings.maxrevisebatchsize,
               "Sector size": data.settings.sectorsize.toLocaleString(window.APP_LOCALE || undefined) + " bytes",
               "siamuxport": data.settings.siamuxport,
               "Window size": data.settings.windowsize + " Blocks"
            });


         }
         let rowIndex = 0;
         for (const key in structuredData) {
            const row = `
              <tr>
                <th scope="row" class="px-3 py-2">${key}</th>
                <td class="px-3 py-2 text-end">${structuredData[key]}</td>
              </tr>`;
            hostStats.innerHTML += row;
            rowIndex++;
         }

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

  function showToast(message) {
     const toast = document.getElementById("toast");
     toast.textContent = message;
     toast.classList.remove("hidden");

      // Hide after 2 seconds
     setTimeout(() => {
        toast.classList.add("hidden");
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

      function updateFormFields() {
         const selectedService = serviceInput.value.trim();

         // Toggle Telegram instructions
         if (selectedService === "telegram") {
            telegramInstructions.classList.remove("d-none");
            recipientInput.placeholder = "Telegram Chat ID (e.g. 12345678)";
         } else if (selectedService === "pushover") {
            telegramInstructions.classList.add("d-none");
            recipientInput.placeholder = "Pushover user token";
         } else {
            telegramInstructions.classList.add("d-none");
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
