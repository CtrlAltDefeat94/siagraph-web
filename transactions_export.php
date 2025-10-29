<?php
require_once 'include/layout.php';
render_header(
    'SiaGraph - Transaction Export',
    'Download or view transaction history for specific addresses.'
);
?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2">
        <i class="bi bi-file-earmark-arrow-down me-2"></i>Transaction Export
    </h1>
    <p class="text-center mb-4">
        Specify an address and the earliest date to download aggregated transactions as a CSV file.
    </p>

    <div class="sg-container__row">
        <div class="sg-container__row-content sg-container__row-content--center">
            <section class="card" style="max-width: 640px; width: 100%;">
                <h2 class="card__heading">Export Parameters</h2>
                <div class="card__content">
                    <form id="tx-export-form" class="d-flex flex-column gap-3" autocomplete="off" novalidate>
                        <div>
                            <label for="tx-address" class="form-label">Address</label>
                            <input
                                type="text"
                                id="tx-address"
                                class="form-control"
                                name="address"
                                placeholder="Enter a 76-character Sia address"
                                pattern="[0-9a-fA-F]{76}"
                                required
                            >
                
                        </div>

                        <div>
                            <label for="tx-date" class="form-label">Include transactions from</label>
                            <input
                                type="date"
                                id="tx-date"
                                class="form-control"
                                name="date"
                                required
                            >
                           
                        </div>

                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <button type="submit" class="btn btn-primary" id="tx-download-btn">
                                <i class="bi bi-download me-1"></i>Download CSV
                            </button>
                            <button type="button" class="btn btn-outline-secondary" id="tx-view-raw-btn">
                                <i class="bi bi-box-arrow-up-right me-1"></i>View Raw Data
                            </button>
                            <span class="small status-indicator" id="tx-status" role="status" aria-live="polite"></span>
                        </div>
                    </form>
                </div>
            </section>
        </div>
    </div>
</section>
<?php render_footer(['js/transactions-export.js']); ?>
