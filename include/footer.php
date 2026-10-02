<?php $currencyCookie = \Siagraph\Utils\CurrencyDisplay::selectedCurrency(); ?>
<footer class="sg-site-footer">
    <div class="container sg-site-footer__inner">
        <div class="sg-site-footer__brand">
            <a class="sg-site-footer__logo-link" href="/" aria-label="SiaGraph home">
                <img src="/img/siagraph_banner_white.png" alt="SiaGraph" class="sg-site-footer-logo">
            </a>
            <p class="sg-site-footer__tagline">Unofficial Sia network metrics.</p>
            <p class="sg-site-footer__copyright">&copy; 2024-<?php echo date('Y'); ?> SiaGraph.</p>
        </div>

        <nav class="sg-site-footer__nav" aria-label="Footer">
            <section class="sg-site-footer__group">
                <h2 class="sg-site-footer__heading">Developers</h2>
                <a href="/swagger">SiaGraph API docs</a>
                <a href="https://api.sia.tech/explored">Explorer API docs</a>
                <a href="https://explorer.siagraph.info/api/consensus/tip">Raw Explorer API</a>
            </section>

            <section class="sg-site-footer__group sg-site-footer__settings">
                <h2 class="sg-site-footer__heading">Settings</h2>
                <label for="currency-select">Currency</label>
                <select id="currency-select" class="sg-currency-select" onchange="setCurrency(this.value)">
                    <option value="eur" <?php if ($currencyCookie === 'eur') echo 'selected'; ?>>EUR</option>
                    <option value="usd" <?php if ($currencyCookie === 'usd') echo 'selected'; ?>>USD</option>
                    <option value="cad" <?php if ($currencyCookie === 'cad') echo 'selected'; ?>>CAD</option>
                    <option value="gbp" <?php if ($currencyCookie === 'gbp') echo 'selected'; ?>>GBP</option>
                </select>
            </section>
        </nav>

    </div>
</footer>
