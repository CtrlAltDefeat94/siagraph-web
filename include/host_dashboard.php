<?php
/** Host-specific configuration; shared renderer owns chart and range behavior. */
function host_dashboard(string $tab, string $dailyUrl, string $publicKey, string $currency): array
{
    $styles = require __DIR__ . '/graph_configs.php';
    $metric = function ($label, $type, $style, $extra = []) use ($styles) {
        return array_merge(['label' => $label, 'type' => $type,
            'backgroundColor' => $styles[$style]['backgroundColor'] ?? '#38bdf8',
            'borderColor' => $styles[$style]['borderColor'] ?? '#38bdf8'], $extra);
    };
    $money = ['bucket' => true, 'decimals' => 2, 'showNative' => true];
    $page = ['id' => 'host-' . $tab, 'title' => ucfirst($tab), 'defaultRange' => '1y', 'persistRange' => false, 'lazy' => true, 'followCurrency' => true, 'currency' => $currency];
    if ($tab === 'history') {
        $page['sources'] = [
            'default' => ['kind' => 'host-daily', 'url' => $dailyUrl],
            'contracts' => ['kind' => 'host-contracts', 'url' => $dailyUrl],
            'egress' => ['kind' => 'host-egress', 'url' => '/api/v1/host_estimated_egress?public_key=' . rawurlencode($publicKey)],
        ];
        $page['metrics'] = [
            'used_storage' => $metric('Used Storage', 'bytes', 'used_storage'),
            'total_storage' => $metric('Total Storage', 'bytes', 'total_storage'),
            'storage_price' => $metric('Storage Price / TB / month', 'money', 'storage_price', $money),
            'upload_price' => $metric('Ingress Price / TB', 'money', 'upload_price', $money),
            'download_price' => $metric('Egress Price / TB', 'money', 'download_price', $money),
            'successful' => $metric('Successful', 'count', 'successful_contracts'),
            'renewed' => $metric('Renewed', 'count', 'renewed_contracts'),
            'failed' => $metric('Failed', 'count', 'failed_contracts'),
            'active' => $metric('Active Contracts', 'count', 'active_contracts', ['chartType' => 'line']),
            'egress' => $metric('Estimated Egress (GB)', 'count', 'used_storage'),
        ];
        $page['sections'] = [
            ['title' => 'Estimated Egress',
             'help' => 'Monthly estimates in GB from July 2025, derived from contract revenue rather than measured traffic. Totals may be partial. Paid operations and underestimated upload or storage charges can inflate estimates.',
             'charts' => [['id' => 'host-egress', 'source' => 'egress', 'metrics' => ['egress'], 'type' => 'bar', 'interval' => 'month']]],
            ['title' => 'Contract Activity History', 'charts' => [['id' => 'host-contract-history', 'source' => 'contracts', 'metrics' => ['successful', 'renewed', 'failed', 'active'], 'type' => 'bar', 'interval' => 'month']]],
            ['title' => 'Daily Storage', 'charts' => [['id' => 'host-storage', 'metrics' => ['used_storage', 'total_storage']]]],
            ['title' => 'Daily Pricing', 'charts' => [['id' => 'host-pricing', 'metrics' => ['storage_price', 'upload_price', 'download_price']]]],
        ];
    } else {
        $page['sources'] = [
            'default' => ['kind' => 'host-economics', 'url' => $dailyUrl],
            'revenue' => ['kind' => 'host-economics', 'url' => $dailyUrl,
                'scheduled' => '/api/v1/host_scheduled_revenue?public_key=' . rawurlencode($publicKey)],
        ];
        $page['metrics'] = [
            'earned' => $metric('Earned', 'money', 'successful_contracts', $money),
            'burned' => $metric('Burned Funds', 'money', 'failed_contracts', $money),
            'anticipated' => $metric('Anticipated Revenue', 'money', 'total_hosts', $money),
            'locked' => $metric('Locked Collateral', 'money', 'host_collateral_locked', $money),
            'risked' => $metric('Risked Collateral', 'money', 'renter_collateral_locked', $money),
        ];
        $page['sections'] = [
            ['title' => 'Revenue (Monthly)', 'charts' => [['id' => 'host-revenue', 'source' => 'revenue', 'metrics' => ['earned', 'burned', 'anticipated'], 'type' => 'bar', 'stacked' => true, 'interval' => 'month', 'includeForecast' => true]]],
            ['title' => 'Collateral Risk', 'charts' => [['id' => 'host-collateral', 'metrics' => ['locked', 'risked'], 'interval' => 'month']]],
        ];
    }
    foreach ($page['sections'] as &$section) foreach ($section['charts'] as &$chart) {
        $chart['tooltipMetrics'] = $chart['metrics'];
        $chart['startAt'] = 'first-valid';
    }
    unset($section, $chart);
    return $page;
}
