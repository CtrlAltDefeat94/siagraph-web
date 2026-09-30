<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
require_once dirname(__DIR__) . '/include/components/dashboard.php';
$priceCurrency = strtolower((string) ($_COOKIE['currency'] ?? 'eur'));
if (!in_array($priceCurrency, ['eur', 'usd', 'cad', 'gbp'], true)) $priceCurrency = 'eur';
render_dashboard([
    'title' => 'Siacoin Price',
    'description' => 'Current and historical Siacoin exchange rates.',
    'currency' => $priceCurrency,
    'currencies' => ['eur', 'usd', 'cad', 'gbp'],
    'sources' => [
        'default' => ['url' => '/api/v1/daily/exchange_rate', 'gaps' => 'day'],
        'quote' => ['url' => '/api/v1/exchange_rate', 'kind' => 'record'],
    ],
    'metrics' => ['price' => ['label' => 'Price per SC', 'type' => 'price', 'borderColor' => '#6366f1', 'backgroundColor' => '#6366f133']],
    'kpis' => ['mode' => 'latest', 'source' => 'quote', 'metrics' => ['price']],
    'sections' => [
        ['title' => 'Exchange Rate History',
         'help' => 'Daily averages of recorded hourly prices (UTC). Missing days appear as gaps.',
         'charts' => [['id' => 'price', 'metrics' => ['price'], 'beginAtZero' => false, 'table' => true]]],
    ],
    'links' => ['/host_pricing' => 'Host Pricing', '/revenue' => 'Revenue', '/contracts_collateral' => 'Contracts & Collateral'],
]);
