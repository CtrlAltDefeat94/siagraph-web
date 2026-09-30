<?php
include_once '../../../../bootstrap.php';
require_once __DIR__ . '/../../_core/response.php';
require_once __DIR__ . '/../../_core/request.php';
require_once __DIR__ . '/../../_core/format.php';

use Siagraph\Services\V2\NetworkService;

$query = [];
$start = v2_query_string('start');
if ($start !== null) {
    $query['start'] = $start;
}

$res = NetworkService::monthlyRevenue($query);
if (!$res['ok']) {
    v2_json_error('upstream_failure', $res['error'] ?? 'Failed to fetch monthly revenue', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
    exit;
}

$data = v2_normalize_keys_snake_case($res['json']);
v2_json_success($data, v2_meta_base(['v1_monthly_revenue']));
