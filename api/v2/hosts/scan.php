<?php
include_once '../../../bootstrap.php';
require_once __DIR__ . '/../../../include/api_auth.php';
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../_core/format.php';

use Siagraph\Services\V2\HostsService;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    v2_json_error('method_not_allowed', 'Only POST is allowed', 405);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true, 512, JSON_BIGINT_AS_STRING);
if (!siagraph_is_api_password_authenticated(is_array($body) ? $body : null)) {
    v2_json_error('api_auth_required', 'Valid API authentication is required.', 401, null, [], v2_meta_base([], true));
    exit;
}

$publicKey = is_array($body) ? trim((string) ($body['public_key'] ?? '')) : '';
if ($publicKey === '') {
    v2_json_error('invalid_body', 'public_key is required', 400, 'public_key');
    exit;
}

$res = HostsService::scan(['public_key' => $publicKey]);
if (!$res['ok']) {
    v2_json_error('upstream_failure', $res['error'] ?? 'Failed to scan host', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
    exit;
}

$data = v2_normalize_keys_snake_case($res['json']);
v2_json_success($data, v2_meta_base(['v1_scan_host']));
