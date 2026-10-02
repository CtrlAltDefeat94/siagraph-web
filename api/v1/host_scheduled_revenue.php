<?php
use Siagraph\Database\Database;
use Siagraph\Services\HostScheduledRevenue;
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
    // Date-scoped keys prevent yesterday's result being served after midnight UTC.
    $day = gmdate('Y-m-d');
    $cacheKey = 'host_scheduled_revenue:v3:' . $day . ':' . hash('sha256', $publicKey);
    if (($cached = Cache::getCache($cacheKey)) !== null) {
        echo $cached;
        exit;
    }
    $rows = HostScheduledRevenue::query(
        Database::getConnection(),
        (string) ($SETTINGS['database']['database'] ?? ''),
        $publicKey
    );
    $json = json_encode([
        'public_key' => $publicKey,
        'as_of' => gmdate('Y-m-d\TH:i:s\Z'),
        'scheduled_revenue' => $rows,
    ], JSON_THROW_ON_ERROR);
    $midnight = new DateTimeImmutable($day . ' 00:00:00', new DateTimeZone('UTC'));
    Cache::setCacheSeconds($json, $cacheKey, max(1, $midnight->modify('+1 day')->getTimestamp() - time()));
    echo $json;
} catch (Throwable $e) {
    error_log('Host anticipated revenue: ' . $e->getMessage());
    http_response_code(503);
    header('Cache-Control: no-store');
    echo json_encode(['error' => 'Anticipated revenue is temporarily unavailable.']);
}
