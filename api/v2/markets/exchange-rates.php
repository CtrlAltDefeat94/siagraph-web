<?php
include_once '../../../bootstrap.php';
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../_core/request.php';
require_once __DIR__ . '/../_core/format.php';

use Siagraph\Services\V2\MarketsService;

$base = strtolower((string) v2_query_string('base', 'sc'));
if ($base !== 'sc') {
    v2_json_error('invalid_query_param', 'base must be sc', 400, 'base');
    exit;
}

$query = [];
$start = v2_query_string('start');
$end = v2_query_string('end');
if ($start !== null) {
    $query['start'] = $start;
}
if ($end !== null) {
    $query['end'] = $end;
}

$res = MarketsService::exchangeRates($query);
if (!$res['ok']) {
    v2_json_error('upstream_failure', $res['error'] ?? 'Failed to fetch exchange rates', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
    exit;
}

$data = v2_normalize_keys_snake_case($res['json']);
v2_json_success($data, v2_meta_base(['v1_exchange_rates']));
