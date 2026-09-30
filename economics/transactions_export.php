<?php
require_once dirname(__DIR__) . '/include/layout.php';
render_header(
    'SiaGraph - Transaction Export',
    'Download or view transaction history for specific addresses.',
    [
        '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/transactions-export.css'), ENT_QUOTES, 'UTF-8') . '">'
    ]
);
?>
<section id="main-content" class="sg-container transactions-export-page">
    <section class="card transactions-export-hero">
        <div>
            <div class="transactions-export-hero__kicker">Address tools</div>
            <h1 class="transactions-export-hero__title">Transaction Export</h1>
            <p class="transactions-export-hero__copy">
                Download address transaction history as CSV, or inspect the raw API response before importing the data elsewhere.
            </p>
        </div>
    </section>

    <section class="transactions-export-layout">
        <section class="card tx-export-card">
            <h2 class="card__heading">Export Parameters</h2>
            <p class="transactions-export-section-copy">
                Provide a Sia address, choose the starting date, and select the fiat currency used for historical conversion columns.
            </p>
            <div class="card__content">
                <form id="tx-export-form" class="tx-export-form" autocomplete="off" novalidate>
                    <div class="tx-export-field">
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

                    <div class="tx-export-form__split">
                        <div class="tx-export-field">
                            <label for="tx-currency" class="form-label">Currency</label>
                            <select id="tx-currency" class="form-select" name="currency">
                                <option value="usd">US Dollar (USD)</option>
                                <option value="eur">Euro (EUR)</option>
                                <option value="cad">Canadian Dollar (CAD)</option>
                                <option value="cny">Chinese Yuan (CNY)</option>
                                <option value="gbp">British Pound (GBP)</option>
                                <option value="jpy">Japanese Yen (JPY)</option>
                                <option value="rub">Russian Ruble (RUB)</option>
                            </select>
                        </div>

                        <div class="tx-export-field">
                            <label for="tx-date" class="form-label">Include transactions from</label>
                            <input
                                type="date"
                                id="tx-date"
                                class="form-control"
                                name="date"
                                required
                            >
                        </div>
                    </div>

                    <div class="tx-export-actions">
                        <button type="submit" class="btn btn-primary" id="tx-download-btn">
                            <i class="bi bi-download me-1"></i>Download CSV
                        </button>
                        <button type="button" class="btn btn-outline-secondary" id="tx-view-raw-btn">
                            <i class="bi bi-box-arrow-up-right me-1"></i>View Raw Data
                        </button>
                        <span class="status-indicator" id="tx-status" role="status" aria-live="polite"></span>
                    </div>
                </form>
            </div>
        </section>

        <aside class="card transactions-export-context">
            <h2 class="card__heading">What Gets Exported</h2>
            <div class="card__content">
                <ul class="transactions-export-list">
                    <li>
                        <span class="transactions-export-list__icon"><i class="bi bi-calendar-range" aria-hidden="true"></i></span>
                        <span>Transactions from the selected start date onward.</span>
                    </li>
                    <li>
                        <span class="transactions-export-list__icon"><i class="bi bi-cash-coin" aria-hidden="true"></i></span>
                        <span>SC amounts with historical fiat conversion in the selected currency.</span>
                    </li>
                    <li>
                        <span class="transactions-export-list__icon"><i class="bi bi-code-slash" aria-hidden="true"></i></span>
                        <span>Raw JSON is available for verification or custom processing.</span>
                    </li>
                </ul>
            </div>
        </aside>
    </section>
</section>
<?php render_footer(['js/transactions-export.js']); ?>
