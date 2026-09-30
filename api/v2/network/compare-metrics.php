<?php
include_once '../../../bootstrap.php';
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../_core/request.php';
require_once __DIR__ . '/../_core/format.php';

use Siagraph\Services\V2\NetworkService;

$query = [];
$actualDate = v2_query_string('actual_date');
$compareDate = v2_query_string('compare_date');
if ($actualDate !== null) {
    $query['actual_date'] = $actualDate;
}
if ($compareDate !== null) {
    $query['compare_date'] = $compareDate;
}

$res = NetworkService::compareMetrics($query);
if (!$res['ok']) {
    v2_json_error('upstream_failure', $res['error'] ?? 'Failed to compare metrics', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
    exit;
}

$data = v2_normalize_keys_snake_case($res['json']);
v2_json_success($data, v2_meta_base(['v1_compare_metrics_day']));
