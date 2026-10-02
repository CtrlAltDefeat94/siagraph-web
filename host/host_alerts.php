<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';

$prefillPublicKey = trim((string) ($_GET['public_key'] ?? $_GET['publicKey'] ?? ''));

render_header('SiaGraph - Host Alerts', 'SiaGraph - Host Alerts', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/components/data-page.css'), ENT_QUOTES, 'UTF-8') . '">',
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('css/pages/host-alerts.css'), ENT_QUOTES, 'UTF-8') . '">'
]);
?>
<section id="main-content" class="sg-container sg-data-page host-alerts-page">
    <section class="host-alerts-hero">
        <div>
            <div class="host-alerts-hero__kicker">Host monitoring</div>
            <h1 class="sg-data-title">Host Alerts</h1>
            <p class="host-alerts-hero__copy">
                Subscribe to operational notifications for a Sia host. Alerts focus on issues that can affect renter availability, contract health, or remaining capacity.
            </p>
            <p class="host-alerts-hero__note">
                <strong>Delivery interval:</strong> critical errors repeat every 4 hours until resolved. Warnings are sent once, and low-capacity alerts are sent once per severity level.
            </p>
        </div>
        <a href="/host_explorer" class="host-alerts-hero__link">
            Find host in explorer
            <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </a>
    </section>

    <section class="card host-alerts-workspace">
        <div class="host-alerts-subscribe">
            <div class="host-alerts-section-head">
                <h2 class="card__heading">Subscribe</h2>
                <p class="host-alerts-section-copy">
                    Use the host public key and choose where SiaGraph should send notifications.
                </p>
            </div>
            <form id="hostAlertsForm" class="host-alerts-form">
                <div class="host-alerts-field host-alerts-field--full">
                    <label for="hostIdentifier" class="form-label">Host Public Key</label>
                    <input
                        type="text"
                        class="form-control"
                        id="hostIdentifier"
                        placeholder="ed25519:..."
                        value="<?php echo htmlspecialchars($prefillPublicKey, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >
                </div>
                <div class="host-alerts-field">
                    <label for="service" class="form-label">Delivery Method</label>
                    <select class="form-select" id="service" required>
                        <option value="email">Email</option>
                        <option value="pushover">Pushover</option>
                        <option value="telegram">Telegram</option>
                    </select>
                </div>
                <div class="host-alerts-field">
                    <label for="recipient" class="form-label">Recipient</label>
                    <input type="text" class="form-control" id="recipient" placeholder="you@example.com" required>
                    <div id="telegramInstructions" class="form-text is-hidden">
                        Start a chat with <a href="https://t.me/Siagraph_bot" target="_blank" rel="noopener noreferrer"><strong>@Siagraph_bot</strong></a>
                        and type <code>/start</code> to get your chat ID.
                    </div>
                </div>
                <div class="host-alerts-actions">
                    <div id="subscriptionStatus" class="host-alerts-status" aria-live="polite"></div>
                    <button type="submit" class="btn btn-sm btn-brand" id="submitSubscriptionBtn">Subscribe</button>
                </div>
            </form>
        </div>

        <section class="host-alerts-triggers">
            <div class="host-alerts-section-head">
                <h2 class="card__heading">What Triggers Alerts</h2>
            </div>
            <div class="host-alerts-rule-list">
                <div class="host-alerts-rule">
                    <span class="host-alerts-rule__icon"><i class="bi bi-wifi-off" aria-hidden="true"></i></span>
                    <div>
                        <strong>Connectivity issues</strong>
                        <span>Host timeout, response failures, or sync-related problems.</span>
                    </div>
                </div>
                <div class="host-alerts-rule">
                    <span class="host-alerts-rule__icon"><i class="bi bi-wallet2" aria-hidden="true"></i></span>
                    <div>
                        <strong>Low wallet balance</strong>
                        <span>Insufficient balance risk for new or existing contracts.</span>
                    </div>
                </div>
                <div class="host-alerts-rule">
                    <span class="host-alerts-rule__icon"><i class="bi bi-hdd" aria-hidden="true"></i></span>
                    <div>
                        <strong>Low capacity</strong>
                        <span>Free storage drops into a tracked warning level.</span>
                    </div>
                </div>
            </div>
        </section>
    </section>
</section>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("hostAlertsForm");
    const submitBtn = document.getElementById("submitSubscriptionBtn");
    const hostIdentifierInput = document.getElementById("hostIdentifier");
    const serviceInput = document.getElementById("service");
    const recipientInput = document.getElementById("recipient");
    const statusDiv = document.getElementById("subscriptionStatus");
    const telegramInstructions = document.getElementById("telegramInstructions");

    function setStatus(message, type) {
        statusDiv.textContent = message;
        statusDiv.classList.remove("text-danger", "text-success", "text-light");
        if (type === "success") {
            statusDiv.classList.add("text-success");
        } else if (type === "error") {
            statusDiv.classList.add("text-danger");
        } else {
            statusDiv.classList.add("text-light");
        }
    }

    function updateFormFields() {
        const selectedService = serviceInput.value.trim();
        if (selectedService === "telegram") {
            telegramInstructions.classList.remove("is-hidden");
            recipientInput.placeholder = "Telegram Chat ID (e.g. 12345678)";
            return;
        }
        telegramInstructions.classList.add("is-hidden");
        recipientInput.placeholder = selectedService === "pushover" ? "Pushover user token" : "you@example.com";
    }

    function normalizePublicKey(identifier) {
        const trimmed = identifier.trim();
        if (!trimmed) return null;
        return /^ed25519:[a-f0-9]{64}$/i.test(trimmed) ? trimmed : null;
    }

    updateFormFields();
    serviceInput.addEventListener("change", updateFormFields);

    form.addEventListener("submit", async function (event) {
        event.preventDefault();

        const service = serviceInput.value.trim();
        const recipient = recipientInput.value.trim();
        const hostIdentifier = hostIdentifierInput.value.trim();

        if (!service || !recipient || !hostIdentifier) {
            setStatus("Please complete all fields.", "error");
            return;
        }

        submitBtn.disabled = true;

        try {
            const publicKey = normalizePublicKey(hostIdentifier);
            if (!publicKey) {
                setStatus("Invalid public key. Use format: ed25519: followed by 64 hex characters.", "error");
                submitBtn.disabled = false;
                return;
            }

            setStatus("Submitting...", "neutral");
            const response = await fetch("/api/v1/alerts/subscribe", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({
                    public_key: publicKey,
                    service: service,
                    recipient: recipient
                })
            });

            const data = await response.json();
            if (response.ok) {
                setStatus("Successfully subscribed!", "success");
                recipientInput.value = "";
            } else {
                setStatus((data && data.error) ? data.error : "Subscription failed.", "error");
            }
        } catch (err) {
            setStatus("An error occurred. Please try again.", "error");
            console.error("Subscription error:", err);
        } finally {
            submitBtn.disabled = false;
        }
    });
});
</script>
<?php render_footer(); ?>
