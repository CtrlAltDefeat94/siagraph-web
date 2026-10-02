<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
require dirname(__DIR__) . '/include/components/renter_history.php';
render_header('SiaGraph - Renter', 'Renter wallet statistics and daily history.', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/pages/renters.css'), ENT_QUOTES, 'UTF-8') . '">',
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/pages/renter.css'), ENT_QUOTES, 'UTF-8') . '">',
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/pages/active-contracts.css'), ENT_QUOTES, 'UTF-8') . '">',
]);
?>
<section id="main-content" class="sg-container renters-page renter-profile" data-renter-page="detail">
    <div class="renter-profile-top">
        <section class="card renter-hero">
            <div class="renter-hero-top">
                <div><div class="renter-kicker">Renter Explorer</div><h1>Renter wallet</h1></div>
                <div class="renter-actions"><a class="button text-sm" href="/renters">Back to renters</a><a id="renterExplorerLink" class="button text-sm" hidden>View in explorer</a></div>
            </div>
            <div id="renterIdentity"></div>
            <div id="renterChips" class="renter-chips"></div>
            <p id="renterStatus" role="status" aria-live="polite">Loading renter…</p>
            <div id="renterCandidates"></div>
        </section>
        <section class="card renter-observation-card" aria-labelledby="renterActivityHeading">
            <h2 id="renterActivityHeading">Wallet activity</h2>
            <div id="renterObservations"></div>
            <p id="renterFreshness" class="renter-section-copy"></p>
        </section>
    </div>
    <div id="renterDetail" hidden>
        <section class="card renter-summary" aria-label="Renter summary"><div id="renterMetrics" class="renter-metrics"></div></section>
        <div class="renter-tabs" role="tablist" aria-label="Renter sections">
            <?php foreach (['overview' => 'Overview', 'contracts' => 'Contracts', 'economics' => 'Economics', 'charts' => 'History'] as $key => $label): ?>
                <button type="button" role="tab" id="renter-tab-<?php echo $key; ?>" aria-controls="renter-panel-<?php echo $key; ?>" aria-selected="<?php echo $key === 'overview' ? 'true' : 'false'; ?>" tabindex="<?php echo $key === 'overview' ? '0' : '-1'; ?>" data-renter-tab="<?php echo $key; ?>"><?php echo $label; ?></button>
            <?php endforeach; ?>
        </div>
        <section id="renter-panel-overview" role="tabpanel" aria-labelledby="renter-tab-overview" tabindex="0">
            <div class="renter-panel-grid">
                <section class="card"><h2>Contract participation</h2><div id="renterOverviewContracts" class="renter-facts"></div><button type="button" class="renter-section-link" data-renter-jump="contracts">View contract details →</button></section>
                <section class="card"><h2>Contract funds</h2><div id="renterOverviewFunds" class="renter-facts"></div><button type="button" class="renter-section-link" data-renter-jump="economics">View economics →</button></section>
            </div>
        </section>
        <section id="renter-panel-contracts" role="tabpanel" aria-labelledby="renter-tab-contracts" tabindex="0" hidden>
            <div class="renter-panel-grid">
                <section class="card"><h2>Contract activity</h2><div id="renterContractMetrics" class="renter-facts"></div></section>
                <section class="card"><h2 title="These windows may overlap; a contract can appear in more than one window.">Upcoming expirations</h2><div id="renterExpirationMetrics" class="renter-metrics renter-expiration-cards"></div></section>
            </div>
            <section class="card"><div class="renter-hero-top"><h2>Current contract participation</h2><a id="renterBrowseContracts" class="button text-sm" hidden>Browse contracts →</a></div><div data-active-contracts data-kind="renter" data-summary-only></div></section>
        </section>
        <section id="renter-panel-economics" role="tabpanel" aria-labelledby="renter-tab-economics" tabindex="0" hidden>
            <section class="card"><h2 title="Committed host revenue is not realized spending.">Renter economics</h2><div id="renterEconomicMetrics" class="renter-metrics"></div></section>
            <?php render_renter_history('economics'); ?>
        </section>
        <section id="renter-panel-charts" role="tabpanel" aria-labelledby="renter-tab-charts" tabindex="0" hidden>
            <?php render_renter_history('storage'); ?>
            <?php render_renter_history('contracts'); ?>
        </section>
    </div>
</section>
<?php render_footer(['js/renter-format.js', 'js/renter-currency.js', 'js/active-contracts.js', 'js/renters.js']); ?>
