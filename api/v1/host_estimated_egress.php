<?php
use Siagraph\Database\Database;
use Siagraph\Services\HostEstimatedEgress;
use Siagraph\Utils\Cache;

header('Content-Type: application/json');
$publicKey = $_GET['public_key'] ?? '';
if (!is_string($publicKey) || !preg_match('/^(?:ed25519:)?[a-f0-9]{64}$/i', trim($publicKey))) {
    http_response_code(400);
    echo json_encode(['error' => 'A valid host public_key is required.']);
    exit;
}
$publicKey = strtolower(trim($publicKey));
if (!str_starts_with($publicKey, 'ed25519:')) $publicKey = 'ed25519:' . $publicKey;

try {
    require_once __DIR__ . '/../../bootstrap.php';
    $cacheKey = 'host_estimated_egress:v2:' . hash('sha256', $publicKey);
    if (($cached = Cache::getCache($cacheKey)) !== null) {
        echo $cached;
        exit;
    }
    $rows = HostEstimatedEgress::query(Database::getConnection(), (string) ($SETTINGS['database']['database'] ?? ''), $publicKey);
    // Coverage fields remain internal; only chart values are public.
    $rows = array_map(static fn(array $row): array => [
        'month' => $row['month'],
        'estimated_egress_gb' => $row['estimated_egress_gb'],
    ], $rows);
    $json = json_encode(['public_key' => $publicKey, 'start_date' => '2025-07-01', 'estimated_egress' => $rows], JSON_THROW_ON_ERROR);
    Cache::setCache($json, $cacheKey, 'hour');
    echo $json;
} catch (Throwable $e) {
    error_log('Host estimated egress: ' . $e->getMessage());
    http_response_code(503);
    header('Cache-Control: no-store');
    echo json_encode(['error' => 'Estimated egress is temporarily unavailable.']);
}
