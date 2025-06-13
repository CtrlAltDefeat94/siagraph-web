<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SiaGraph</title>
    <meta property="og:title" content="SiaGraph Host Explorer" />
    <!--<meta property="og:description" content="A statistics website for the Sia Network." />-->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@latest/dist/tailwind.min.css" rel="stylesheet">
    <script src="script.js"></script>
    <link rel="icon" href="img/favicon.ico" type="image/png">
</head>

<body>
    <!-- Header Section -->
    <?php include "include/header.html"; ?>
    <!-- Main Content Section -->
<!--    <section id="main-content" class="container mt-4 pb-5 masonry-container">-->
<section id="main-content" class="container mt-4 pb-5 max-w-screen-xl">

        <div class="flex flex-col md:flex-row justify-between mt-4 mb-2">
            <a class="flex md:mr-auto items-center font-bold text-xl cursor-pointer hover:underline mb-2 sm:mb-0"
                href='/host_explorer'>Top Hosts</a>

            <div class="mb-2 md:ml-2 sm:mb-0 flex items-center space-x-2">
                <input type="text" id="search" name="search" class="px-4 py-2 border rounded-md h-10"
                    placeholder="Search...">
                <button class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600 h-10"
                    onclick="handleSearch()">Search</button>
            </div>

            <div class="flex justify-between items-center mb-2 md:ml-2 sm:mb-0">
                <label for="sort" class="mr-2">Sort hosts by:</label>
                <select id="sort" class="px-4 py-2 border rounded-md h-10" onchange="handleSortChange()">
                    <option value="rank">Rank</option>
                    <option value="used_storage">Used Storage</option>
                    <option value="total_storage">Total Storage</option>
                    <option value="storage_price">Storage Price</option>
                    <option value="net_address">Name</option>
                    <option value="age">Age</option>
                    <option value="growth">24h Growth</option>
                </select>
            </div>

            <div class="flex items-center mb-2 md:ml-2 sm:mb-0 h-10">
                <input type="checkbox" id="activeOnly" class="h-5 w-5" onchange="handleShowInactiveChange()">
                <label for="activeOnly" class="ml-2">Show Inactive</label>
            </div>
        </div>



        <div class="overflow-x-auto">
            <table id="hostTable" class="table-auto min-w-full border-collapse">
                <thead>
                    <tr class="bg-gray-100">
                        <th class="px-4 py-2 w-8">#</th>
                        <th class="px-4 py-2 w-80">Host</th>
                        <!--<th class="px-4 py-2">24h growth</th>-->
                        <th class="px-4 py-2 w-32">Country</th>
                        <th class="px-4 py-2 w-32">
                            Used Storage<span class="text-xs text-gray-500 ml-1">(24h)</span>
                        </th>
                        <th class="px-4 py-2 w-28">Total Storage</th>
                        <th class="px-4 py-2 w-24">Price</th>
                        <th class="px-4 py-2 w-16">Score</th>
                    </tr>
                </thead>
                <!-- Table body -->
                <tbody id="hostTableBody">
                    <!-- Table rows will be dynamically populated here -->
                </tbody>
            </table>
        </div>
        <!-- Pagination -->
        <div id="pagination" class="flex justify-center mt-4"></div>
    </section>
    <!-- Footer Section -->
    <?php include "include/footer.php" ?>

    <!-- JavaScript -->
    <script>
        var ajaxBaseUrl = '/api/v1/hosts';
        var ajaxParams = { 'page': 1 }; // Object to hold URL parameters
        var query;
        document.getElementById('search').addEventListener("keydown", function (e) {
            if (e.keyCode == 13) {
                handleSearch();
            }
        });

        document.addEventListener("DOMContentLoaded", function () {
            // Set default sort criteria
            const sortElement = document.getElementById('sort');
            if (sortElement) {
                sortElement.value = '<?php echo isset($_GET["sort"]) ? $_GET["sort"] : "rank"; ?>';
                // Trigger initial data load
                loadData(); // Display initial data immediately after fetching
            } else {
                console.error("Element with ID 'sort' not found.");
            }
        });

        document.addEventListener("DOMContentLoaded", function () {
            const urlParams = new URLSearchParams(window.location.search);
            const showInactiveParam = urlParams.get('showinactive');

            // Check if the showinactive parameter is set to true
            const showInactive = showInactiveParam === 'true';

            // Update checkbox state
            const activeOnlyCheckbox = document.getElementById('activeOnly');
            activeOnlyCheckbox.checked = showInactive;
        });

        let hosts = []; // Initialize hosts array
        const limit = 15; // Number of records per page

        function updateAjaxParams(params) {
            for (const key in params) {
                if (params.hasOwnProperty(key)) {
                    ajaxParams[key] = params[key];
                }
            }

        }
        function constructAjaxUrl() {
            let url = ajaxBaseUrl;

            // Check if ajaxParams is not empty
            if (Object.keys(ajaxParams).length > 0) {
                url += '?';

                // Build URL parameters from ajaxParams object
                const paramStrings = [];
                for (const key in ajaxParams) {
                    if (ajaxParams.hasOwnProperty(key)) {
                        paramStrings.push(`${encodeURIComponent(key)}=${encodeURIComponent(ajaxParams[key])}`);
                    }
                }
                url += paramStrings.join('&');
            }

            // Output the constructed URL for verification (optional)

            return url;
        }


        // Function to handle sorting criteria change
        function handleSortChange() {
            const sortElement = document.getElementById('sort');
            const selectedSort = sortElement.value;

            // Update URL parameter
            const url = new URL(window.location.href);
            updateAjaxParams({ sort: selectedSort, page: 1 });

            // Update URL without reloading
            window.history.replaceState({}, '', url);

            loadData();
        }


        // Function to handle searbbbbbbbbbbch
        function handleSearch() {
            const searchInput = document.getElementById('search').value.toLowerCase();

            // Update the URL to include the search query
            const url = new URL(window.location.href);
            updateAjaxParams({ query: searchInput, page: 1 });

            query = searchInput;
            // Make an AJAX request to fetch the data based on the search query
            const xhr = new XMLHttpRequest();
            xhr.open('GET', constructAjaxUrl());
            xhr.onload = function () {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    const filteredHosts = response.hosts; // Assuming the response contains an array of hosts
                    const totalPages = response.pagination.total_pages; // Assuming the response contains the total number of pages

                    // Display the fetched data
                    displayData(filteredHosts, totalPages);
                } else {
                    console.error('Failed to fetch data');
                }
            };
            xhr.send();
        }

        // Function to display data for the specified page
        function displayData(data, totalPages) {
            const tableBody = document.getElementById('hostTableBody');
            tableBody.innerHTML = ''; // Clear existing rows

            data.forEach((host, index) => {
                const isEvenRow = index % 2 === 0;
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td class="border px-4 py-2">${host.rank}</td>
                    <td class="border px-4 py-2"><a href="/host?id=${host.host_id}" class="hover:underline">${host.net_address}</a></td>
                    <td class="border px-4 py-2">${host.country_name}</td>
                    <td class="border px-4 py-2">
                        ${((host.used_storage / (1000 * 1000 * 1000 * 1000)).toFixed(2)).toLocaleString()} TB
                        <span id="storage-diff-${host.host_id}" class="text-xs text-gray-500 ml-1"></span>
                    </td>
                    <td class="border px-4 py-2">${((host.total_storage / (1000 * 1000 * 1000 * 1000)).toFixed(2)).toLocaleString()} TB</td>
                    <td class="border px-4 py-2">${Math.round(host.storage_price / (10 ** 12) * 4320).toLocaleString()} SC</td>
                    <td class="border px-4 py-2">${Math.round(host.total_score)}</td>
                `;
                row.className = isEvenRow ? 'bg-gray-50' : 'bg-gray-25';
                tableBody.appendChild(row);
            });

            // Generate pagination buttons
            generatePaginationButtons(totalPages, ajaxParams['page']);
            // Update storage difference formatting
            updateStorageDiff(data);
        }
        // Function to generate pagination buttons
        function generatePaginationButtons(totalPages, currentPage) {
            const paginationDiv = document.getElementById('pagination');
            paginationDiv.innerHTML = ''; // Clear existing buttons

            const maxVisibleButtons = 5;

            // Calculate start and end page numbers for pagination
            let startPage = currentPage - Math.floor(maxVisibleButtons / 2);
            startPage = Math.max(startPage, 1);
            const endPage = Math.min(startPage + maxVisibleButtons - 1, totalPages);

            // Adjust start page again if end page is at the boundary
            startPage = Math.max(endPage - maxVisibleButtons + 1, 1);

            const firstButton = createPaginationButton(1, 'First');
            const prevButton = createPaginationButton(currentPage - 1, 'Previous');
            paginationDiv.appendChild(firstButton);
            paginationDiv.appendChild(prevButton);
            // Add first and previous buttons
            if (currentPage <= 1) {
                firstButton.classList.add('pagination-button--invisible');
                prevButton.classList.add('pagination-button--invisible');
            }

            // Add page buttons
            for (let i = startPage; i <= endPage; i++) {
                const pageButton = createPaginationButton(i, i);
                pageButton.classList.add('pagination-button--digit');
                if (i === currentPage) {
                    pageButton.classList.add('font-bold');
                }
                paginationDiv.appendChild(pageButton);
            }

            const nextButton = createPaginationButton(currentPage + 1, 'Next');
            const lastButton = createPaginationButton(totalPages, 'Last');
            paginationDiv.appendChild(nextButton);
            paginationDiv.appendChild(lastButton);

            // Hide last and next buttons on last page
            if (currentPage >= totalPages) {
                nextButton.classList.add('pagination-button--invisible');
                lastButton.classList.add('pagination-button--invisible');
            }
        }
        // Function to create a pagination button
        function createPaginationButton(page, text) {
            const pageButton = document.createElement('button');
            pageButton.textContent = text;
            pageButton.classList.add('pagination-button', 'py-1', 'mr-2', 'rounded-md', 'bg-gray-200', 'text-gray-700');

            // Attach event listener to updateAjaxParams and log the page
            pageButton.onclick = () => {
                updateAjaxParams({ page: page });
                loadData();
                console.log(page); // Log the current page value
            };

            return pageButton;
        }


        function updateStorageDiff(data, page) {
            for (let i = 0; i < data.length; i++) {
                const host = data[i];
                const originalIndex = hosts.indexOf(host);
                const storageDiffElement = document.getElementById(`storage-diff-${host.host_id}`);
                const storageDiff = Math.round(host.used_storage_diff / (1000 * 1000 * 1000));

                if (storageDiff > 0) {
                    storageDiffElement.innerHTML = `(+${storageDiff.toLocaleString()} GB)`;
                    storageDiffElement.style.color = 'green';
                } else if (storageDiff < 0) {
                    storageDiffElement.innerHTML = `(${storageDiff.toLocaleString()} GB)`;
                    storageDiffElement.style.color = 'red';
                } else {
                    //storageDiffElement.innerHTML = `(${storageDiff.toLocaleString()} GB)`;
                    storageDiffElement.innerHTML = ``;
                }
            }
        }

        function handleShowInactiveChange() {
            const activeOnlyCheckbox = document.getElementById('activeOnly');
            const showInactive = activeOnlyCheckbox.checked;

            // Construct the new URL
            const url = new URL(window.location.href);
            if (showInactive) {
                updateAjaxParams({ showinactive: true, page: 1 });
            } else {
                updateAjaxParams({ showinactive: false, page: 1 });
            }

            // Update URL without reloading
            window.history.replaceState({}, '', url); // Update URL without reloading

            loadData(); // Reload data for the first page
        }
        // Function to load data from server based on page number and query
        function loadData() {
            const xhr = new XMLHttpRequest();
            let url = constructAjaxUrl();
            xhr.open('GET', url, true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    const hosts = response.hosts; // Assuming the response contains an array of hosts
                    const totalPages = response.pagination.total_pages; // Assuming the response contains the total number of pages

                    // Display the fetched data
                    displayData(hosts, totalPages);
                } else {
                    console.error('Failed to fetch data');
                }
            };
            xhr.send();
        }
    </script>
</body>

</html>


<style>
    .pagination-button:not(.pagination-button--digit) {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .pagination-button--invisible {
        opacity: 0;
        pointer-events: none;
    }

    .pagination-button--digit {
        width: 40px;
    }
</style>