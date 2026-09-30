<?php
include_once '../../../bootstrap.php';
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../_core/request.php';
require_once __DIR__ . '/../_core/format.php';

use Siagraph\Services\V2\NetworkService;

$interval = v2_query_string('interval', 'day');
if (!in_array($interval, ['day', 'month'], true)) {
    v2_json_error('invalid_query_param', 'interval must be day or month', 400, 'interval', [], v2_meta_base(['api-v2']));
    exit;
}

$query = [];
$start = v2_query_string('start');
if ($start !== null) {
    $query['start'] = $start;
}

$res = NetworkService::metrics($interval, $query);
if (!$res['ok']) {
    v2_json_error('upstream_failure', $res['error'] ?? 'Failed to load metrics', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
    exit;
}

$data = v2_normalize_keys_snake_case($res['json']);
v2_json_success($data, v2_meta_base(['v1_metrics_' . $interval]));
