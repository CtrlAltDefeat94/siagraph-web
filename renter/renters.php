<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
render_header('SiaGraph - Renters', 'Explore renter wallets, storage, and contract activity.', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/pages/renters.css'), ENT_QUOTES, 'UTF-8') . '">',
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/pages/renter.css'), ENT_QUOTES, 'UTF-8') . '">',
]);
?>
<section id="main-content" class="sg-container renters-page renter-directory" data-renter-page="directory">
    <section class="card renter-hero"><div class="renter-hero-top"><div><div class="renter-kicker">Renter Explorer</div><h1>Renters</h1><p class="renter-section-copy">Explore wallet participation, contracted storage, and network history.</p></div><a class="button text-sm" href="/renter_distribution">Storage distribution →</a></div></section>
    <section class="card"><h2>Network summary</h2><p id="renterOverviewStatus" role="status">Loading…</p><div id="renterOverview" class="renter-metrics"></div></section>
    <div class="renter-tabs" role="tablist" aria-label="Renter explorer sections">
        <button type="button" role="tab" id="renter-tab-wallets" data-renter-tab="wallets" aria-controls="renter-panel-wallets" aria-selected="true">Wallets</button>
        <button type="button" role="tab" id="renter-tab-history" data-renter-tab="history" aria-controls="renter-panel-history" aria-selected="false" tabindex="-1">History</button>
    </div>
    <section id="renter-panel-wallets" role="tabpanel" aria-labelledby="renter-tab-wallets" tabindex="0" class="card">
        <h2>Renter wallets</h2>
        <p class="renter-section-copy">Select a wallet for its summary, host distribution, and contract browser.</p>
        <form id="renterSearch" class="renter-controls">
            <label>Wallet address or renter public key<input name="search" type="search" maxlength="78" placeholder="Exact address or public key"></label>
            <label>Wallets<select name="active"><option value="1">With active contracts</option><option value="0">All observed wallets</option></select></label>
            <label>Sort by<select name="sort"><option value="contracted_filesize">Contracted storage</option><option value="active_contracts">Active contracts</option><option value="active_hosts">Active hosts</option><option value="last_active">Last activity</option></select></label>
            <button type="submit">Search</button>
        </form>
        <p id="renterStatus" role="status" aria-live="polite">Loading renters…</p>
        <div id="renterResults" class="renter-table-wrap"></div>
        <nav class="renter-controls" aria-label="Renter pages"><button id="renterPrevious" disabled>Previous</button><span id="renterPage"></span><button id="renterNext" disabled>Next</button></nav>
    </section>
    <section id="renter-panel-history" role="tabpanel" aria-labelledby="renter-tab-history" tabindex="0" hidden>
        <?php require dirname(__DIR__) . '/include/components/renter_history.php'; render_renter_history('network'); ?>
    </section>
</section>
<?php render_footer(['js/renter-format.js', 'js/renter-currency.js', 'js/renters.js']); ?>
