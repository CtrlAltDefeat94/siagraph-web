<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';

use Siagraph\Utils\ApiClient;

$json_data = ApiClient::fetchJson('/api/v1/hosts?limit=0');
$dataError = !is_array($json_data) || !isset($json_data['hosts']);
if ($dataError) {
    $json_data = ['hosts' => []];
}

$versions = [];
$countries = [];
foreach ($json_data['hosts'] as $host) {
    $ver = $host['software_version'];
    $ct  = $host['country_name'];
    if (!isset($versions[$ver])) {
        $versions[$ver] = 1;
    } else {
        $versions[$ver]++;
    }
    if (!isset($countries[$ct])) {
        $countries[$ct] = 1;
    } else {
        $countries[$ct]++;
    }
}
ksort($versions);
ksort($countries);

render_header('SiaGraph - Host Explorer');
?>
    <?php if ($dataError): ?>
        <p class="text-center text-muted">Host data unavailable.</p>
    <?php endif; ?>
    <!-- Main Content Section -->
<section id="main-content" class="sg-container sg-container--wide py-4 gap-4">

        <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-hdd-network me-2"></i>Host Explorer</h1>
        <div class="host-layout gap-4 items-start">
            <section class="card">
                <h2 class="card__heading">Filters</h2>
                <div class="card__content">
                    <div class="flex flex-col gap-3">
                        <label for="acceptingContractsFilter" class="flex items-center text-sm">
                            <input type="checkbox" id="acceptingContractsFilter" class="h-4 w-4 me-2" onchange="applyFilters()">Accepting contracts
                        </label>
                        <label for="activeOnly" class="flex items-center text-sm">
                            <input type="checkbox" id="activeOnly" class="h-4 w-4 me-2" onchange="handleShowInactiveChange()">Include inactive
                        </label>

                        <div>
                            <label class="block mb-1 text-sm" for="versionFilter">Version</label>
                            <select id="versionFilter" class="px-2 py-1 pe-3 border border-gray-600 rounded-md w-100 h-8 text-sm bg-gray-800 text-white" title="Filter by host software version">
                                <option value="">All Versions</option>
                                <?php foreach(array_keys($versions) as $ver){echo "<option value=\"$ver\">$ver</option>";} ?>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="countryFilter">Country</label>
                            <select id="countryFilter" class="px-2 py-1 pe-4 border border-gray-600 rounded-md w-100 h-8 text-sm bg-gray-800 text-white" title="Filter by country">
                                <option value="">All Countries</option>
                                <?php foreach(array_keys($countries) as $ct){echo "<option value=\"$ct\">$ct</option>";} ?>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="maxContractPrice">Max Contract Price</label>
                            <input type="number" id="maxContractPrice" class="px-2 py-1 border border-gray-600 rounded-md w-100 h-8 text-sm bg-gray-800 text-white placeholder-gray-400" onchange="applyFilters()">
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="maxStoragePrice">Max Storage Price</label>
                            <input type="number" id="maxStoragePrice" class="px-2 py-1 border border-gray-600 rounded-md w-100 h-8 text-sm bg-gray-800 text-white placeholder-gray-400" onchange="applyFilters()">
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="maxUploadPrice">Max Upload Price</label>
                            <input type="number" id="maxUploadPrice" class="px-2 py-1 border border-gray-600 rounded-md w-100 h-8 text-sm bg-gray-800 text-white placeholder-gray-400" onchange="applyFilters()">
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="maxDownloadPrice">Max Download Price</label>
                            <input type="number" id="maxDownloadPrice" class="px-2 py-1 border border-gray-600 rounded-md w-100 h-8 text-sm bg-gray-800 text-white placeholder-gray-400" onchange="applyFilters()">
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="flex items-center justify-between gap-2">
                    <h2 class="card__heading">Hosts</h2>
                    <div class="flex flex-wrap items-center gap-2 w-100">
                        <span class="text-sm">Sort:</span>
                        <select id="sort" class="px-2 py-1 pe-4 border border-gray-600 rounded-md h-8 text-sm bg-gray-800 text-white" onchange="handleSortChange()" title="Rank: host reliability. Used Storage: storage currently used. Total Storage: host capacity. Storage Price: price per TB per month. Name: host network address. Age: how long the host has been online. 24h Growth: storage growth over the last day.">
                            <option value="rank">Rank</option>
                            <option value="used_storage">Used Storage</option>
                            <option value="total_storage">Total Storage</option>
                            <option value="storage_price">Storage Price</option>
                            <option value="net_address">Name</option>
                            <option value="age">Age</option>
                            <option value="growth">24h Growth</option>
                        </select>
                        <div class="ms-auto d-flex align-items-center gap-2">
                            <input type="text" id="search" name="search" class="px-2 py-1 border border-gray-600 rounded-md h-8 text-sm w-full sm:w-64 bg-gray-800 text-white placeholder-gray-400" placeholder="Search net address" title="Type part of net address to filter hosts">
                            <button class="button h-8 px-3" onclick="handleSearch()">Search</button>
                        </div>
                    </div>
                </div>
                <div class="card__content">
                    <div class="overflow-x-auto">
                        <table id="hostTable" class="table-clean text-white min-w-full" style="visibility: hidden;">
                            <thead></thead>
                        <!-- Table body -->
                        <tbody id="hostTableBody">
                            <!-- Table rows will be dynamically populated here -->
                        </tbody>
                        </table>
                        <!-- Pagination -->
                        <div id="pagination" class="d-flex justify-content-center w-100 mt-4"></div>
                    </div>
                </div>
            </section>
        </div>

        
    </section>
    <!-- Footer Section -->

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
            // Seed ajaxParams from current URL so we don't drop existing params
            (function seedParamsFromUrl(){
                const urlParams = new URLSearchParams(window.location.search);
                urlParams.forEach((value, key) => {
                    // Preserve all known/unknown params; backend will ignore unknowns
                    // Normalize booleans for consistency
                    if (value === 'true') {
                        ajaxParams[key] = true;
                    } else if (value === 'false') {
                        ajaxParams[key] = false;
                    } else if (key === 'page') {
                        const n = parseInt(value, 10);
                        ajaxParams[key] = isNaN(n) ? 1 : n;
                    } else {
                        ajaxParams[key] = value;
                    }
                });
                // Reflect query param into search input, if present
                const q = urlParams.get('query');
                if (q !== null) {
                    const searchEl = document.getElementById('search');
                    if (searchEl) searchEl.value = q;
                }
            })();
            // Set default sort criteria
            const sortElement = document.getElementById('sort');
            if (sortElement) {
                sortElement.value = '<?php echo htmlspecialchars(isset($_GET["sort"]) ? $_GET["sort"] : "rank", ENT_QUOTES, 'UTF-8'); ?>';
            } else {
                console.error("Element with ID 'sort' not found.");
            }

            // Initialize inactive hosts checkbox from query string
            const urlParams = new URLSearchParams(window.location.search);
            const showInactiveParam = urlParams.get('showinactive');
            const showInactive = showInactiveParam === 'true';
            const activeOnlyCheckbox = document.getElementById('activeOnly');
            activeOnlyCheckbox.checked = showInactive;

            const acceptingContractsParam = urlParams.get('acceptingContracts');
            const acceptingContractsCheckbox = document.getElementById('acceptingContractsFilter');
            acceptingContractsCheckbox.checked = acceptingContractsParam === 'true';

            // Render header for current viewport
            if (typeof renderTableHeader === 'function') {
                renderTableHeader();
            }

            // Trigger initial data load
            applyFilters();

            document.getElementById('versionFilter').addEventListener('change', applyFilters);
            document.getElementById('countryFilter').addEventListener('change', applyFilters);
            document.getElementById('acceptingContractsFilter').addEventListener('change', applyFilters);
        });

        let hosts = []; // Initialize hosts array

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

        function applyFilters() {
            const version = document.getElementById('versionFilter').value;
            const country = document.getElementById('countryFilter').value;
            const acceptingContracts = document.getElementById('acceptingContractsFilter').checked;

            if (version) {
                ajaxParams['version'] = version;
            } else {
                delete ajaxParams['version'];
            }

            if (country) {
                ajaxParams['country'] = country;
            } else {
                delete ajaxParams['country'];
            }

            if (acceptingContracts) {
                ajaxParams['acceptingContracts'] = true;
            } else {
                delete ajaxParams['acceptingContracts'];
            }

            const contractPrice = document.getElementById('maxContractPrice').value;
            const storagePrice = document.getElementById('maxStoragePrice').value;
            const uploadPrice = document.getElementById('maxUploadPrice').value;
            const downloadPrice = document.getElementById('maxDownloadPrice').value;

            if (contractPrice !== '') {
                ajaxParams['maxContractPrice'] = contractPrice;
            } else {
                delete ajaxParams['maxContractPrice'];
            }

            if (storagePrice !== '') {
                ajaxParams['maxStoragePrice'] = storagePrice;
            } else {
                delete ajaxParams['maxStoragePrice'];
            }

            if (uploadPrice !== '') {
                ajaxParams['maxUploadPrice'] = uploadPrice;
            } else {
                delete ajaxParams['maxUploadPrice'];
            }

            if (downloadPrice !== '') {
                ajaxParams['maxDownloadPrice'] = downloadPrice;
            } else {
                delete ajaxParams['maxDownloadPrice'];
            }

            // Reset page to 1 when filters change
            ajaxParams['page'] = 1;

            const url = new URL(window.location.href);
            url.search = new URLSearchParams(ajaxParams).toString();
            window.history.replaceState({}, '', url);

            loadData();
        }


        // Function to handle sorting criteria change
        function handleSortChange() {
            const sortElement = document.getElementById('sort');
            const selectedSort = sortElement.value;

            // Update URL parameter
            updateAjaxParams({ sort: selectedSort });
            applyFilters();
        }


        // Function to handle search
        function handleSearch() {
            const searchInput = document.getElementById('search').value.toLowerCase();
            updateAjaxParams({ query: searchInput });
            query = searchInput;
            applyFilters();
        }

        function renderScore(score) {
            const rounded = Math.max(0, Math.min(10, Math.round(score)));
            const hue = (rounded / 10) * 120;
            return `<span class="score" style="color: hsl(${hue}, 70%, 50%);">${rounded}</span>`;
        }

        // Responsive helpers
        function isMobile() {
            return window.innerWidth < 768; // Tailwind md breakpoint
        }

        function showTable() {
            const tbl = document.getElementById('hostTable');
            if (tbl) tbl.style.visibility = 'visible';
        }

        function hideTable() {
            const tbl = document.getElementById('hostTable');
            if (tbl) tbl.style.visibility = 'hidden';
        }

        function renderTableHeader() {
            const thead = document.querySelector('#hostTable thead');
            if (!thead) return;
            if (isMobile()) {
                thead.innerHTML = `
                    <tr>
                        <th class="px-2 py-2 num">#</th>
                        <th class="px-2 py-2">Host</th>
                    </tr>
                `;
            } else {
                thead.innerHTML = `
                    <tr>
                        <th class="px-4 py-2 w-8 num">#</th>
                        <th class="px-4 py-2 w-80">Host</th>
                        <th class="px-4 py-2 w-32">Country</th>
                        <th class="px-4 py-2 w-32 num">Used Storage<span class="text-xs text-gray-400 ml-1">(24h)</span></th>
                        <th class="px-4 py-2 w-28 num">Total Storage</th>
                        <th class="px-4 py-2 w-24 num text-nowrap">Price</th>
                        <th class="px-4 py-2 w-16 num">Score</th>
                    </tr>
                `;
            }
        }

        // Function to display data for the specified page
        function displayData(data, pagination) {
            const tableBody = document.getElementById('hostTableBody');
            tableBody.innerHTML = ''; // Clear existing rows
            // Ensure header matches current viewport
            renderTableHeader();

            if (!data || data.length === 0) {
                const emptyRow = document.createElement('tr');
                const colspan = isMobile() ? 2 : 7;
                emptyRow.innerHTML = `<td colspan="${colspan}" class="px-4 py-8 text-center text-gray-400">No hosts match your filters.</td>`;
                tableBody.appendChild(emptyRow);
                document.getElementById('pagination').innerHTML = '';
                return;
            }

            const perPage = (pagination && pagination.per_page) ? pagination.per_page : 15;
            const currentPage = (pagination && pagination.current_page) ? pagination.current_page : (ajaxParams['page'] || 1);

            data.forEach((host, index) => {
                const isEvenRow = index % 2 === 0;
                const row = document.createElement('tr');
                const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
                const usedTB = Number((host.used_storage / (1000 * 1000 * 1000 * 1000)).toFixed(2)).toLocaleString(loc);
                const totalTB = Number((host.total_storage / (1000 * 1000 * 1000 * 1000)).toFixed(2)).toLocaleString(loc);
                const priceSC = Math.round(host.storage_price / (10 ** 12) * 4320).toLocaleString(loc);
                const computedIndex = (((currentPage - 1) * perPage) + index + 1);
                const displayRank = (host.filtered_rank && Number(host.filtered_rank) > 0) ? host.filtered_rank : computedIndex;
                if (isMobile()) {
                    // Mobile: Only two columns (# and Host with details)
                    row.innerHTML = `
                        <td class="border px-2 py-2 num">${displayRank}</td>
                        <td class="border px-2 py-2 align-top">
                            <a href="/host?id=${host.host_id}" class="host-link hover:underline">${host.net_address}</a>${host.accepting_contracts == 0 ? ' <span class="text-red-500" title="Not accepting contracts">❌</span>' : ''}
                            <div class="text-xs text-gray-300 mt-1">
                                <span class="whitespace-nowrap">${host.country_name}</span> · 
                                <span class="whitespace-nowrap">Used: ${usedTB} TB</span> · 
                                <span class="whitespace-nowrap">Total: ${totalTB} TB</span> · 
                                <span class="whitespace-nowrap">Price: ${priceSC} SC</span> · 
                                <span class="whitespace-nowrap">Score: ${renderScore(host.total_score)}</span>
                            </div>
                        </td>
                    `;
                } else {
                    // Desktop: All columns except the second (mobile host)
                    row.innerHTML = `
                        <td class="border px-4 py-2 num">${displayRank}</td>
                        <td class="border px-4 py-2 align-top">
                            <a href="/host?id=${host.host_id}" class="host-link hover:underline">${host.net_address}</a>${host.accepting_contracts == 0 ? ' <span class="text-red-500" title="Not accepting contracts">❌</span>' : ''}
                        </td>
                        <td class="border px-4 py-2">${host.country_name}</td>
                        <td class="border px-4 py-2 num">
                            ${usedTB} TB
                            <span id="storage-diff-${host.host_id}" class="text-xs text-gray-400 ml-1"></span>
                        </td>
                        <td class="border px-4 py-2 num">${totalTB} TB</td>
                        <td class="border px-4 py-2 num text-nowrap">${priceSC} SC</td>
                        <td class="border px-4 py-2 num">${renderScore(host.total_score)}</td>
                    `;
                }
                tableBody.appendChild(row);
            });

            // Generate pagination buttons
            const totalPages = (pagination && pagination.total_pages) ? pagination.total_pages : 1;
            generatePaginationButtons(totalPages, currentPage);
            // Update storage difference formatting
            updateStorageDiff(data);
            // Make table visible after content is ready
            showTable();
        }
        // Function to generate pagination buttons
        function generatePaginationButtons(totalPages, currentPage) {
            const paginationDiv = document.getElementById('pagination');
            paginationDiv.innerHTML = ''; // Clear existing buttons

            const maxVisibleButtons = isMobile() ? 3 : 5;

            // Calculate start and end page numbers for pagination
            let startPage = currentPage - Math.floor(maxVisibleButtons / 2);
            startPage = Math.max(startPage, 1);
            const endPage = Math.min(startPage + maxVisibleButtons - 1, totalPages);

            // Adjust start page again if end page is at the boundary
            startPage = Math.max(endPage - maxVisibleButtons + 1, 1);

            const firstButton = createPaginationButton(1, isMobile() ? '«' : 'First');
            const prevButton = createPaginationButton(currentPage - 1, isMobile() ? '‹' : 'Previous');
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
                    pageButton.classList.add('font-bold', 'pagination-button--active');
                }
                paginationDiv.appendChild(pageButton);
            }

            const nextButton = createPaginationButton(currentPage + 1, isMobile() ? '›' : 'Next');
            const lastButton = createPaginationButton(totalPages, isMobile() ? '»' : 'Last');
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
            pageButton.classList.add('pagination-button', 'px-3', 'py-1', 'mr-2', 'rounded-md');

            // Attach event listener to update params, update URL, and load data
            pageButton.onclick = () => {
                updateAjaxParams({ page: page });
                const url = new URL(window.location.href);
                url.search = new URLSearchParams(ajaxParams).toString();
                window.history.replaceState({}, '', url);
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

                if (storageDiffElement) {
                    if (storageDiff > 0) {
                        const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
                        storageDiffElement.innerHTML = `(+${storageDiff.toLocaleString(loc)} GB)`;
                        storageDiffElement.style.color = 'green';
                    } else if (storageDiff < 0) {
                        const loc2 = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
                        storageDiffElement.innerHTML = `(${storageDiff.toLocaleString(loc2)} GB)`;
                        storageDiffElement.style.color = 'red';
                    } else {
                        storageDiffElement.innerHTML = ``;
                    }
                }
            }
        }

        function handleShowInactiveChange() {
            const activeOnlyCheckbox = document.getElementById('activeOnly');
            const showInactive = activeOnlyCheckbox.checked;

            if (showInactive) {
                updateAjaxParams({ showinactive: true });
            } else {
                updateAjaxParams({ showinactive: false });
            }
            applyFilters();
        }
        // Function to load data from server based on page number and query
        function showLoadingSkeleton() {
            const tableBody = document.getElementById('hostTableBody');
            renderTableHeader();
            const cols = isMobile() ? 2 : 7;
            tableBody.innerHTML = '';
            for (let r = 0; r < 8; r++) {
                const tr = document.createElement('tr');
                for (let c = 0; c < cols; c++) {
                    const td = document.createElement('td');
                    td.className = 'border px-4 py-2';
                    td.innerHTML = '<span class="skeleton-line"></span>';
                    tr.appendChild(td);
                }
                tableBody.appendChild(tr);
            }
            const paginationDiv = document.getElementById('pagination');
            if (paginationDiv) paginationDiv.innerHTML = '';
            // Show the table once skeleton is ready
            showTable();
        }

        function loadData() {
            showLoadingSkeleton();
            const xhr = new XMLHttpRequest();
            let url = constructAjaxUrl();
            xhr.open('GET', url, true);
            xhr.onload = function () {
                if (xhr.status === 200) {
                    const response = JSON.parse(xhr.responseText);
                    const hosts = response.hosts; // Assuming the response contains an array of hosts
                    const pagination = response.pagination;

                    // Cache for potential re-render on resize
                    window.currentHosts = hosts;
                    window.currentPagination = pagination;

                    // Display the fetched data
                    displayData(hosts, pagination);
                } else {
                    console.error('Failed to fetch data');
                }
            };
            xhr.send();
        }

        // Re-render on breakpoint changes
        (function setupResizeRerender(){
            let lastIsMobile = isMobile();
            window.addEventListener('resize', () => {
                const nowIsMobile = isMobile();
                if (nowIsMobile !== lastIsMobile) {
                    lastIsMobile = nowIsMobile;
                    if (window.currentHosts) {
                        displayData(window.currentHosts, window.currentPagination || { total_pages: 1, current_page: 1, per_page: 15 });
                    } else {
                        renderTableHeader();
                    }
                }
            });
        })();
    </script>



<style>
    /* Two-column layout with fixed sidebar on md+ */
    .host-layout{ display: grid; grid-template-columns: 1fr; align-items: start; }
    @media (min-width: 768px){ /* md */
        .host-layout{ grid-template-columns: 240px 1fr; }
    }
    /* Ensure pagination is centered on all viewports */
    #pagination { display: flex; justify-content: center; align-items: center; }
    /* Keep pagination button content on one line and centered */
    .pagination-button { display: inline-flex; align-items: center; justify-content: center; white-space: nowrap; }
    .pagination-button:not(.pagination-button--digit) {
        padding-left: 1rem;
        padding-right: 1rem;
    }

    .pagination-button--invisible {
        opacity: 0;
        pointer-events: none;
    }

    .pagination-button--digit { min-width: 44px; }

    /* Prevent long net addresses from expanding the table */
    .table-clean td:nth-child(2),
    .table-clean .host-link {
        overflow-wrap: anywhere; /* modern wrap for unbreakable strings */
        word-break: break-word;  /* fallback */
    }

    /* Mobile refinements: tighter cards, edge-to-edge table, smaller pagination */
    @media (max-width: 767.98px) {
        /* Reduce card chrome to free space */
        section.card { padding: 0.75rem; border-radius: 1rem; }
        /* Container side padding */
        #main-content.sg-container { padding-left: 0.75rem; padding-right: 0.75rem; gap: 1.5rem; }
        /* Let the table use available width */
        .table-clean { table-layout: fixed; }
        .table-clean th, .table-clean td { padding-left: 0.5rem; padding-right: 0.5rem; }
        /* Allow long hostnames/IPs to wrap aggressively on small screens */
        .table-clean .host-link { word-break: break-all; }
        /* Bleed the overflow container to the card edges */
        .card .card__content > .overflow-x-auto { margin-left: -0.75rem; margin-right: -0.75rem; }
        /* Pagination: wrap and shrink */
        #pagination { display: flex; flex-wrap: wrap; gap: 0.25rem; }
        .pagination-button { padding: 0.25rem 0.5rem; font-size: 0.875rem; margin-right: 0 !important; }
        .pagination-button--digit { min-width: 36px; }
    }
</style>
<?php render_footer(); ?>
