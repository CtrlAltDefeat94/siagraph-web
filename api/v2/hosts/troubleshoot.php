<?php
include_once '../../../bootstrap.php';
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../_core/request.php';
require_once __DIR__ . '/../_core/format.php';

use Siagraph\Services\V2\HostsService;

$query = [];
$hostId = v2_query_string('host_id');
$publicKey = v2_query_string('public_key');
$netAddress = v2_query_string('net_address');
$scan = v2_query_bool('scan');

if ($hostId !== null) {
    // Resolve host_id by calling details endpoint first
    $hostRes = HostsService::details(['id' => $hostId]);
    if ($hostRes['ok'] && is_array($hostRes['json']) && !empty($hostRes['json']['net_address'])) {
        $query['net_address'] = (string) $hostRes['json']['net_address'];
    }
}
if ($publicKey !== null) {
    $query['public_key'] = $publicKey;
}
if ($netAddress !== null) {
    $query['net_address'] = $netAddress;
}
if ($scan !== null) {
    $query['scan'] = $scan ? 'true' : 'false';
}

if (empty($query)) {
    v2_json_error('invalid_query_param', 'Provide host_id, public_key, or net_address', 400, 'host_id');
    exit;
}

$res = HostsService::troubleshoot($query);
if (!$res['ok']) {
    v2_json_error('upstream_failure', $res['error'] ?? 'Failed to troubleshoot host', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
    exit;
}

$data = v2_normalize_keys_snake_case($res['json']);
v2_json_success($data, v2_meta_base(['v1_host_troubleshooter']));
