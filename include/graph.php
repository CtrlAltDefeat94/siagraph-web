<?php
function renderGraph(
    $canvasid,
    $datasets,
    $dateKey,
    $jsonUrl = null,
    $jsonData = null,
    $charttype = 'line',
    $interval = "day",
    $rangeslider = true,
    $displaylegend = "true",
    $defaultrangeinmonths = 3,
    $displayYAxis = "true",
    $unitType = 'bytes',
    $jsonKey = null,
    $height = 500,
    $yAxisTitle = null,
    $yScale = 'linear',
    $stacked = false,
    $useFiatInitial = null,
    $initAfterEvent = null
) {
    global $currencyCookie;
    $encodedDatasets = json_encode($datasets);
    // Determine initial fiat mode: if explicitly provided, use it; otherwise infer from currency cookie
    $initialUseFiat = ($useFiatInitial !== null)
        ? (bool)$useFiatInitial
        : (isset($currencyCookie) && strtolower($currencyCookie) !== 'sc');
    ?>
    <div id="canvasContainer-<?php echo htmlspecialchars($canvasid, ENT_QUOTES, 'UTF-8'); ?>">
        <canvas id="<?php echo htmlspecialchars($canvasid, ENT_QUOTES, 'UTF-8'); ?>"
            style="height:<?php echo $height; ?>px !important;width: 100% !important;"></canvas>

        <?php if ($rangeslider && $charttype !== 'pie'): ?>
            <div id="dateRangeSlider-<?php echo htmlspecialchars($canvasid, ENT_QUOTES, 'UTF-8'); ?>"></div>
        <?php endif; ?>

        <script>
            document.addEventListener('DOMContentLoaded', function () {
                (function () {
                    var options = {
                        canvasId: "<?php echo htmlspecialchars($canvasid, ENT_QUOTES, 'UTF-8'); ?>",
                        jsonData: <?php echo json_encode(empty($jsonData) ? null : (is_string($jsonData) ? json_decode($jsonData, true) : $jsonData)); ?>,
                        jsonUrl: "<?php echo htmlspecialchars($jsonUrl, ENT_QUOTES); ?>",
                        unitType: "<?php echo $unitType; ?>",
                        datasets: <?php echo $encodedDatasets; ?>,
                        interval: "<?php echo $interval; ?>",
                        displaylegend: "<?php echo $displaylegend; ?>",
                        charttype: "<?php echo $charttype; ?>",
                        displayYAxis: "<?php echo $displayYAxis; ?>",
                        defaultrangeinmonths: <?php echo $defaultrangeinmonths; ?>,
                        rangeslider: <?php echo $rangeslider ? 'true' : 'false'; ?>,
                        dateKey: "<?php echo $dateKey; ?>",
                        jsonKey: "<?php echo $jsonKey; ?>",
                        yAxisTitle: "<?php echo htmlspecialchars((string)$yAxisTitle, ENT_QUOTES, 'UTF-8'); ?>",
                        useFiat: <?php echo $initialUseFiat ? 'true' : 'false'; ?>,
                        currency: "<?php echo isset($currencyCookie) ? strtolower($currencyCookie) : 'eur'; ?>",
                        stacked: <?php echo $stacked ? 'true' : 'false'; ?>,
                        initAfterEvent: <?php echo $initAfterEvent ? json_encode($initAfterEvent) : 'null'; ?>
                    };
                    options.yScale = "<?php echo htmlspecialchars((string)$yScale, ENT_QUOTES, 'UTF-8'); ?>";

                    if (!window.graphInstances) window.graphInstances = {};
                    function __initGraph_<?php echo preg_replace('/[^A-Za-z0-9_]/', '_', $canvasid); ?>() {
                        if (window.graphInstances[options.canvasId]) return; // already initialized
                        window.graphInstances[options.canvasId] = new GraphRenderer(options);
                    }
                    if (options.initAfterEvent && options.useFiat) {
                        document.addEventListener(options.initAfterEvent, function () {
                            __initGraph_<?php echo preg_replace('/[^A-Za-z0-9_]/', '_', $canvasid); ?>();
                        });
                    } else {
                        __initGraph_<?php echo preg_replace('/[^A-Za-z0-9_]/', '_', $canvasid); ?>();
                    }
                })();
            });
        </script>
    </div>
    <?php
}
