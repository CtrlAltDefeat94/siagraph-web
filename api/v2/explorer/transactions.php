<?php
include_once '../../../bootstrap.php';
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../_core/request.php';
require_once __DIR__ . '/../_core/format.php';

use Siagraph\Services\V2\ExplorerService;

$query = [];
$address = v2_query_string('address');
$date = v2_query_string('date');
$currency = v2_query_string('currency', 'usd');
$format = strtolower((string) v2_query_string('format', 'json'));

if ($address !== null) {
    $query['address'] = $address;
}
if ($date !== null) {
    $query['date'] = $date;
}
$query['currency'] = $currency;
$query['format'] = $format;

if ($format === 'csv') {
    $res = ExplorerService::transactions($query, false);
    if (!$res['ok']) {
        v2_json_error('upstream_failure', $res['error'] ?? 'Failed to export transactions', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
        exit;
    }

    header('Content-Type: text/csv');
    echo (string) $res['raw'];
    exit;
}

$res = ExplorerService::transactions($query, true);
if (!$res['ok']) {
    v2_json_error('upstream_failure', $res['error'] ?? 'Failed to fetch transactions', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
    exit;
}

$data = v2_normalize_keys_snake_case($res['json']);
v2_json_success($data, v2_meta_base(['v1_transactions']));
