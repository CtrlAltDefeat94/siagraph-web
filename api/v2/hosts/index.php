<?php
include_once '../../../bootstrap.php';
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../_core/request.php';
require_once __DIR__ . '/../_core/format.php';

use Siagraph\Services\V2\HostsService;

$query = [];
$allowed = [
    'page', 'limit', 'sort', 'country', 'version', 'query',
    'maxStoragePrice', 'maxUploadPrice', 'maxDownloadPrice',
    'showinactive', 'acceptingContracts', 'minStorage'
];
foreach ($allowed as $k) {
    if (isset($_GET[$k]) && trim((string) $_GET[$k]) !== '') {
        $query[$k] = (string) $_GET[$k];
    }
}

// Canonical aliases
if (isset($_GET['q']) && trim((string) $_GET['q']) !== '') {
    $query['query'] = trim((string) $_GET['q']);
}
if (isset($_GET['include_inactive'])) {
    $query['showinactive'] = filter_var($_GET['include_inactive'], FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
}
if (isset($_GET['per_page']) && (int) $_GET['per_page'] > 0) {
    $query['limit'] = (int) $_GET['per_page'];
}

$res = HostsService::list($query);
if (!$res['ok']) {
    v2_json_error('upstream_failure', $res['error'] ?? 'Failed to fetch hosts', $res['status'] ?: 502, null, [], v2_meta_base(['v1_bridge'], true));
    exit;
}

$legacy = $res['json'];
$pagination = null;
if (isset($legacy['pagination']) && is_array($legacy['pagination'])) {
    $pagination = [
        'page' => (int) ($legacy['pagination']['current_page'] ?? 1),
        'per_page' => (int) ($legacy['pagination']['per_page'] ?? 0),
        'total_items' => (int) ($legacy['pagination']['total_rows'] ?? 0),
        'total_pages' => (int) ($legacy['pagination']['total_pages'] ?? 0),
    ];
}

$data = [
    'items' => v2_normalize_keys_snake_case($legacy['hosts'] ?? []),
    'facets' => [
        'versions' => v2_normalize_keys_snake_case($legacy['versions'] ?? []),
    ],
    'parity' => v2_normalize_keys_snake_case($legacy),
];

$meta = v2_meta_base(['v1_hosts']);
if ($pagination !== null) {
    $meta['pagination'] = $pagination;
}

v2_json_success($data, $meta);
