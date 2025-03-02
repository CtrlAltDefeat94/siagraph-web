<!DOCTYPE html>
<html lang="en">

<head>
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>SiaGraph</title>
   <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
   <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
   <link href="https://cdn.jsdelivr.net/npm/tailwindcss@latest/dist/tailwind.min.css" rel="stylesheet">
</head>

<body class="bg-gray-100">
   <?php include "include/header.html"; ?>
   <section id="main-content" class="container mt-4 pb-5">
      <div class="max-w-4xl mx-auto bg-white shadow-md rounded-lg p-6">
         <div id="search-section" class="text-center mb-6">
            <h2 class="text-2xl font-bold text-gray-800 mb-4">Troubleshoot a Network Address</h2>
            <form method="GET" action="">
               <input type="text" name="net_address" class="form-control mb-3" placeholder="Enter Net Address">
               <button type="submit" class="btn btn-primary w-full">Search</button>
            </form>
         </div>
         <?php
         if (isset($_GET['net_address']) && !empty($_GET['net_address'])) {
            $netAddress = urlencode($_GET['net_address']);
            $apiUrl = "https://alpha.siagraph.info/api/v1/host_troubleshooter?net_address=$netAddress";
            
            $response = file_get_contents($apiUrl);
            
            if ($response !== false) {
               $data = json_decode($response, true);
            }
         }
         ?>

         <?php if (isset($data)): ?>
            <div id="top-table" class="mb-6">
               <table class="table-auto w-full border-collapse sm:table-fixed bg-white shadow-md rounded-lg">
                  <tbody>
                     <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Public Key</h2>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 text-center"> <?= htmlspecialchars($data['host_info']['publicKey']) ?> </td>
                     </tr>
                  </tbody>
               </table>
            </div>
            <div id="results-section" class="grid grid-cols-1 md:grid-cols-2 gap-6">
               <table class="table-auto w-full border-collapse sm:table-fixed bg-white shadow-md rounded-lg">
                  <tbody>
                     <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Host Information</h2>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">NetAddress</td>
                        <td class="px-4 py-2 text-right"> <?= htmlspecialchars($data['host_info']['netAddress']) ?> </td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Country Code</td>
                        <td class="px-4 py-2 text-right"> <?= htmlspecialchars($data['host_info']['countryCode']) ?> </td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Known Since</td>
                        <td class="px-4 py-2 text-right"> <?= htmlspecialchars($data['host_info']['knownSince']) ?> </td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">Last Announcement</td>
                        <td class="px-4 py-2 text-right"> <?= htmlspecialchars($data['host_info']['lastAnnouncement']) ?> </td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Uptime</td>
                        <td class="px-4 py-2 text-right"> 
                           <?= round(($data['host_info']['successfulInteractions'] / $data['host_info']['totalScans']) * 100, 2) ?>% 
                        </td>
                     </tr>
                  </tbody>
               </table>
               <table class="table-auto w-full border-collapse sm:table-fixed bg-white shadow-md rounded-lg">
                  <tbody>
                     <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Network & Ports</h2>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">IPv4 Accessible</td>
                        <td class="px-4 py-2 text-right"> <?= $data['ipv4_accessible'] ? "Yes" : "No" ?> </td>
                     </tr>
                     <tr class="bg-gray-100">
                        <td class="px-4 py-2 font-semibold">IPv6 Accessible</td>
                        <td class="px-4 py-2 text-right"> <?= $data['ipv6_accessible'] ? "Yes" : "No" ?> </td>
                     </tr>
                     <tr class="bg-gray-200">
                        <td class="px-4 py-2 font-semibold">Scanned Ports</td>
                        <td class="px-4 py-2 text-right">
                           <?php foreach ($data['scanned_ports'] as $port => $status): ?>
                              <span class="badge bg-<?= $status == 'open' ? 'success' : 'danger' ?>">Port <?= $port ?>: <?= ucfirst($status) ?></span>
                           <?php endforeach; ?>
                        </td>
                     </tr>
                  </tbody>
               </table>
            </div>
         <?php endif; ?>
      </div>
   </section>
   <?php include "include/footer.html"; ?>
</body>

</html>
