<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';

render_header(
    'SiaGraph - Renter Distribution',
    'Largest renters by utilized storage with remaining renters grouped into an Others slice.',
    [
        '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/components/data-page.css'), ENT_QUOTES, 'UTF-8') . '">',
        '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/renter-distribution.css'), ENT_QUOTES, 'UTF-8') . '">'
    ]
);
?>
<section id="main-content" class="sg-container sg-data-page renter-distribution-page">
    <h1 class="sg-data-title">Renter Storage Distribution</h1>
    <p class="renter-distribution-description">View the largest renter wallets by contracted storage and their share of the network. <a href="/renters">Browse renter wallets and daily history</a></p>
    <p class="renter-distribution-note">Based on blockchain contract data; values are estimates and may differ from host-reported totals.</p>

    <section class="renter-distribution-layout">
        <section class="card renter-distribution-chart-card">
            <div class="renter-distribution-card-head">
                <h2 class="card__heading">Share of Utilized Storage</h2>
                <div id="renterDistributionStatus" class="renter-distribution-status">Loading renter data…</div>
            </div>
            <div class="card__content">
                <div id="renterChartContainer" class="renter-distribution-chart-wrap">
                    <canvas id="renterDistributionChart" class="sg-chart-canvas sg-chart-canvas--compact"></canvas>
                </div>
            </div>
        </section>

        <section class="card renter-distribution-table-card">
            <h2 class="card__heading">Largest Renters</h2>
            <p class="renter-distribution-section-copy">Top renter wallets, with remaining storage participants grouped as Others.</p>
            <div class="card__content">
                <div id="renterTableContainer" class="renter-distribution-table-wrap">
                    <table class="renter-distribution-table">
                        <thead>
                            <tr>
                                <th scope="col">Rank</th>
                                <th scope="col">Wallet</th>
                                <th scope="col" class="text-end">Filesize</th>
                                <th scope="col" class="text-end">Share</th>
                            </tr>
                        </thead>
                        <tbody id="renterDistributionTableBody">
                            <tr><td colspan="4" class="text-center">Loading…</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </section>
</section>
<?php render_footer(['js/renter-format.js', 'js/renter-distribution.js']); ?>
