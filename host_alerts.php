<?php
require_once 'bootstrap.php';
require_once 'include/layout.php';

$prefillPublicKey = trim((string) ($_GET['public_key'] ?? $_GET['publicKey'] ?? ''));

render_header('SiaGraph - Host Alerts', 'SiaGraph - Host Alerts');
?>
<style>
.host-alerts-card .alerts-copy,
.host-alerts-card .form-label,
.host-alerts-card .form-text {
    color: #dbe3ee !important;
}

.host-alerts-card .form-control,
.host-alerts-card .form-select {
    background-color: #0f172a;
    color: #f8fafc;
    border-color: #334155;
}

.host-alerts-card .form-control::placeholder {
    color: #94a3b8;
    opacity: 1;
}

.host-alerts-card .form-control:focus,
.host-alerts-card .form-select:focus {
    background-color: #0f172a;
    color: #f8fafc;
    border-color: #60a5fa;
    box-shadow: 0 0 0 0.2rem rgba(96, 165, 250, 0.2);
}

.host-alerts-card {
    width: min(100%, 760px);
    margin-inline: auto;
}

.host-alerts-card .alerts-list {
    margin: 0 0 1rem 0;
    padding-left: 1.25rem;
    list-style: disc;
}

.host-alerts-card .alerts-list li + li {
    margin-top: 0.35rem;
}
</style>
<section id="main-content" class="sg-container sg-container--narrow">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-bell me-2"></i>Host Alerts</h1>

    <div class="sg-container__row mb-4">
        <div class="sg-container__row-content sg-container__row-content--center">
            <div class="sg-container__column">
                <section class="card host-alerts-card">
                    <h2 class="card__heading">Subscribe to Host Alerts</h2>
                    <div class="card__content">
                        <p class="alerts-copy mb-2">
                            Subscribe to operational alerts for a specific host using its public key.
                        </p>
                        <p class="alerts-copy mb-2">
                            Alert types include, but are not limited to:
                        </p>
                        <ul class="alerts-copy alerts-list">
                            <li><strong>Connectivity issues:</strong> when a host fails to respond in time or is getting out of sync.</li>
                            <li><strong>Low wallet balance:</strong> when the wallet balance is insufficient to handle new or existing contracts.</li>
                            <li><strong>Low capacity:</strong> when free space is running low. A warning is sent once, and if it later escalates to an error, that error is also sent once.</li>
                        </ul>
                        <p class="alerts-copy mb-3">
                            Most critical errors are repeated at 4-hour intervals until resolved, while warnings are reported once. Low-capacity alerts are sent once per severity level.
                        </p>
                        <form id="hostAlertsForm" class="row g-3">
                            <div class="col-12">
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
                            <div class="col-12 col-md-6">
                                <label for="service" class="form-label">Delivery Method</label>
                                <select class="form-select" id="service" required>
                                    <option value="email">Email</option>
                                    <option value="pushover">Pushover</option>
                                    <option value="telegram">Telegram</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label for="recipient" class="form-label">Recipient</label>
                                <input type="text" class="form-control" id="recipient" placeholder="you@example.com" required>
                                <div id="telegramInstructions" class="form-text mt-1 is-hidden">
                                    Start a chat with <a href="https://t.me/Siagraph_bot" target="_blank" rel="noopener noreferrer"><strong>@Siagraph_bot</strong></a>
                                    and type <code>/start</code> to get your chat ID.
                                </div>
                            </div>
                            <div class="col-12 d-flex justify-content-between align-items-center">
                                <a href="/host_explorer" class="button">Find host in explorer</a>
                                <button type="submit" class="btn btn-sm btn-brand" id="submitSubscriptionBtn">Subscribe</button>
                            </div>
                            <div class="col-12">
                                <div id="subscriptionStatus" class="small"></div>
                            </div>
                        </form>
                    </div>
                </section>
            </div>
        </div>
    </div>
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
