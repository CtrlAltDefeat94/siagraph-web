<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
require_once dirname(__DIR__) . '/include/components/dashboard.php';
$metrics = require dirname(__DIR__) . '/include/dashboard_metrics.php';
$storage = ['utilized_storage', 'total_storage'];
$hosts = ['active_hosts', 'total_hosts'];
$linear = [...$storage, 'predicted_utilized', 'predicted_total'];
$exponential = [...$storage, 'exponential_utilized', 'exponential_total'];
$keys = array_unique([...$linear, ...$exponential, ...$hosts, 'block_height', 'utilization_percent', 'ath_bytes', 'ath_date', 'days_since_ath']);
render_dashboard([
    'title' => 'Storage & Hosting',
    'description' => 'Sia network storage capacity, utilization, host counts, and storage projections.',
    'sources' => [
        'default' => '/api/v1/daily/growth',
        'metrics' => '/api/v1/daily/metrics',
        'ath' => ['kind' => 'storage-ath', 'url' => '/api/v1/storage/ath'],
        'forecast' => ['kind' => 'storage-forecast', 'url' => '/api/v1/monthly/growth'],
    ],
    'metrics' => array_intersect_key($metrics, array_flip($keys)),
    'sections' => [
        ['title' => 'Storage Capacity',
         'kpis' => ['mode' => 'latest', 'source' => 'default', 'metrics' => [...$storage, 'utilization_percent']],
         'charts' => [
             ['id' => 'storage', 'metrics' => $storage, 'tooltipMetrics' => [...$storage, 'utilization_percent']],
             ['id' => 'utilization', 'title' => 'Storage Utilization', 'metrics' => ['utilization_percent'], 'tooltipMetrics' => [...$storage, 'utilization_percent']],
         ]],
        ['title' => 'Hosts',
         'kpis' => ['mode' => 'latest', 'source' => 'default', 'metrics' => $hosts],
         'charts' => [['id' => 'hosts', 'title' => 'Host Counts', 'metrics' => $hosts, 'tooltipMetrics' => $hosts]]],
        ['title' => 'Block Height', 'kpis' => ['mode' => 'latest', 'source' => 'metrics', 'metrics' => ['block_height']]],
        ['title' => 'All-Time High', 'kpis' => ['mode' => 'latest', 'source' => 'ath', 'metrics' => ['ath_bytes', 'ath_date', 'days_since_ath']]],
        ['title' => 'Storage Forecast', 'help' => '24-month projection from the latest five monthly averages. Exponential mode uses a logarithmic scale.',
         'charts' => [['id' => 'forecast', 'source' => 'forecast', 'metrics' => $linear, 'tooltipMetrics' => $linear,
             'interval' => 'month', 'includeForecast' => true,
             'modes' => [
                 ['label' => 'Linear', 'metrics' => $linear, 'tooltipMetrics' => $linear, 'yScale' => 'linear'],
                 ['label' => 'Exponential', 'metrics' => $exponential, 'tooltipMetrics' => $exponential, 'yScale' => 'logarithmic'],
             ],
         ]]],
    ],
    'links' => ['/host_explorer' => 'Host Explorer', '/host_pricing' => 'Host Pricing', '/host_troubleshooter' => 'Host Troubleshooter'],
]);
