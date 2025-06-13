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
    <link rel="icon" href="img/favicon.ico" type="image/png">
   <style>
      .copy-notification {
         position: absolute;
         background: #333;
         color: #fff;
         padding: 5px 10px;
         border-radius: 5px;
         display: none;
         font-size: 0.9em;
         z-index: 1000;
      }

      .copy-text {
         cursor: pointer;
         font-weight: bold;
         position: relative;
      }
   </style>
</head>

<body>
   <!-- Header Section -->
   <?php include "include/header.html"; ?>
   <!-- Main Content Section -->
   <section id="main-content" class="container mt-4 pb-5">
      <div class="max-w-6xl mx-auto">
         <div class="grid grid-cols-1 md:grid-cols-2 flex justify-center gap-5">
            <!-- Left Section -->
            <div class="bg-white rounded-lg shadow-lg p-6 w-full overflow-x-auto max-w-xl">
               <table id="mainnetTable" class="table-auto w-full border-collapse sm:table-fixed">
                  <thead>
                     <tr class="bg-primary text-white">
                        <th class="px-4 py-2">Address</th>
                        <th class="px-4 py-2 text-right">Protocol Version</th>
                        <th class="px-4 py-2 text-right">Last Scanned</th>
                     </tr>
                  </thead>
                  <tbody>
                     <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Mainnet</h2>
                  </tbody>
               </table>
            </div>
            <!-- Right Section -->
            <div class="bg-white rounded-lg shadow-lg p-6 w-full overflow-x-auto max-w-xl">
               <table id="zenTable" class="table-auto w-full border-collapse sm:table-fixed">
                  <thead>
                     <tr class="bg-primary text-white">
                        <th class="px-4 py-2">Address</th>
                        <th class="px-4 py-2 text-right">Protocol Version</th>
                        <th class="px-4 py-2 text-right">Last Scanned</th>
                     </tr>
                  </thead>
                  <tbody>
                     <h2 class="text-center fs-5 fw-bold py-2 bg-primary text-white rounded-top">Zen</h2>
                  </tbody>
               </table>
            </div>
         </div>
      </div>
   </section>
   <!-- Footer Section -->
   <?php include "include/footer.php" ?>

   <script>
      $(document).ready(function () {
         $.ajax({
            url: '/api/v1/peers',
            method: 'GET',
            success: function (data) {
               const mainnetPeers = data.mainnet.slice(0, 5);
               const zenPeers = data.zen.slice(0, 5);

               mainnetPeers.forEach((peer, index) => {
                  const rowClass = index % 2 === 0 ? 'bg-gray-200' : 'bg-gray-100';
                  $('#mainnetTable tbody').append(
                     `<tr class="${rowClass}">
                        <td class="px-4 py-2 copy-text" onclick="copyToClipboard(event, '${peer.address}')">${peer.address}
                           <span class="copy-notification">Copied!</span>
                        </td>
                        <td class="px-4 py-2 text-right">${peer.version}</td>
                        <td class="px-4 py-2 text-right">${peer.last_scanned}</td>
                     </tr>`
                  );
               });

               zenPeers.forEach((peer, index) => {
                  const rowClass = index % 2 === 0 ? 'bg-gray-200' : 'bg-gray-100';
                  $('#zenTable tbody').append(
                     `<tr class="${rowClass}">
                        <td class="px-4 py-2 copy-text" onclick="copyToClipboard(event, '${peer.address}')">${peer.address}
                           <span class="copy-notification">Copied!</span>
                        </td>
                        <td class="px-4 py-2 text-right">${peer.version}</td>
                        <td class="px-4 py-2 text-right">${peer.last_scanned}</td>
                     </tr>`
                  );
               });
            },
            error: function (error) {
               console.error('Error fetching data', error);
            }
         });
      });

      function copyToClipboard(event, text) {
         const tempInput = document.createElement('input');
         tempInput.value = text;
         document.body.appendChild(tempInput);
         tempInput.select();
         document.execCommand('copy');
         document.body.removeChild(tempInput);

         const notification = event.target.querySelector('.copy-notification');
         notification.style.display = 'block';
         notification.style.left = `${event.offsetX}px`;
         notification.style.top = `${event.offsetY}px`;
         setTimeout(() => {
            notification.style.display = 'none';
         }, 2000);
      }
   </script>
</body>

</html>