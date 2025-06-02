<?php
include_once "include/redis.php";
include_once 'include/graph.php';
include_once 'include/config.php';
$currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur';
$currency = strtolower($currencyCookie);

$host_id = $_GET["id"] ?? '';
$public_key = $_GET["public_key"] ?? '';

// Host main data cache key
$hostCacheKey = 'host' . http_build_query($_GET);
$hostCacheResult = json_decode(getCache($hostCacheKey), true);

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
$troubleshooterCacheResult = json_decode(getCache($troubleshooterCacheKey), true);

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
      // Optionally set to cache here
   } catch (Exception $err) {
      $troubleshootData = null; // or log error
   }
} else {
   $troubleshootData = $troubleshooterCacheResult;
}

?>


<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>SiaGraph Host Explorer - <?php echo $hostdata['net_address']; ?></title>
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <link href="https://cdn.jsdelivr.net/npm/tailwindcss@latest/dist/tailwind.min.css" rel="stylesheet">
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.css" crossorigin="anonymous" />
   <script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.js" crossorigin="anonymous"></script>
   <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
   <script src="script.js"></script>
   <!-- Import Moment.js library -->
   <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js"></script>
   <!-- Import Chart.js 3 library with Moment adapter -->
   <script src="https://cdn.jsdelivr.net/npm/chart.js@3"></script>
   <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1"></script>
   <script src="https://cdn.jsdelivr.net/npm/chartjs-plugin-annotation@1.0.2"></script>

</head>

<body>
   <!-- Header Section -->
   <?php include "include/header.html"; ?>
   <!-- Main Content Section -->
   <section id="main-content" class="container mt-4 pb-5">

      <div class="max-w-6xl mx-auto">
         <div class="flex justify-start mt-4 mb-2 gap-2">
            <a class="hover:underline cursor-pointer flex items-center font-bold text-xl" href='/host_explorer'>Top
               Hosts</a>
            <span class="flex items-center font-bold text-xl ">/</span>
            <span class="flex items-center font-bold text-xl"><?php echo $hostdata['net_address']; ?></span>
         </div>
         <?php if ($troubleshootData['port_status']['ipv4_rhp4'] === false): ?>
            <div class="mb-4 p-4 bg-red-400 border-l-4 border-red-500 text-red-700 rounded">
               ❌ Failed to connect to RHP4 via port <?php echo $assumed_rhp4_port; ?>. Make sure RHP4 is accessible before
               block height 526.000.
            </div>
         <?php endif; ?>
         <?php if (!str_starts_with($hostdata['software_version'], 'hostd v2.2')): ?>
            <div class="mb-4 p-4 bg-red-400 border-l-4 border-red-500 text-red-700 rounded">
               ❌ Hostd v2.2.0 or newer is required for the hardfork. Make sure to update to Hostd v2.2.0 or newer before block 
               height 526.000.
            </div>
         <?php endif; ?>

         <div class="grid grid-cols-1 md:grid-cols-2 flex justify-center gap-5">
            <!-- Added grid class here -->
            <!-- Left Section -->
            <div class="bg-white rounded-lg shadow-lg p-6 w-full overflow-x-auto max-w-xl">
               <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Host stats</h2>
               <table id="storageStatsTable" class="table w-full border-collapse block md:table">
                  <tbody id="hostStats"></tbody>
               </table>

               <div class="flex justify-center items-center mt-3">
                  <a class="cursor-pointer hover:underline text-blue-500 font-bold rounded" data-bs-toggle="modal"
                     data-bs-target="#subscribeModal">
                     🔔 Subscribe to alerts
                  </a>
               </div>
               </table>

               <!--<div id="connectionStatus" class="mt-4"></div> -->
            </div>
            <!-- Right Section -->
            <div class="w-full max-w-xl mx-auto md:mx-0">
               <!-- World Map Placeholder -->
               <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                  <!--<h2 class="text-2xl font-semibold mb-4">World Map</h2>-->
                  <div id="map" class="w-full h-64"></div>
               </div>



               <!-- Other Placeholder Section -->
               <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
                  <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">HostScore Benchmarks</h2>
                  <table id="hostscoreBenchmarks" class="table-auto min-w-full border-collapse">
                     <tbody>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Final score</td>
                           <td class="px-4 py-2 text-right">
                              <?php echo (end($hostdata['node_scores']['global'])['total_score'] ?? 0) . "/10"; ?>
                           </td>
                        </tr>
                        <tr class="bg-gray-100">
                           <td class="px-4 py-2 font-semibold">Time to First Byte</td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($hostdata['benchmark']['ttfb'] / 1000 / 1000, 2) . " ms (" . (end($hostdata['node_scores']['global'])['ttfb_score'] ?? 0) . "/10)"; ?>
                           </td>
                        </tr>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Renter upload</td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($hostdata['benchmark']['upload_speed'] / 1000 / 1000, 2) . " MB/s (" . (end($hostdata['node_scores']['global'])['upload_score'] ?? 0) . "/10)"; ?>
                           </td>
                        </tr>
                        <tr class="bg-gray-100">
                           <td class="px-4 py-2 font-semibold">Renter download</td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($hostdata['benchmark']['download_speed'] / 1000 / 1000, 2) . " MB/s (" . (end($hostdata['node_scores']['global'])['download_score'] ?? 0) . "/10)"; ?>
                           </td>
                        </tr>
                     </tbody>
                  </table>
                  <div class="flex justify-center items-center">
                     <a id='recentbenchmarks' class="cursor-pointer hover:underline text-blue-500 font-bold rounded"
                        href='/host_benchmarks?id=<?php echo $host_id; ?>'>View recent benchmarks</a>
                  </div>
               </div>

               <!-- Other Placeholder Section -->
               <div class="bg-white rounded-lg shadow-lg p-6">
                  <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Averages of hosts with
                     final score <?php echo (end($hostdata['node_scores']['global'])['total_score'] ?? 0); ?></h2>
                  <table id="hostAverages" class="table-auto min-w-full border-collapse">
                     <tbody>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Contract price</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($hostdata['segment_averages']['contractprice'] / pow(10, 24), 2); ?>SC
                           <td class="px-4 py-2 text-right">
                              <?php echo round(num: $hostdata['settings']['contractprice'] / $hostdata['segment_averages']['contractprice'] * 100); ?>%
                           </td>
                        </tr>
                        <tr class="bg-gray-100">
                           <td class="px-4 py-2 font-semibold">Storage price</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($hostdata['segment_averages']['storageprice'] / pow(10, 12) * 4320, 2); ?>
                              SC/TB/Month
                           </td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($hostdata['settings']['storageprice'] / $hostdata['segment_averages']['storageprice'] * 100); ?>%
                           </td>
                        </tr>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Upload price</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($hostdata['segment_averages']['uploadprice'] / pow(10, 12), 2); ?> SC/TB
                           </td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($hostdata['settings']['ingressprice'] / $hostdata['segment_averages']['uploadprice'] * 100); ?>%
                           </td>
                        </tr>
                        <tr class="bg-gray-100">
                           <td class="px-4 py-2 font-semibold">Egress price</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($hostdata['segment_averages']['downloadprice'] / pow(10, 12), 2); ?>
                              SC/TB
                           </td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($hostdata['settings']['egressprice'] / $hostdata['segment_averages']['downloadprice'] * 100); ?>%
                           </td>
                        </tr>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Stored data</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($hostdata['segment_averages']['used_storage'] / pow(10, 12), 2); ?> TB
                           </td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($hostdata['used_storage'] / $hostdata['segment_averages']['used_storage'] * 100); ?>%
                           </td>
                        </tr>
                     </tbody>
                  </table>
               </div>
            </div>
         </div>
         <section id="graph-section" class="bg-light p-3 rounded-3 mt-4 pb-5">
            <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Daily storage stats</h2>
            <section class="graph-container">
               <?php


               renderGraph(
                  $canvasid = "dailystoragestats",
                  $datasets = [
                     [
                        'label' => 'Utilized Storage',
                        'key' => 'used_storage', // Modify based on your data
                        'backgroundColor' => 'rgba(75, 192, 192, 0.2)',
                        'borderColor' => 'rgba(75, 192, 192, 1)',
                        'transform' => "return entry['used_storage'];",
                        'unit' => 'TB',
                        'unitDivisor' => 1e12,
                        'decimalPlaces' => 2,
                        'startAtZero' => true
                     ],
                     [
                        'label' => 'Total Storage',
                        'key' => 'total_storage', // Modify based on your data
                        'backgroundColor' => 'rgba(255, 99, 132, 0.2)',
                        'borderColor' => 'rgba(255, 99, 132, 1)',
                        'transform' => "return entry['total_storage'];",
                        'unit' => 'TB',
                        'unitDivisor' => 1e12,
                        'decimalPlaces' => 2,
                        'startAtZero' => true
                     ]

                  ],
                  $dateKey = "date",
                  $jsonUrl = "/api/v1/host?id=" . $host_id, // JSON URL
                  $jsonData = $cacheresult ?? null,
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
         </section>
         <section id="graph-section2" class="bg-light p-3 rounded-3 mt-4 pb-5">
            <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Daily pricing stats</h2>
            <section class="graph-container">
               <?php


               renderGraph(
                  $canvasid = "dailypricestats",
                  $datasets = [
                     [
                        'label' => 'Storage Price',
                        'key' => 'storage_price', // Modify based on your data
                        'backgroundColor' => 'rgba(255, 159, 64, 0.2)',
                        'borderColor' => 'rgba(255, 159, 64, 1)',
                        'transform' => "return (entry['storage_price'] / 1e12 * 4320);",
                        'unit' => '',
                        'decimalPlaces' => 2,
                        'startAtZero' => true
                     ],
                     [
                        'label' => 'Upload Price',
                        'key' => 'upload_price', // Modify based on your data
                        'backgroundColor' => 'rgba(153, 102, 255, 0.2)',
                        'borderColor' => 'rgba(153, 102, 255, 1)',
                        'transform' => "return (entry['upload_price'] / 1e12);",
                        'unit' => '',
                        'decimalPlaces' => 2,
                        'startAtZero' => true
                     ],
                     [
                        'label' => 'Egress Price',
                        'key' => 'egress_price', // Modify based on your data
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'transform' => "return (entry['egress_price'] / 1e12);",
                        'unit' => '',
                        'decimalPlaces' => 2,
                        'startAtZero' => true
                     ]

                  ],
                  $dateKey = "date",
                  $jsonUrl = "/api/v1/host?id=" . $host_id, // JSON URL
                  $jsonData = $cacheresult ?? null,

                  $charttype = 'line',

                  $interval = 'week',
                  $rangeslider = true,
                  $displaylegend = "false",
                  $defaultrangeinmonths = 6,
                  $displayYAxis = "false",
                  $unitType = 'SC',
                  $jsonKey = 'dailydata',
               );
               ?>
            </section>
         </section>
      </div>
      </div>
   </section>
   <!-- Footer Section -->
   <?php include "include/footer.php" ?>
   <div id="toast" class="fixed bottom-4 right-4 bg-blue-600 text-white px-4 py-2 rounded shadow-lg hidden z-50">
      Copied to clipboard!
   </div>
   <!-- Subscription Modal -->
   <div class="modal fade" id="subscribeModal" tabindex="-1" aria-labelledby="subscribeModalLabel" aria-hidden="true">
      <div class="modal-dialog modal-sm modal-dialog-centered">
         <div class="modal-content">
            <div class="modal-header">
               <h5 class="modal-title" id="subscribeModalLabel">Subscribe to Alerts</h5>
               <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="subscribeForm"> <!-- ✅ Proper form opening -->
               <div class="modal-body">
                  <div class="mb-3">
                     <label for="service" class="form-label">Delivery Method</label>
                     <select class="form-select" id="service" required>
                        <option value="email">📧 Email</option>
                        <option value="pushover">📲 Pushover</option>
                     </select>
                  </div>
                  <div class="mb-3">
                     <label for="recipient" class="form-label">Recipient</label>
                     <input type="text" class="form-control" id="recipient"
                        placeholder="you@example.com or user token" required>
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

</body>
<link href="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.js"></script>

</html>
<script>
   // Function to initialize the map
   function initMap() {
      var locationString = "<?php echo $hostdata['location']; ?>"; // Get the location string from PHP

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
         if (!exchangeRate) return sc.toFixed(decimals) + " SC";
         return `${symbol}${(sc * exchangeRate).toFixed(2)}`;
      }


      const hostStats = document.getElementById("storageStatsTable");
      //const resultsSection = document.getElementById("resultsSection");

      hostStats.innerHTML = "";
      if (data && Object.keys(data).length > 0) {
         // Start with the common structure
         const structuredData = {
            "SiaGraph ID": data.host_id,
            "Netaddress": data.net_address,
            "Public Key": `<span>${data.public_key.substring(0, 25)}...</span> 
                <button class='bg-blue-500 text-white px-2 py-1 rounded' 
                onclick='copyToClipboard("${data.public_key}")'>📋</button>`,
            "V2": data.v2,
            "Online": data.online,
            "Accepting contracts": data.settings.acceptingcontracts,
            "First seen": data.first_seen,
            "Last announced": data.last_announced,
            "Country": data.country,
            "Software version": data.software_version,
            "Protocol version": data.protocol_version,
            "Used storage": (data.used_storage / 1e12).toFixed(2) + " TB",
            "Total storage": (data.total_storage / 1e12).toFixed(2) + " TB",
            "Storage price": formatSCtoFiat((data.settings.storageprice / 1e12) * 4320, 6) + "/TB/Month",
            "Ingress price": formatSCtoFiat((data.settings.ingressprice / 1e12)) + "/TB",
            "Egress price": formatSCtoFiat((data.settings.egressprice / 1e12)) + "/TB",
            "Contract price": (data.settings.contractprice / 1e24).toFixed(4) + " SC",
            "Sector access price": (data.settings.freesectorprice / 1e18).toFixed(4) + " SC/million",
            "Collateral": (data.settings.collateral / data.settings.storageprice).toFixed(2) + "× storage price",
            "Max collateral": (data.settings.maxcollateral / 1e24).toFixed(4) + " SC",
            "Max contract duration": (data.settings.maxduration / 4320).toFixed(0) + " Months"

         };
         // Append additional fields based on whether V2 is true or false
         if (!data.v2) {
            // V1
            Object.assign(structuredData, {
               "Base RPC price": data.settings.baserpcprice,
               "Emperheral Account Expiry": data.settings.ephemeralaccountexpiry,
               "Max Download Batch Size": data.settings.maxdownloadbatchsize / 1024 / 1024 + "MB",
               "Max ephemeral account balance": (data.settings.max_ephemeral_account_balance / 1e24).toFixed(4) + " SC",
               "maxrevisebatchsize": data.settings.maxrevisebatchsize,
               "Sector size": data.settings.sectorsize.toLocaleString() + " bytes",
               "siamuxport": data.settings.siamuxport,
               "Window size": data.settings.windowsize + " Blocks"
            });


         }
         let rowIndex = 0;
         for (const key in structuredData) {
            const bgColor = rowIndex % 2 === 0 ? 'bg-gray-100' : 'bg-gray-200';
            const row = `
     <div class="w-full ${bgColor} px-4 py-2 flex flex-col md:flex-row md:items-center justify-between border-b">
       <div class="font-semibold text-sm md:text-base">${key}</div>
       <div class="text-left text-sm md:text-base mt-1 md:mt-0">${structuredData[key]}</div>
     </div>`;
            hostStats.innerHTML += row;
            rowIndex++;
         }

         //        resultsSection.style.display = "block";

         const troubleshootData = <?php echo json_encode($troubleshootData); ?>;

         const connectionChecks = {
            'Online': troubleshootData.online,
            'Accepting Contracts': troubleshootData.settings.acceptingcontracts,
            'IPv4 Enabled': troubleshootData.ipv4_enabled,
            'IPv4 RHP2': troubleshootData.port_status?.ipv4_rhp2,
            'IPv4 RHP3': troubleshootData.port_status?.ipv4_rhp3,
            'IPv4 RHP4': troubleshootData.port_status?.ipv4_rhp4,
            'IPv6 Enabled': troubleshootData.ipv6_enabled,
            'IPv6 RHP2': troubleshootData.port_status?.ipv6_rhp2,
            'IPv6 RHP3': troubleshootData.port_status?.ipv6_rhp3,
            'IPv6 RHP4': troubleshootData.port_status?.ipv6_rhp4
         };

         const ipv4Checks = ['IPv4 Enabled'];
         const ipv6Checks = ['IPv6 Enabled'];
         if (!troubleshootData.v2) {
            ipv4Checks.push('IPv4 RHP2', 'IPv4 RHP3');
            ipv6Checks.push('IPv6 RHP2', 'IPv6 RHP3');
         }
         ipv4Checks.push('IPv4 RHP4');
         ipv6Checks.push('IPv6 RHP4');

         const renderSection = (title, keys, gridCols = 1) => {
            const gridClass = gridCols > 1 ? `grid grid-cols-${gridCols} gap-2` : '';
            return `<div class="mb-3">
    <h5 class='font-semibold mb-2'>${title}</h5>
    <div class="${gridClass}">` +
               keys.map(k => {
                  const v = connectionChecks[k];
                  return `<div class='p-2 rounded ${v ? "bg-green-100 text-green-700" : "bg-red-100 text-red-700"}'>${v ? "✅" : "❌"} ${k}</div>`;
               }).join('') + '</div></div>';
         };

         document.getElementById('connectionStatus').innerHTML =
            `<div class="mb-4">
    ${renderSection('General', ['Online', 'Accepting Contracts'], 2)}
  </div>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>${renderSection('IPv4 Status', ipv4Checks, 1)}</div>
    <div>${renderSection('IPv6 Status', ipv6Checks, 1)}</div>
  </div>`;
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
   document.addEventListener("DOMContentLoaded", function () {
      const subscribeForm = document.getElementById("subscribeModal");
      const submitBtn = document.getElementById("submitSubscriptionBtn");

      const serviceInput = document.getElementById("service");
      const recipientInput = document.getElementById("recipient");
      const statusDiv = document.getElementById("subscriptionStatus");

      const publicKey = "<?php echo $hostdata['public_key']; ?>";

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
               // Optionally clear the form
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
      const hostdata = <?php echo json_encode($hostdata); ?>;
      const currency = "<?php echo $currencyCookie; ?>";
      let exchangeRate = null;
      try {
         const res = await fetch("https://explorer.siagraph.info/api/exchange-rate/siacoin/" + currency);
         const text = await res.text();
         exchangeRate = parseFloat(text);
      } catch (err) {
         console.warn("Failed to fetch exchange rate:", err);
      }
      initMap();
      displayHostData(hostdata, exchangeRate, currency);


   });

</script>