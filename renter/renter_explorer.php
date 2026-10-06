<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
render_header('SiaGraph - Renter Explorer', 'Explore renter wallets, storage, and contract activity.', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/components/data-page.css'), ENT_QUOTES, 'UTF-8') . '">',
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/pages/renters.css'), ENT_QUOTES, 'UTF-8') . '">',
]);
?>
<section id="main-content" class="sg-container sg-container--wide sg-data-page renters-page renter-directory" data-renter-page="directory">
    <div class="renter-explorer-heading">
        <h1 class="sg-data-title">Renter Explorer</h1>
        <a href="/renter_distribution" class="renter-explorer-hero__link">Storage distribution <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <section class="renter-results" aria-labelledby="renter-results-heading">
        <div class="renter-results-toolbar">
            <h2 id="renter-results-heading" class="card__heading">Renters <span id="renterStatus" class="renter-results__status sg-data-status" role="status" aria-live="polite">Loading…</span></h2>
        </div>
        <div class="sg-data-scroll renter-table-scroll" role="region" aria-label="Renter results" tabindex="0">
            <table id="renterTable" class="table-clean text-white min-w-full table-loading">
                <thead><tr><th scope="col">Renter wallet address</th><th scope="col">Contracted storage</th><th scope="col">Active contracts</th><th scope="col">Active hosts</th><th scope="col">Last activity</th></tr></thead>
                <tbody id="renterResults"><tr><td colspan="5">Loading…</td></tr></tbody>
            </table>
        </div>
        <nav id="renterPagination" class="renter-pagination" aria-label="Renter pages"><button id="renterPrevious" type="button" disabled>Previous</button><span id="renterPage"></span><button id="renterNext" type="button" disabled>Next</button></nav>
    </section>
</section>
<?php render_footer(['js/renter-format.js', 'js/renter-currency.js', 'js/renters.js']); ?>
