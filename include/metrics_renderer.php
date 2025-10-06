<?php
function render_metrics_graphs(array $metrics) {
    $graphConfigs = require __DIR__ . '/graph_configs.php';
    foreach ($metrics as $metric) {
        $canvasId = $metric['canvasid'];
        echo "<section class='card'>\n";
        echo "    <h2 class='card__heading'>{$metric['title']}</h2>\n";
        echo "    <div class='card__content'>\n";
        $dataset = $graphConfigs[$metric['datasetKey']];
        if (!empty($metric['datasetOverrides'])) {
            $dataset = array_merge($dataset, $metric['datasetOverrides']);
        }
        renderGraph(
            $canvasId,
            [ $dataset ],
            $metric['dateKey'] ?? 'date',
            $metric['jsonUrl'] ?? null,
            $metric['jsonData'] ?? null,
            $metric['charttype'] ?? 'line',
            $metric['interval'] ?? 'month',
            $metric['rangeslider'] ?? true,
            $metric['displaylegend'] ?? 'false',
            $metric['defaultrangeinmonths'] ?? 6,
            $metric['displayYAxis'] ?? 'false',
            $metric['unitType'] ?? 'bytes',
            $metric['jsonKey'] ?? null
        );
        echo "    </div>\n";
        echo "</section>\n";
    }
}
?>