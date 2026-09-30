<?php
// Units and calculations are explicit; zero is a value, null is missing data.
$styles = require __DIR__ . '/graph_configs.php';
$definitions = [
    'utilized_storage' => ['label' => 'Utilized Storage', 'type' => 'bytes'],
    'total_storage' => ['label' => 'Total Storage', 'type' => 'bytes'],
    'active_hosts' => ['label' => 'Active Hosts', 'type' => 'count'],
    'total_hosts' => ['label' => 'Total Hosts', 'type' => 'count'],
    'block_height' => ['label' => 'Block Height', 'type' => 'count'],
    'utilization_percent' => ['label' => 'Utilization', 'type' => 'ratio', 'numerator' => ['utilized_storage'], 'denominator' => 'total_storage'],
    'ath_bytes' => ['label' => 'ATH Utilized Storage', 'type' => 'bytes'],
    'ath_date' => ['label' => 'ATH Date', 'type' => 'date'],
    'days_since_ath' => ['label' => 'Days Since ATH', 'type' => 'count'],
    'predicted_utilized' => ['label' => 'Projected Utilized Storage', 'type' => 'bytes'],
    'predicted_total' => ['label' => 'Projected Total Storage', 'type' => 'bytes'],
    'exponential_utilized' => ['label' => 'Projected Utilized Storage', 'type' => 'bytes'],
    'exponential_total' => ['label' => 'Projected Total Storage', 'type' => 'bytes'],
    'contracts_formed' => ['label' => 'Contracts Formed', 'type' => 'count'],
    'renewed_contracts' => ['label' => 'Renewed', 'type' => 'count'],
    'successful_contracts' => ['label' => 'Successful', 'type' => 'count'],
    'failed_contracts' => ['label' => 'Failed', 'type' => 'count'],
    'active_contracts' => ['label' => 'Active Contracts', 'type' => 'count'],
    'renter_collateral_locked' => ['label' => 'Renter Balance', 'type' => 'money', 'divisor' => 1e24],
    'host_collateral_locked' => ['label' => 'Host Collateral', 'type' => 'money', 'divisor' => 1e24],
    'circulating_supply' => ['label' => 'Circulating Supply', 'type' => 'sc', 'divisor' => 1e24],
    'collateral_locked_percent' => [
        'label' => 'Collateral / Supply', 'type' => 'ratio',
        'numerator' => ['renter_collateral_locked', 'host_collateral_locked'],
        'denominator' => 'circulating_supply',
    ],
];
foreach (['predicted_utilized', 'exponential_utilized'] as $key) $styles[$key] = $styles['utilized_storage'];
foreach (['predicted_total', 'exponential_total'] as $key) $styles[$key] = $styles['total_storage'];
foreach ($definitions as $key => &$definition) {
    if (str_starts_with($key, 'predicted_') || str_starts_with($key, 'exponential_')) $definition['projected'] = true;
    $definition['backgroundColor'] = $styles[$key]['backgroundColor'] ?? 'rgba(99, 102, 241, 0.2)';
    $definition['borderColor'] = $styles[$key]['borderColor'] ?? $styles[$key]['backgroundColor'] ?? 'rgba(99, 102, 241, 0.2)';
}
unset($definition);
return $definitions;
