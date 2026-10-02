<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/include/layout.php';
$hostKey = $_GET['public_key'] ?? $_GET['host_public_key'] ?? '';
$hostKey = is_string($hostKey) ? trim($hostKey) : '';
if (!preg_match('/^(?:ed25519:)?[a-f0-9]{64}$/iD', $hostKey)) $hostKey = '';
$hostKey = preg_replace('/^ed25519:/', '', strtolower($hostKey));
$lastDate = (new DateTimeImmutable('yesterday', new DateTimeZone('UTC')))->format('Y-m-d');
render_header('SiaGraph - Host Revenue Export', 'Export completed V2 contract revenue, rollovers, forfeitures and collateral losses.', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/components/data-page.css'), ENT_QUOTES, 'UTF-8') . '">',
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/host-revenue-export.css'), ENT_QUOTES, 'UTF-8') . '">'
]);
?>
<section id="main-content" class="sg-container sg-data-page host-revenue-export-page">
    <section class="host-revenue-heading">
        <div>
            <div class="host-revenue-heading__kicker">Host tools</div>
            <h1 class="sg-data-title">Host Revenue Export</h1>

            <?php if ($hostKey !== ''): ?>
                <p><a class="button text-sm" href="/host?public_key=<?php echo rawurlencode($hostKey); ?>">Back to host</a></p>
            <?php endif; ?>
        </div>
    </section>
    <section class="host-revenue-layout">
        <section class="card host-revenue-card">
            <h2 class="card__heading">Export Parameters</h2>

            <div class="card__content">
                <form id="host-revenue-export-form" class="host-revenue-form" action="/api/v2/contracts/tax-export.php" method="get" target="_blank" rel="noopener noreferrer" autocomplete="off">
                    <div class="host-revenue-field">
                        <label for="revenue-host-key" class="form-label">Host public key</label>
                        <input type="text" id="revenue-host-key" name="host_public_key" class="form-control"
                            value="<?php echo htmlspecialchars($hostKey, ENT_QUOTES, 'UTF-8'); ?>"
                            placeholder="Enter a 64-character host public key" pattern="([eE][dD]25519:)?[0-9a-fA-F]{64}" maxlength="72" spellcheck="false" autocapitalize="none" required>
                    </div>
                    <div class="host-revenue-form__split">
                        <div class="host-revenue-field">
                            <label for="revenue-from" class="form-label">Payout maturity dates from</label>
                            <input type="date" id="revenue-from" name="from" class="form-control" value="2025-07-01" min="2025-07-01" max="<?php echo $lastDate; ?>" required>
                        </div>
                        <div class="host-revenue-field">
                            <label for="revenue-to" class="form-label">Payout maturity dates through</label>
                            <input type="date" id="revenue-to" name="to" class="form-control" value="<?php echo $lastDate; ?>" min="2025-07-01" max="<?php echo $lastDate; ?>" required>
                        </div>
                    </div>
                    <div class="host-revenue-field">
                        <label for="revenue-currency" class="form-label">Price currency</label>
                        <select id="revenue-currency" name="currency" class="form-select">
                            <option value="eur">Euro (EUR)</option>
                            <option value="usd">US Dollar (USD)</option>
                            <option value="cad">Canadian Dollar (CAD)</option>
                            <option value="cny">Chinese Yuan (CNY)</option>
                            <option value="gbp">British Pound (GBP)</option>
                            <option value="jpy">Japanese Yen (JPY)</option>
                            <option value="rub">Russian Ruble (RUB)</option>
                            <option value="btc">Bitcoin (BTC)</option>
                            <option value="eth">Ether (ETH)</option>
                        </select>
                    </div>
                    <div class="host-revenue-actions">
                        <button type="submit" name="format" value="csv" class="button" id="revenue-download-btn"><i class="bi bi-download me-1" aria-hidden="true"></i>Download CSV</button>
                        <button type="submit" name="format" value="json" class="button" id="revenue-raw-btn"><i class="bi bi-box-arrow-up-right me-1" aria-hidden="true"></i>View Raw Data</button>
                        <span id="revenue-status" class="sg-data-status" role="status" aria-live="polite"></span>
                    </div>
                </form>
            </div>
        </section>
    </section>
    <details class="card host-revenue-card host-revenue-method">
        <summary class="host-revenue-method__summary"><h2 class="card__heading">Method and accounting decisions</h2></summary>
        <div class="card__content">
            <section class="host-revenue-method__item">
                <h3>Recognition date.</h3>
                <p>This export assigns unlocked revenue and realized losses to the UTC date on which the contract payout reaches its protocol maturity height: <code>resolution height + 144 blocks</code>. This is an objective blockchain date when the output becomes spendable.</p>
            </section>
            <section class="host-revenue-method__item">
                <h3>Contract outcomes.</h3>
                <p>A storage proof releases the contract’s earned revenue. A term renewal pays released revenue and may create a successor contract. A refresh can roll revenue into its successor; rolled revenue is not included in realized-revenue totals and is counted only if a later contract releases it. An expiration forfeits contract revenue and may burn host collateral.</p>
            </section>
            <section class="host-revenue-method__item">
                <h3>Revenue derivation.</h3>
                <p>Revenue is not a separate value published by the explorer. The successful host output contains both the host’s own collateral and its revenue. SiaGraph subtracts the total collateral committed by the host from that output; if the result would be negative, it uses zero. A storage proof classifies the result as unlocked revenue, while an expiration classifies it as forfeited revenue.</p>
            </section>
            <section class="host-revenue-method__item">
                <h3>Renewal derivation.</h3>
                <p>A renewal can reveal a newer contract state than the parent record because V2 revisions are normally exchanged off-chain. SiaGraph first adds the amount paid directly to the host at renewal to the host amount carried into the successor, then subtracts the parent contract’s total host collateral. If the successor keeps the same proof and expiration heights, the operation is a refresh: the portion of that revenue present in the successor remains rolled, and the remainder becomes unlocked. If the contract duration changes, all final parent revenue becomes unlocked.</p>
            </section>
            <section class="host-revenue-method__item">
                <h3>Loss reconciliation.</h3>
                <p>For an expiration, <code>protocol burn = forfeited revenue + collateral lost</code>. Forfeited revenue was never received. Collateral lost represents host principal that was committed to the contract and not returned. Whether a receipt or loss is taxable or deductible is determined by the applicable jurisdiction.</p>
            </section>
            <section class="host-revenue-method__item">
                <h3>SC conversion.</h3>
                <p>One Siacoin equals <code>10<sup>24</sup></code> Hastings. Every SC amount is calculated by dividing its integer Hastings value by <code>10<sup>24</sup></code>; the export preserves 24 fractional digits.</p>
            </section>
            <section class="host-revenue-method__item">
                <h3>Daily price.</h3>
                <p>SiaGraph records Siacoin prices sourced from CoinGecko and returns the arithmetic mean of all valid observations it recorded for the payout-maturity UTC date. Using a daily average reduces the influence of brief intraday price spikes, including short-lived pump-and-dump events. It is not a closing price or a volume-weighted price. A price is blank only when SiaGraph has no valid observation for that date.</p>
            </section>
            <section class="host-revenue-method__item">
                <h3>Currency calculation.</h3>
                <p>The export deliberately does not add redundant per-row currency-value columns. Reproduce a value with <code>unlocked revenue in SC × daily-average SC price</code>, or <code>collateral lost in SC × daily-average SC price</code>. No minor-unit or jurisdiction-specific rounding is applied.</p>
            </section>
            <section class="host-revenue-method__item">
                <h3>Verification.</h3>
                <p>Contract IDs, successor IDs and resolution transaction IDs allow each row to be checked against the Sia blockchain. The downloaded filename contains the complete normalized host public key, selected maturity period and price currency.</p>
            </section>
        </div>
    </details>
</section>
<?php render_footer(['js/host-revenue-export.js']); ?>
