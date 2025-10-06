<footer class="text-white py-6 mt-5" style="background:rgba(255,255,255,.05);border-top:1px solid rgba(255,255,255,.1);">
    <div class="container text-center">
        <div class="d-inline-flex align-items-center mb-1 mt-2">
            <img src="img/siagraph_banner_white.png" alt="SiaGraph"
                 style="height: 20px; margin-right: 8px; position: relative; top: 2px;">
            <span class="align-middle">
                &copy; 2024-<?php echo date("Y"); ?>.
            </span>
        </div>
        <p class="mb-1">Metrics for the Sia network.</p>
        <p class="mb-0">
            <a class="text-white text-decoration-none" href="/swagger">SiaGraph API docs</a>
            <span class="mx-2">|</span>
            <a class="text-white text-decoration-none" href="https://api.sia.tech/explored">Explorer API docs</a>
            <span class="mx-2">|</span>
            <a class="text-white text-decoration-none" href="https://explorer.siagraph.info/api/consensus/tip">Explorer API</a>
            <span class="mx-2">|</span>
            <?php $currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur'; ?>
            <label for="currency-select" class="me-1">Currency:</label>
            <select id="currency-select" class="text-white border-0" onchange="setCurrency(this.value)">
                <option value="eur" <?php if($currencyCookie==='eur') echo 'selected'; ?>>EUR</option>
                <option value="usd" <?php if($currencyCookie==='usd') echo 'selected'; ?>>USD</option>
                <option value="sc" <?php if($currencyCookie==='sc') echo 'selected'; ?>>SC</option>
            </select>

        </p>
    </div>
</footer>
