<?php
require_once dirname(__DIR__) . '/bootstrap.php';
require_once dirname(__DIR__) . '/include/layout.php';
require_once dirname(__DIR__) . '/include/components/range_controls.php';
include_once dirname(__DIR__) . '/include/graph.php';
$graphConfigs = require dirname(__DIR__) . '/include/graph_configs.php';

use Siagraph\Utils\ApiClient;

$months = isset($_GET['months']) ? intval($_GET['months']) : 12;
$metricsEndpoint = '/api/v1/daily/metrics';
$latestData = ApiClient::fetchJson($metricsEndpoint);
$dataError = !is_array($latestData);
$latest = $dataError ? [] : end($latestData);

if ($dataError) {
    $utilPct = 0;
    $collateralPct = 0;
} else {
    $utilPct = !empty($latest['total_storage']) ? round($latest['utilized_storage'] / $latest['total_storage'] * 100, 2) : 0;
    $locked = ($latest['renter_collateral_locked'] ?? 0) + ($latest['host_collateral_locked'] ?? 0);
    $collateralPct = !empty($latest['circulating_supply']) ? round($locked / $latest['circulating_supply'] * 100, 30) : 0;
}
?>
<?php
// Page merged: redirect to Storage where utilization % is shown
header('Location: /network_storage', true, 302);
exit;
?>
