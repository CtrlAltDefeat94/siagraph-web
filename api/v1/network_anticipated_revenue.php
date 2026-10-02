<?php
use Siagraph\Database\Database;
use Siagraph\Services\NetworkAnticipatedRevenue;
use Siagraph\Utils\Cache;

header('Content-Type: application/json');
header('Cache-Control: no-store');
try {
    require_once __DIR__ . '/../../bootstrap.php';
    $cacheKey = 'network_anticipated_revenue:forecast:v4:' . gmdate('Y-m-d');
    if (($cached = Cache::getCache($cacheKey)) !== null) {
        echo $cached;
        exit;
    }
    $rows = NetworkAnticipatedRevenue::query(
        Database::getConnection(),
        (string) ($SETTINGS['database']['database'] ?? '')
    );
    $json = json_encode([
        'as_of' => gmdate('Y-m-d\TH:i:s\Z'),
        'scheduled_revenue' => $rows,
    ], JSON_THROW_ON_ERROR);
    Cache::setCacheSeconds($json, $cacheKey, 60);
    echo $json;
} catch (Throwable $e) {
    error_log('Network anticipated revenue: ' . $e->getMessage());
    http_response_code(503);
    header('Cache-Control: no-store');
    echo json_encode(['error' => 'Anticipated revenue is temporarily unavailable.']);
}
