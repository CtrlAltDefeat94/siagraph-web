<?php
require_once 'include/layout.php';
render_header('SiaGraph - Peer Explorer');
?>
<section id="main-content" class="sg-container">
    <h1 class="sg-container__heading text-center mb-2"><i class="bi bi-diagram-3 me-2"></i>Peers</h1>
    <p class="text-center mb-4">A list of peers to kickstart syncing.</p>

    <div class="sg-container__row">
        <div class="sg-container__row-content">
            <div class="sg-container__column sg-container__column--half">
                <section class="card">
                    <h2 class="card__heading">Mainnet Peers</h2>
                    <div class="card__content overflow-x-auto">
                        <table id="mainnetTable" class="table table-dark table-clean text-white w-full border-collapse">
                            <thead>
                                <tr class="bg-primary-400 text-white">
                                    <th class="px-4 py-2">Address</th>
                                    <th class="px-4 py-2 text-right">Protocol Version</th>
                                    <th class="px-4 py-2 text-right">Last Scanned</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </section>
            </div>

            <div class="sg-container__column sg-container__column--half">
                <section class="card">
                    <h2 class="card__heading">Zen Peers</h2>
                    <div class="card__content overflow-x-auto">
                        <table id="zenTable" class="table table-dark table-clean text-white w-full border-collapse">
                            <thead>
                                <tr class="bg-primary-400 text-white">
                                    <th class="px-4 py-2">Address</th>
                                    <th class="px-4 py-2 text-right">Protocol Version</th>
                                    <th class="px-4 py-2 text-right">Last Scanned</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>
    </div>
</section>
<?php render_footer(['js/peers.js']); ?>
