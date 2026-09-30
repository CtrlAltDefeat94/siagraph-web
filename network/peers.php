<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
render_header('SiaGraph - Peer Explorer', 'SiaGraph - Peer Explorer', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/peers.css'), ENT_QUOTES, 'UTF-8') . '">'
]);
?>
<section id="main-content" class="sg-container peers-page">
    <section class="card peers-hero">
        <div>
            <div class="peers-hero__kicker">Peer discovery</div>
            <h1 class="peers-hero__title">Peer Explorer</h1>
            <p class="peers-hero__copy">
                Use recently synced peers to bootstrap a node or check reachable addresses across supported Sia networks.
            </p>
            <p class="peers-hero__note">
                Click an address to copy it. Lists are sampled from peers seen recently by SiaGraph.
            </p>
        </div>
        <div id="peersStatus" class="peers-status" aria-live="polite">Loading peers…</div>
    </section>

    <section class="peers-layout">
        <section class="card peers-card">
            <h2 class="card__heading">Mainnet Peers</h2>
            <p class="peers-section-copy">Recently synced peers for the main Sia network.</p>
            <div class="card__content">
                <div class="peers-table-wrap">
                    <table id="mainnetTable" class="peers-table">
                        <thead>
                            <tr>
                                <th>Address</th>
                                <th class="text-end">Protocol Version</th>
                                <th class="text-end">Last Scanned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="3" class="text-center">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="card peers-card">
            <h2 class="card__heading">Zen Peers</h2>
            <p class="peers-section-copy">Recently synced peers for the Zen network.</p>
            <div class="card__content">
                <div class="peers-table-wrap">
                    <table id="zenTable" class="peers-table">
                        <thead>
                            <tr>
                                <th>Address</th>
                                <th class="text-end">Protocol Version</th>
                                <th class="text-end">Last Scanned</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td colspan="3" class="text-center">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </section>
</section>
<?php render_footer(['js/peers.js']); ?>
