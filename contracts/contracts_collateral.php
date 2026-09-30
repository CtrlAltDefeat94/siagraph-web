<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
require_once dirname(__DIR__) . '/include/components/dashboard.php';
$metrics = require dirname(__DIR__) . '/include/dashboard_metrics.php';
$keys = ['active_contracts', 'renter_collateral_locked', 'host_collateral_locked', 'collateral_locked_percent', 'circulating_supply'];
render_dashboard([
    'title' => 'Contracts & Collateral',
    'description' => 'Active contracts, locked funds, and circulating supply.',
    'source' => '/api/v1/daily/metrics?history=all',
    'currency' => \Siagraph\Utils\CurrencyDisplay::selectedCurrency(),
    'metrics' => array_replace(array_flip($keys), array_intersect_key($metrics, array_flip($keys))),
    'kpis' => ['mode' => 'latest', 'metrics' => ['active_contracts', 'renter_collateral_locked', 'host_collateral_locked']],
    'sections' => [
        ['title' => 'Active Contracts', 'charts' => [
            ['id' => 'active', 'metrics' => ['active_contracts']],
        ]],
        ['title' => 'Locked Funds', 'charts' => [
            ['id' => 'locked', 'metrics' => ['renter_collateral_locked', 'host_collateral_locked'], 'stacked' => true],
        ]],
        ['title' => 'Collateral vs Supply', 'charts' => [
            ['id' => 'ratio', 'metrics' => ['collateral_locked_percent']],
        ]],
        ['title' => 'Circulating Supply', 'charts' => [
            ['id' => 'supply', 'metrics' => ['circulating_supply']],
        ]],
    ],
]);
