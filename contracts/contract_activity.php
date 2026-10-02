<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
require_once dirname(__DIR__) . '/include/components/dashboard.php';
$metrics = require dirname(__DIR__) . '/include/dashboard_metrics.php';
$keys = ['contracts_formed', 'renewed_contracts', 'successful_contracts', 'failed_contracts'];
render_dashboard([
    'title' => 'Contract Activity',
    'description' => 'Contract formation and completions on the Sia network.',
    'source' => '/api/v1/daily/aggregates',
    'metrics' => array_intersect_key($metrics, array_flip($keys)),
    'kpis' => ['mode' => 'range-total', 'metrics' => $keys],
    'sections' => [
        ['title' => 'Contract Formation', 'charts' => [
            ['id' => 'formed', 'metrics' => ['contracts_formed'], 'type' => 'bar'],
        ]],
        ['title' => 'Contract Completions', 'xLabels' => 'last', 'charts' => [
            ['id' => 'renewed', 'title' => 'Renewed', 'metrics' => ['renewed_contracts'], 'type' => 'bar'],
            ['id' => 'successful', 'title' => 'Successful', 'metrics' => ['successful_contracts'], 'type' => 'bar'],
            ['id' => 'failed', 'title' => 'Failed', 'metrics' => ['failed_contracts'], 'type' => 'bar'],
        ]],
    ],
]);
