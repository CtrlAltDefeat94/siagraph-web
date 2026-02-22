<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';

$dataError = false;

render_header('SiaGraph - Host Explorer');
?>
    <?php if ($dataError): ?>
        <p class="text-center text-muted">Host data unavailable.</p>
    <?php endif; ?>
    <!-- Main Content Section -->
<section id="main-content" class="sg-container sg-container--wide py-4 gap-4">

        <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-hdd-network me-2"></i>Host Explorer</h1>
        <div class="host-layout gap-4 items-start">
            <section class="card filters-card">
                <h2 class="card__heading">Filters</h2>
                <button id="filtersToggle" class="filters-toggle" type="button" aria-expanded="false" aria-controls="filtersContent">Show filters</button>
                <div class="card__content" id="filtersContent" hidden>
                    <div class="flex flex-col gap-3">
                        <label for="acceptingContractsFilter" class="flex items-center text-sm">
                            <input type="checkbox" id="acceptingContractsFilter" class="h-4 w-4 me-2">Accepting contracts
                        </label>
                        <label for="activeOnly" class="flex items-center text-sm">
                            <input type="checkbox" id="activeOnly" class="h-4 w-4 me-2" onchange="handleShowInactiveChange()">Include inactive
                        </label>

                        <div>
                            <label class="block mb-1 text-sm" for="versionFilter">Version</label>
                            <select id="versionFilter" class="px-2 py-1 pe-3 border border-gray-600 rounded-md w-100 h-10 text-sm bg-gray-800 text-white" title="Filter by host software version">
                                <option value="">All Versions</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="countryFilter">Country</label>
                            <select id="countryFilter" class="px-2 py-1 pe-4 border border-gray-600 rounded-md w-100 h-10 text-sm bg-gray-800 text-white" title="Filter by country">
                                <option value="">All Countries</option>
                            </select>
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="maxContractPrice">Max Contract Price</label>
                            <input type="number" id="maxContractPrice" class="px-2 py-1 border border-gray-600 rounded-md w-100 h-10 text-sm bg-gray-800 text-white placeholder-gray-400" onchange="applyFilters()">
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="maxStoragePrice">Max Storage Price</label>
                            <input type="number" id="maxStoragePrice" class="px-2 py-1 border border-gray-600 rounded-md w-100 h-10 text-sm bg-gray-800 text-white placeholder-gray-400" onchange="applyFilters()">
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="maxUploadPrice">Max Upload Price</label>
                            <input type="number" id="maxUploadPrice" class="px-2 py-1 border border-gray-600 rounded-md w-100 h-10 text-sm bg-gray-800 text-white placeholder-gray-400" onchange="applyFilters()">
                        </div>
                        <div>
                            <label class="block mb-1 text-sm" for="maxDownloadPrice">Max Download Price</label>
                            <input type="number" id="maxDownloadPrice" class="px-2 py-1 border border-gray-600 rounded-md w-100 h-10 text-sm bg-gray-800 text-white placeholder-gray-400" onchange="applyFilters()">
                        </div>
                        <div class="flex justify-end">
                            <button id="clearFiltersBtn" class="button h-10 px-3" type="button">Clear filters</button>
                        </div>
                    </div>
                </div>
            </section>

            <section class="card">
                <div class="hosts-toolbar">
                    <h2 class="card__heading">Hosts</h2>
                    <div id="loadMessage" class="hosts-toolbar__meta text-sm text-gray-400" aria-live="polite"></div>
                    <div class="hosts-toolbar__controls">
                        <div class="hosts-toolbar__sort">
                            <span class="text-sm">Sort:</span>
                            <select id="sort" class="px-2 py-1 pe-4 border border-gray-600 rounded-md h-10 text-sm bg-gray-800 text-white" onchange="handleSortChange()" title="Rank: host reliability. Used Storage: storage currently used. Total Storage: host capacity. Storage Price: price per TB per month. Name: host network address. Age: how long the host has been online. 24h Growth: storage growth over the last day.">
                            <option value="rank">Rank</option>
                            <option value="used_storage">Used Storage</option>
                            <option value="total_storage">Total Storage</option>
                            <option value="storage_price">Storage Price</option>
                            <option value="net_address">Name</option>
                            <option value="age">Age</option>
                            <option value="growth">24h Growth</option>
                            </select>
                        </div>
                        <div class="hosts-toolbar__search">
                            <input type="text" id="search" name="search" class="px-2 py-1 border border-gray-600 rounded-md h-10 text-sm w-full sm:w-64 bg-gray-800 text-white placeholder-gray-400" placeholder="Search net address or public key" title="Type part of a net address or public key to filter hosts">
                            <button class="button h-10 px-3" onclick="handleSearch()">Search</button>
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
        var ajaxMetaUrl = '/api/v1/hosts?meta=1';
        var ajaxParams = { 'page': 1 }; // Object to hold URL parameters
        var query = '';
        let searchDebounceTimer = null;
        let loadController = null;
        let activeRequestId = 0;
        let lastIsMobile = false;
        let lastViewportIsMobile = window.innerWidth < 768;
        window.currentHosts = null;
        window.currentPagination = null;

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
                    query = q.toLowerCase();
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

            // Restore numeric inputs from URL
            ['maxContractPrice', 'maxStoragePrice', 'maxUploadPrice', 'maxDownloadPrice'].forEach((key) => {
                const input = document.getElementById(key);
                if (input && ajaxParams[key] !== undefined) {
                    input.value = ajaxParams[key];
                }
            });

            // Wire UI events
            document.getElementById('search').addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    handleSearch();
                }
            });
            document.getElementById('search').addEventListener('input', function () {
                clearTimeout(searchDebounceTimer);
                searchDebounceTimer = setTimeout(() => {
                    handleSearch();
                }, 300);
            });
            document.getElementById('versionFilter').addEventListener('change', applyFilters);
            document.getElementById('countryFilter').addEventListener('change', applyFilters);
            document.getElementById('acceptingContractsFilter').addEventListener('change', applyFilters);
            document.getElementById('clearFiltersBtn').addEventListener('click', clearFilters);
            document.getElementById('filtersToggle').addEventListener('click', toggleFiltersPanel);

            // Render header for current viewport
            lastIsMobile = isCompactHostsView();
            lastViewportIsMobile = isMobileViewport();
            if (typeof renderTableHeader === 'function') {
                renderTableHeader();
            }

            syncFilterPanelForViewport();
            loadFilterMeta().finally(() => {
                applyFilters();
            });
        });

        function updateAjaxParams(params) {
            for (const key in params) {
                if (params.hasOwnProperty(key)) {
                    ajaxParams[key] = params[key];
                }
            }
        }

        function setLoadMessage(message) {
            const messageEl = document.getElementById('loadMessage');
            if (messageEl) messageEl.textContent = message || '';
        }

        function setFiltersOpen(isOpen) {
            const content = document.getElementById('filtersContent');
            const toggle = document.getElementById('filtersToggle');
            if (!content || !toggle) return;
            content.hidden = !isOpen;
            toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            toggle.textContent = isOpen ? 'Hide filters' : 'Show filters';
        }

        function toggleFiltersPanel() {
            const content = document.getElementById('filtersContent');
            if (!content) return;
            setFiltersOpen(content.hidden);
        }

        function syncFilterPanelForViewport() {
            const toggle = document.getElementById('filtersToggle');
            if (!toggle) return;
            if (isMobileViewport()) {
                toggle.style.display = 'inline-flex';
                setFiltersOpen(false);
            } else {
                toggle.style.display = 'none';
                setFiltersOpen(true);
            }
        }

        function populateSelect(selectId, defaultLabel, items, currentValue) {
            const select = document.getElementById(selectId);
            if (!select) return;
            const selected = currentValue !== undefined ? String(currentValue) : '';
            select.innerHTML = `<option value="">${defaultLabel}</option>`;
            if (!Array.isArray(items)) return;
            items.forEach((entry) => {
                if (!entry || !entry.value) return;
                const option = document.createElement('option');
                option.value = entry.value;
                option.textContent = `${entry.value} (${entry.count})`;
                if (entry.value === selected) option.selected = true;
                select.appendChild(option);
            });
        }

        async function loadFilterMeta() {
            try {
                const response = await fetch(ajaxMetaUrl, { headers: { 'Accept': 'application/json' } });
                if (!response.ok) throw new Error('Failed to load metadata');
                const meta = await response.json();
                populateSelect('versionFilter', 'All Versions', meta.versions || [], ajaxParams['version'] || '');
                populateSelect('countryFilter', 'All Countries', meta.countries || [], ajaxParams['country'] || '');
            } catch (error) {
                setLoadMessage('Filter metadata is unavailable. You can still browse hosts.');
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
            const showInactive = document.getElementById('activeOnly').checked;

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

            if (showInactive) {
                ajaxParams['showinactive'] = true;
            } else {
                delete ajaxParams['showinactive'];
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

            const searchInput = document.getElementById('search').value.trim();
            if (searchInput) {
                ajaxParams['query'] = searchInput;
                query = searchInput;
            } else {
                delete ajaxParams['query'];
                query = '';
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
            const searchInput = document.getElementById('search').value.trim();
            updateAjaxParams({ query: searchInput });
            query = searchInput;
            applyFilters();
        }

        function renderScore(score) {
            const rounded = Math.max(0, Math.min(10, Math.ceil(score)));
            const hue = (rounded / 10) * 120;
            return `<span class="score" style="color: hsl(${hue}, 70%, 50%);">${rounded}</span>`;
        }

        function isStorageDiffAvailable(host) {
            return Number(host.used_storage_diff_available) === 1;
        }

        function getStorageDiffReason(host) {
            if (host.used_storage_diff_reason === 'missing_latest') {
                return 'Latest hourly sample is unavailable.';
            }
            if (host.used_storage_diff_reason === 'missing_baseline') {
                return '24h baseline sample is unavailable.';
            }
            return '24h delta is unavailable.';
        }

        // Responsive helpers
        function isMobileViewport() {
            return window.innerWidth < 768; // Tailwind md breakpoint
        }

        function isCompactHostsView() {
            const hostsCard = document.querySelector('.host-layout > section.card:last-child');
            const availableWidth = hostsCard ? hostsCard.clientWidth : window.innerWidth;
            // Compact mode needs to account for actual card width, not just viewport width.
            return availableWidth < 960;
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
            const compact = isCompactHostsView();
            const table = document.getElementById('hostTable');
            if (table) table.classList.toggle('compact-table', compact);
            if (compact) {
                thead.innerHTML = `
                    <tr>
                        <th class="px-2 py-2 num rank-col">#</th>
                        <th class="px-2 py-2">Host</th>
                    </tr>
                `;
            } else {
                thead.innerHTML = `
                    <tr>
                        <th class="px-4 py-2 w-8 num">#</th>
                        <th class="px-4 py-2 w-80">Host</th>
                        <th class="px-4 py-2 w-32">Country</th>
                        <th class="px-4 py-2 w-32 num">Used Storage<span class="text-xs text-gray-400 ml-1" title="24h delta is recalculated hourly.">(24h)</span></th>
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
                const colspan = isCompactHostsView() ? 2 : 7;
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
                const diffAvailable = isStorageDiffAvailable(host);
                const growthGB = diffAvailable ? Math.round(Number(host.used_storage_diff) / (1000 * 1000 * 1000)) : 0;
                const growthLabel = diffAvailable
                    ? (growthGB > 0 ? `+${growthGB.toLocaleString(loc)} GB` : `${growthGB.toLocaleString(loc)} GB`)
                    : 'N/A';
                const growthClass = diffAvailable
                    ? (growthGB > 0 ? 'metric-positive' : (growthGB < 0 ? 'metric-negative' : 'metric-neutral'))
                    : 'metric-muted';
                const growthTitle = diffAvailable ? '' : ` title="${getStorageDiffReason(host)}"`;
                const computedIndex = (((currentPage - 1) * perPage) + index + 1);
                const displayRank = (host.filtered_rank && Number(host.filtered_rank) > 0) ? host.filtered_rank : computedIndex;
                if (isCompactHostsView()) {
                    // Mobile: Only two columns (# and Host with details)
                    row.innerHTML = `
                        <td class="border px-2 py-2 num rank-col">${displayRank}</td>
                        <td class="border px-2 py-2 align-top">
                            <a href="/host?id=${host.host_id}" class="host-link hover:underline">${host.net_address}</a>${host.accepting_contracts == 0 ? ' <span class="text-red-500" title="Not accepting contracts">❌</span>' : ''}
                            <div class="text-xs text-gray-300 mt-1 mobile-metrics">
                                <div><span class="metric-label">Country</span><span>${host.country_name}</span></div>
                                <div><span class="metric-label">Used</span><span>${usedTB} TB</span></div>
                                <div><span class="metric-label">Total</span><span>${totalTB} TB</span></div>
                                <div><span class="metric-label">24h Growth</span><span class="${growthClass}"${growthTitle}>${growthLabel}</span></div>
                                <div><span class="metric-label">Price</span><span>${priceSC} SC</span></div>
                                <div><span class="metric-label">Score</span><span>${renderScore(host.total_score)}</span></div>
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

            const maxVisibleButtons = isCompactHostsView() ? 3 : 5;

            // Calculate start and end page numbers for pagination
            let startPage = currentPage - Math.floor(maxVisibleButtons / 2);
            startPage = Math.max(startPage, 1);
            const endPage = Math.min(startPage + maxVisibleButtons - 1, totalPages);

            // Adjust start page again if end page is at the boundary
            startPage = Math.max(endPage - maxVisibleButtons + 1, 1);

            const firstButton = createPaginationButton(1, isCompactHostsView() ? '«' : 'First');
            const prevButton = createPaginationButton(currentPage - 1, isCompactHostsView() ? '‹' : 'Previous');
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

            const nextButton = createPaginationButton(currentPage + 1, isCompactHostsView() ? '›' : 'Next');
            const lastButton = createPaginationButton(totalPages, isCompactHostsView() ? '»' : 'Last');
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
                const totalPages = (window.currentPagination && window.currentPagination.total_pages) ? window.currentPagination.total_pages : 1;
                if (page < 1 || page > totalPages) return;
                updateAjaxParams({ page: page });
                const url = new URL(window.location.href);
                url.search = new URLSearchParams(ajaxParams).toString();
                window.history.replaceState({}, '', url);
                loadData();
            };

            return pageButton;
        }


        function updateStorageDiff(data) {
            for (let i = 0; i < data.length; i++) {
                const host = data[i];
                const storageDiffElement = document.getElementById(`storage-diff-${host.host_id}`);
                const diffAvailable = isStorageDiffAvailable(host);
                const storageDiff = diffAvailable ? Math.round(Number(host.used_storage_diff) / (1000 * 1000 * 1000)) : 0;

                if (storageDiffElement) {
                    if (!diffAvailable) {
                        storageDiffElement.innerHTML = `(N/A)`;
                        storageDiffElement.style.color = 'rgb(156 163 175)';
                        storageDiffElement.title = getStorageDiffReason(host);
                    } else if (storageDiff > 0) {
                        const loc = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
                        storageDiffElement.innerHTML = `(+${storageDiff.toLocaleString(loc)} GB)`;
                        storageDiffElement.style.color = 'green';
                        storageDiffElement.title = '';
                    } else if (storageDiff < 0) {
                        const loc2 = (typeof window !== 'undefined' && window.APP_LOCALE) ? window.APP_LOCALE : undefined;
                        storageDiffElement.innerHTML = `(${storageDiff.toLocaleString(loc2)} GB)`;
                        storageDiffElement.style.color = 'red';
                        storageDiffElement.title = '';
                    } else {
                        storageDiffElement.innerHTML = `(0 GB)`;
                        storageDiffElement.style.color = 'rgb(209 213 219)';
                        storageDiffElement.title = '';
                    }
                }
            }
        }

        function handleShowInactiveChange() {
            applyFilters();
        }

        function clearFilters() {
            const controlIds = ['versionFilter', 'countryFilter', 'maxContractPrice', 'maxStoragePrice', 'maxUploadPrice', 'maxDownloadPrice', 'search'];
            controlIds.forEach((id) => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            const acceptingContractsCheckbox = document.getElementById('acceptingContractsFilter');
            if (acceptingContractsCheckbox) acceptingContractsCheckbox.checked = false;
            const inactiveCheckbox = document.getElementById('activeOnly');
            if (inactiveCheckbox) inactiveCheckbox.checked = false;

            delete ajaxParams['version'];
            delete ajaxParams['country'];
            delete ajaxParams['maxContractPrice'];
            delete ajaxParams['maxStoragePrice'];
            delete ajaxParams['maxUploadPrice'];
            delete ajaxParams['maxDownloadPrice'];
            delete ajaxParams['acceptingContracts'];
            delete ajaxParams['showinactive'];
            delete ajaxParams['query'];
            ajaxParams['page'] = 1;
            query = '';
            applyFilters();
        }

        function showErrorState(message) {
            const tableBody = document.getElementById('hostTableBody');
            renderTableHeader();
            const colspan = isCompactHostsView() ? 2 : 7;
            tableBody.innerHTML = `<tr><td colspan="${colspan}" class="px-4 py-8 text-center text-red-300">${message}</td></tr>`;
            const paginationDiv = document.getElementById('pagination');
            if (paginationDiv) paginationDiv.innerHTML = '';
            showTable();
        }

        // Function to load data from server based on page number and query
        function showLoadingSkeleton() {
            const tableBody = document.getElementById('hostTableBody');
            renderTableHeader();
            const cols = isCompactHostsView() ? 2 : 7;
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

        async function loadData() {
            showLoadingSkeleton();
            setLoadMessage('Loading hosts...');

            if (loadController) {
                loadController.abort();
            }
            loadController = new AbortController();
            const requestId = ++activeRequestId;

            try {
                let url = constructAjaxUrl();
                const response = await fetch(url, {
                    signal: loadController.signal,
                    headers: { 'Accept': 'application/json' }
                });
                if (!response.ok) throw new Error('Request failed');
                const payload = await response.json();

                // Ignore stale responses if a newer request has started.
                if (requestId !== activeRequestId) return;

                const hosts = Array.isArray(payload.hosts) ? payload.hosts : [];
                const pagination = payload.pagination || { total_pages: 1, current_page: 1, per_page: 15, total_rows: 0 };
                window.currentHosts = hosts;
                window.currentPagination = pagination;
                displayData(hosts, pagination);
                setLoadMessage(`Showing ${hosts.length.toLocaleString()} hosts${pagination.total_rows ? ` of ${Number(pagination.total_rows).toLocaleString()}` : ''}.`);
            } catch (error) {
                if (error && error.name === 'AbortError') return;
                showErrorState('Could not load host data. Please retry.');
                setLoadMessage('Request failed.');
            }
        }

        // Re-render on breakpoint changes
        (function setupResizeRerender(){
            window.addEventListener('resize', () => {
                const nowIsMobile = isCompactHostsView();
                const nowViewportIsMobile = isMobileViewport();
                if (nowViewportIsMobile !== lastViewportIsMobile) {
                    lastViewportIsMobile = nowViewportIsMobile;
                    syncFilterPanelForViewport();
                }
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
    /* Two-column layout: keep overall page width, give Hosts table a bit more room on md+ */
    .host-layout{ display: grid; grid-template-columns: 1fr; align-items: start; }
    .host-layout > .card { min-width: 0; }
    @media (min-width: 768px){ /* md */
        .host-layout{ grid-template-columns: clamp(210px, 15vw, 250px) minmax(0, 1fr); column-gap: 1rem; }
        .filters-card { position: sticky; top: 1rem; }
    }

    .filters-toggle {
        display: none;
        width: 100%;
        min-height: 2.5rem;
        align-items: center;
        justify-content: center;
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 999px;
        color: #fff;
        font-size: 0.875rem;
        margin-bottom: 0.5rem;
    }

    .hosts-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        min-width: 0;
    }

    .hosts-toolbar__meta {
        margin-left: 0;
        white-space: normal;
        flex: 1 1 14rem;
        min-width: 0;
    }

    .hosts-toolbar__controls {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        width: auto;
        justify-content: flex-end;
        flex: 1 1 28rem;
        min-width: 0;
        flex-wrap: wrap;
    }

    .hosts-toolbar__sort {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        white-space: nowrap;
    }

    .hosts-toolbar__search {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        width: min(100%, 520px);
        min-width: 0;
        flex: 1 1 20rem;
    }

    #search { flex: 1 1 auto; min-width: 0; }

    .card .card__content > .overflow-x-auto {
        overflow-x: hidden;
        min-width: 0;
    }

    #hostTable {
        width: 100%;
        min-width: 0;
        table-layout: auto;
    }

    #hostTable th,
    #hostTable td {
        white-space: normal;
        overflow-wrap: anywhere;
        word-break: break-word;
    }

    #hostTable th.num,
    #hostTable td.num {
        white-space: nowrap;
    }

    #hostTable.compact-table .rank-col {
        width: 3.5rem;
        max-width: 3.5rem;
    }

    #hostTable.compact-table {
        table-layout: fixed;
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

    .host-link {
        color: #8ec5ff;
        text-decoration: none;
    }

    .host-link::after {
        content: " \2192";
        opacity: 0.7;
        font-size: 0.85em;
    }

    .host-link:hover,
    .host-link:focus-visible {
        color: #b3dbff;
        opacity: 1;
    }

    .mobile-metrics {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.2rem;
    }

    .mobile-metrics > div {
        display: flex;
        justify-content: space-between;
        gap: 0.5rem;
    }

    .metric-label {
        color: rgb(156 163 175);
        margin-right: 0.5rem;
    }

    .metric-positive { color: #22c55e; }
    .metric-negative { color: #ef4444; }
    .metric-neutral { color: rgb(209 213 219); }
    .metric-muted { color: rgb(156 163 175); }

    /* Mobile refinements: tighter cards, edge-to-edge table, smaller pagination */
    @media (max-width: 767.98px) {
        /* Reduce card chrome to free space */
        section.card { padding: 0.75rem; border-radius: 1rem; }
        /* Container side padding */
        #main-content.sg-container { padding-left: 0.75rem; padding-right: 0.75rem; gap: 1.5rem; }
        .table-clean th, .table-clean td { padding-left: 0.5rem; padding-right: 0.5rem; }
        /* Bleed the overflow container to the card edges */
        .card .card__content > .overflow-x-auto { margin-left: -0.75rem; margin-right: -0.75rem; }
        .hosts-toolbar { flex-direction: column; align-items: stretch; }
        .hosts-toolbar__meta {
            margin-left: 0;
            white-space: normal;
            flex: 0 0 auto;
            min-height: 0;
        }
        .hosts-toolbar__meta:empty { display: none; }
        .hosts-toolbar__controls {
            flex-direction: column;
            align-items: stretch;
            justify-content: flex-start;
            gap: 0.5rem;
            flex: 0 0 auto;
            min-height: 0;
        }
        .hosts-toolbar__sort { width: 100%; justify-content: space-between; }
        .hosts-toolbar__sort select { flex: 1 1 auto; }
        .hosts-toolbar__search {
            width: 100%;
            margin-left: 0;
            flex: 0 0 auto;
            flex-wrap: nowrap;
            align-items: center;
        }
        .hosts-toolbar__search .button {
            flex: 0 0 auto;
            white-space: nowrap;
        }
        #search {
            width: auto !important;
            flex: 1 1 auto;
            min-width: 0;
        }
        .filters-toggle { display: inline-flex; }
        /* Pagination: wrap and shrink */
        #pagination { display: flex; flex-wrap: wrap; gap: 0.25rem; }
        .pagination-button { padding: 0.25rem 0.5rem; font-size: 0.875rem; margin-right: 0 !important; }
        .pagination-button--digit { min-width: 36px; }
    }

    /* Tablet controls wrap cleanly */
    @media (min-width: 768px) and (max-width: 1100px) {
        .hosts-toolbar { align-items: flex-start; }
        .hosts-toolbar__controls { flex-wrap: wrap; justify-content: flex-start; }
        .hosts-toolbar__search { margin-left: 0; width: 100%; max-width: 100%; }
    }
</style>
<?php render_footer(); ?>
