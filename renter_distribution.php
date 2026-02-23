<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';

render_header(
    'SiaGraph - Renter Distribution',
    'Largest renters by utilized storage with remaining renters grouped into an Others slice.'
);
?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2">
        <i class="bi bi-pie-chart me-2"></i>Renter Storage Distribution
    </h1>
    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content">
            <div class="sg-container__column">
                <section class="card">
                    <h2 class="card__heading">Share of Utilized Storage</h2>
                    <div class="card__content">
                        <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
                            <div id="renterDistributionStatus" class="small">Loading renter data…</div>
                        </div>
                        <div class="row g-3 align-items-start">
                            <div class="col-12 col-lg-6">
                                <div id="renterChartContainer" class="graph-container">
                                    <canvas id="renterDistributionChart" height="420" style="max-height:70vh;width:100%;"></canvas>
                                </div>
                            </div>
                            <div class="col-12 col-lg-6">
                                <div id="renterTableContainer" class="table-responsive mb-0">
                                    <table class="table table-dark table-striped table-bordered align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th scope="col">Rank</th>
                                                <th scope="col" class="text-end">Filesize</th>
                                                <th scope="col" class="text-end">Share</th>
                                            </tr>
                                        </thead>
                                        <tbody id="renterDistributionTableBody">
                                            <tr><td colspan="3" class="text-center">Loading…</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <p class="mb-0 mt-3 pt-2 text-end" style="font-size:0.9rem; opacity:0.9; border-top:1px solid rgba(255,255,255,0.12);">
                            <sup>*</sup> Based on blockchain contract data; values are estimates.
                        </p>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>
<?php render_footer(['js/renter-distribution.js']); ?>
