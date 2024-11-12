<?php
#TODO FIX DIVIDE BY ZERO CRASH IN SETTINGS

include_once "include/database.php";
include_once "include/redis.php";
include_once 'include/graph.php';
$cacheKey = 'host' . http_build_query($_GET);
$cacheresult = json_decode(getCache($cacheKey), true);
// Fetch host ID from URL
if (isset($_GET['id'])) {
   $host_id = $_GET['id'];
   $query = "SELECT * FROM Hosts WHERE host_id = $host_id";
} else if (isset($_GET['public_key'])) {
   $public_key = $_GET['public_key'];
   $query = "SELECT * FROM Hosts WHERE public_key = '$public_key'";
}
if (isset($query)) {
   // Perform the query
   $result = mysqli_query($mysqli, $query);


   $settings = mysqli_fetch_assoc($result);
   $public_key = $settings['public_key'];
   $host_id = $settings['host_id'];
   $benchmarkquery = "SELECT
       avg(download_speed) AS download_speed,
       avg(upload_speed) AS upload_speed,
       avg(ttfb) AS ttfb
       from Benchmarks where public_key= '$public_key' and timestamp >= UTC_TIMESTAMP() - INTERVAL 7 DAY";
   $benchmarkresult = mysqli_query($mysqli, $benchmarkquery);
   $benchmark = mysqli_fetch_assoc($benchmarkresult);

   $dailydataquery = "SELECT * from HostsDailyStats where public_key= '$public_key' order by date";
   $dailydataresult = mysqli_query($mysqli, $dailydataquery);

   $dailydata = array();
   while ($row = mysqli_fetch_assoc($dailydataresult)) {
      $dailydata[] = $row;
   }
   $benchmarkscorequery = "SELECT * from BenchmarkScores where public_key= '$public_key' order by date asc";
   $benchmarkscoreresult = mysqli_query($mysqli, $benchmarkscorequery);
   $node_scores = array();
   $node_scores['Global'] = array();
   while ($benchmarkscore = mysqli_fetch_assoc($benchmarkscoreresult)) {
      // Extract values from the current row
      $node = $benchmarkscore['node'];
      $download_score = $benchmarkscore['download_score'];
      $upload_score = $benchmarkscore['upload_score'];
      $ttfb_score = $benchmarkscore['ttfb_score'];
      $total_score = round($benchmarkscore['total_score']);
      // Check if the node already exists in the array
      if (!isset($node_scores[$node])) {
         // If the node doesn't exist, create an array for it
         $node_scores[$node] = array();
      }

      // Add the scores to the array for the current node
      $node_scores[$node]['download_score'] = $download_score;
      $node_scores[$node]['upload_score'] = $upload_score;
      $node_scores[$node]['ttfb_score'] = $ttfb_score;
      $node_scores[$node]['total_score'] = $total_score;
   }
   $globaltotalscore = ($node_scores['global']['total_score'] ?? 0);
   $sectioncomparequery = "    SELECT
       AVG(contract_price) AS average_contract_price,
       AVG(storage_price) AS average_storage_price,
       AVG(upload_price) AS average_upload_price,
       AVG(download_price) AS average_download_price,
       AVG(collateral) AS average_collateral,
       AVG(used_storage) AS average_used_storage
   FROM
       Hosts
   WHERE
       public_key IN (
           SELECT public_key
           FROM BenchmarkScores
           WHERE node = 'global' AND total_score = $globaltotalscore);";
   $sectioncompareresult = mysqli_query($mysqli, $sectioncomparequery);
   $sectioncompare = mysqli_fetch_assoc($sectioncompareresult);

} else {
   echo "Host ID not provided in the URL.";
}

// Close database connection
mysqli_close($mysqli);
?>
<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>SiaGraph</title>
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <link href="https://cdn.jsdelivr.net/npm/tailwindcss@latest/dist/tailwind.min.css" rel="stylesheet">
   <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css">
   <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
   <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
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
            <span class="hover:underline cursor-pointer flex items-center font-bold text-xl"
               onclick="location.href='/host_explorer'">Top Hosts</span>
            <span class="flex items-center font-bold text-xl ">/</span>
            <span class="flex items-center font-bold text-xl"><?php echo $settings['net_address']; ?></span>
         </div>
         <div class="grid grid-cols-1 md:grid-cols-2 flex justify-center gap-5">
            <!-- Added grid class here -->
            <!-- Left Section -->
            <div class="bg-white rounded-lg shadow-lg p-6 w-full overflow-x-auto max-w-xl">
               <table id="storageStatsTable" class="table-auto w-full border-collapse sm:table-fixed">
                  <tbody>
                     <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Host stats</h2>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">SiaGraph ID</td>
                        <td class="px-4 py-2 text-right"><?php echo $host_id; ?></td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Netaddress</td>
                        <td class="px-4 py-2 font-bold text-right whitespace-normal">
                           <?php echo $settings['net_address']; ?>
                        </td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Public key</td>
                        <td class="px-4 py-2 text-right truncate"
                           title="<?php echo htmlspecialchars($settings['public_key']); ?>">
                           <?php echo substr($settings['public_key'], 0, 20) . '...'; ?>
                        </td>

                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Online</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['online'] ? 'True' : 'False'; ?></td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Accepting contracts</td>
                        <td class="px-4 py-2 text-right">
                           <?php echo $settings['accepting_contracts'] ? 'True' : 'False'; ?>
                        </td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">First seen</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['first_seen'] ?> UTC</td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Country</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['country']; ?></td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Sia Version</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['version']; ?></td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Used Storage</td>
                        <td class="px-4 py-2 text-right">
                           <?php echo number_format($settings['used_storage'] / pow(10, 12), 2); ?> TB
                        </td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Total storage</td>
                        <td class="px-4 py-2 text-right">
                           <?php echo number_format($settings['total_storage'] / pow(10, 12), 2); ?> TB
                        </td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Storage price</td>
                        <td class="px-4 py-2 text-right">
                           <?php echo round($settings['storage_price'] / pow(10, 12) * 4320, 8); ?> SC/TB/Month
                        </td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Download price</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['download_price'] / pow(10, 12); ?> SC/TB
                        </td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Upload price</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['upload_price'] / pow(10, 12); ?> SC/TB
                        </td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Contract price</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['contract_price'] / pow(10, 24); ?>SC</td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Collateral</td>
                        <td class="px-4 py-2 text-right">
                           <?php echo $settings['collateral'] / $settings['storage_price']; ?>x storage price
                        </td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Max collateral</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['max_collateral'] / pow(10, 24); ?> SC
                        </td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Max contract duration</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['max_duration'] / 4320; ?> Months</td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Base RPC price</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['base_rpc_price'] / pow(10, 18); ?>
                           SC/million</td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Sector access price</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['sector_access_price'] / pow(10, 18); ?>
                           SC/million</td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Ephemeral account expiry</td>
                        <td class="px-4 py-2 text-right">
                           <?php echo $settings['ephemeral_account_expiry'] / 86400000000000; ?> Days
                        </td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Max ephemeral account balance</td>
                        <td class="px-4 py-2 text-right">
                           <?php echo $settings['max_ephemeral_account_balance'] / pow(10, 24); ?>SC
                        </td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Sector size</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['sector_size']; ?></td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Sia mux port</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['sia_mux_port']; ?></td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Window size</td>
                        <td class="px-4 py-2 text-right"><?php echo $settings['window_size']; ?> Blocks</td>
                     </tr>
                  </tbody>
               </table>
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
                  <table id="storageStatsTable" class="table-auto min-w-full border-collapse">
                     <tbody>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Final score</td>
                           <td class="px-4 py-2 text-right">
                              <?php echo ($node_scores['global']['total_score'] ?? 0) . "/10"; ?>
                           </td>
                        </tr>
                        <tr class="bg-gray-100">
                           <td class="px-4 py-2 font-semibold">Time to First Sector</td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($benchmark['ttfb'] / 1000 / 1000, 2) . " ms (" . ($node_scores['global']['ttfb_score'] ?? 0) . "/10)"; ?>
                           </td>
                        </tr>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Renter upload</td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($benchmark['upload_speed'] / 1000 / 1000, 2) . " MB/s (" . ($node_scores['global']['upload_score'] ?? 0) . "/10)"; ?>
                           </td>
                        </tr>
                        <tr class="bg-gray-100">
                           <td class="px-4 py-2 font-semibold">Renter download</td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($benchmark['download_speed'] / 1000 / 1000, 2) . " MB/s (" . ($node_scores['global']['download_score'] ?? 0) . "/10)"; ?>
                           </td>
                        </tr>
                     </tbody>
                  </table>
                  <div class="flex justify-center items-center">
                     <button id='recentbenchmarks'
                        class="cursor-pointer hover:underline text-blue-500 font-bold rounded"
                        onclick="location.href='/host_benchmarks?id=<?php echo $host_id; ?>'">View recent benchmarks</button>
                  </div>
               </div>
               <!-- Other Placeholder Section -->
               <div class="bg-white rounded-lg shadow-lg p-6">
                  <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Averages of hosts with
                     final score <?php echo ($node_scores['global']['total_score'] ?? 0); ?></h2>
                  <table id="storageStatsTable" class="table-auto min-w-full border-collapse">
                     <tbody>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Contract price</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($sectioncompare['average_contract_price'] / pow(10, 24), 2); ?>SC
                           <td class="px-4 py-2 text-right">
                              <?php echo round($settings['contract_price'] / $sectioncompare['average_contract_price'] * 100); ?>%
                           </td>
                        </tr>
                        <tr class="bg-gray-100">
                           <td class="px-4 py-2 font-semibold">Storage price</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($sectioncompare['average_storage_price'] / pow(10, 12) * 4320, 2); ?>
                              SC/TB/Month
                           </td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($settings['storage_price'] / $sectioncompare['average_storage_price'] * 100); ?>%
                           </td>
                        </tr>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Upload price</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($sectioncompare['average_upload_price'] / pow(10, 12), 2); ?> SC/TB
                           </td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($settings['upload_price'] / $sectioncompare['average_upload_price'] * 100); ?>%
                           </td>
                        </tr>
                        <tr class="bg-gray-100">
                           <td class="px-4 py-2 font-semibold">Download price</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($sectioncompare['average_download_price'] / pow(10, 12), 2); ?> SC/TB
                           </td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($settings['download_price'] / $sectioncompare['average_download_price'] * 100); ?>%
                           </td>
                        </tr>
                        <tr class="bg-gray-200">
                           <td class="px-4 py-2 font-semibold">Stored data</td>
                           <td class="px-4 py-2 text-middle">
                              <?php echo round($sectioncompare['average_used_storage'] / pow(10, 12), 2); ?> TB
                           </td>
                           <td class="px-4 py-2 text-right">
                              <?php echo round($settings['used_storage'] / $sectioncompare['average_used_storage'] * 100); ?>%
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
                        'label' => 'Download Price',
                        'key' => 'download_price', // Modify based on your data
                        'backgroundColor' => 'rgba(54, 162, 235, 0.2)',
                        'borderColor' => 'rgba(54, 162, 235, 1)',
                        'transform' => "return (entry['download_price'] / 1e12);",
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
                  $unitType = 'fiat',
                  $jsonKey = 'dailydata',
               );
               ?>
            </section>
         </section>
      </div>
      </div>
   </section>
   <!-- Footer Section -->
   <?php include "include/footer.html" ?>
</body>
<link href="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/nouislider@14.7.0/distribute/nouislider.min.js"></script>

</html>
<script>
   // Function to initialize the map
   function initMap() {
      var locationString = "<?php echo $settings['location']; ?>"; // Get the location string from PHP

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

   // Call the initMap function when the page has finished loading
   document.addEventListener("DOMContentLoaded", function () {
      initMap();
   });

</script>