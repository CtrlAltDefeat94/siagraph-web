<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

// Cache for a day; response is tiny and rarely changes.
$cacheKey = md5(basename(__FILE__));
if ($cached = Cache::getCache($cacheKey)) {
    echo $cached;
    exit;
}

// Fetch latest utilized storage snapshot
$latestQuery = "
    SELECT date, utilized_storage
    FROM NetworkStats
    WHERE utilized_storage IS NOT NULL
    ORDER BY date DESC
    LIMIT 1
";
$latestStmt = $mysqli->prepare($latestQuery);
if (!$latestStmt || !$latestStmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch latest utilized storage']);
    exit;
}
$latestResult = $latestStmt->get_result();
$latest = $latestResult ? $latestResult->fetch_assoc() : null;

// Fetch all-time high utilized storage (earliest date wins ties)
$athQuery = "
    SELECT date, utilized_storage
    FROM NetworkStats
    WHERE utilized_storage IS NOT NULL
    ORDER BY utilized_storage DESC, date ASC
    LIMIT 1
";
$athStmt = $mysqli->prepare($athQuery);
if (!$athStmt || !$athStmt->execute()) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to fetch ATH utilized storage']);
    exit;
}
$athResult = $athStmt->get_result();
$ath = $athResult ? $athResult->fetch_assoc() : null;

$daysSinceAth = null;
if ($latest && $ath && !empty($latest['date']) && !empty($ath['date'])) {
    try {
        $latestDate = new DateTime($latest['date']);
        $athDate = new DateTime($ath['date']);
        $diff = $athDate->diff($latestDate);
        $daysSinceAth = max(0, (int) $diff->days);
    } catch (Exception $e) {
        $daysSinceAth = null;
    }
}

$response = [
    'utilized_storage' => [
        'ath_bytes'     => $ath['utilized_storage'] ?? null,
        'ath_date'      => $ath['date'] ?? null,
        'latest_bytes'  => $latest['utilized_storage'] ?? null,
        'latest_date'   => $latest['date'] ?? null,
        'days_since_ath'=> $daysSinceAth,
    ],
];

$jsonResult = json_encode($response);
Cache::setCache($jsonResult, $cacheKey, 'day');

echo $jsonResult;
