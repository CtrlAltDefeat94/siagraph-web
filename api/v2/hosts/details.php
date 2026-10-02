<?php
include_once '../../../bootstrap.php';
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../_core/request.php';
require_once __DIR__ . '/../_core/format.php';

use Siagraph\Services\V2\HostsService;

$query = [];
$hostId = v2_query_string('host_id');
$publicKey = v2_query_string('public_key');

if ($hostId !== null) {
    $query['id'] = $hostId;
}
if ($publicKey !== null) {
    $query['public_key'] = $publicKey;
}

if (empty($query)) {
    v2_json_error('invalid_query_param', 'Either host_id or public_key is required', 400, 'host_id');
    exit;
}

$res = HostsService::details($query);
if (!$res['ok']) {
    v2_json_error('upstream_failure', $res['error'] ?? 'Failed to fetch host details', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
    exit;
}

$data = v2_normalize_keys_snake_case($res['json']);
v2_json_success($data, v2_meta_base(['v1_host_details']));
