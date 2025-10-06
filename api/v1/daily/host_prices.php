<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

// Build cache key
$queryString = http_build_query($_GET);
$cacheKey = md5(basename(__FILE__) . $queryString);
if ($cached = Cache::getCache($cacheKey)) {
    echo $cached;
    exit;
}

$start = isset($_GET['start']) ? $_GET['start'] : '1970-01-01';
$stmt = $mysqli->prepare("WITH ranked AS (
    SELECT
        date,
        storage_price,
        upload_price,
        download_price,
        PERCENT_RANK() OVER (PARTITION BY date ORDER BY storage_price) AS storage_pr,
        PERCENT_RANK() OVER (PARTITION BY date ORDER BY upload_price)  AS upload_pr,
        PERCENT_RANK() OVER (PARTITION BY date ORDER BY download_price) AS download_pr
    FROM HostsDailyStats
    WHERE date >= ?
)
SELECT
    date,
    AVG(storage_price)  AS avg_storage_price,
    AVG(upload_price)   AS avg_upload_price,
    AVG(download_price) AS avg_download_price
FROM ranked
WHERE storage_pr BETWEEN 0.1 AND 0.9
  AND upload_pr  BETWEEN 0.1 AND 0.9
  AND download_pr BETWEEN 0.1 AND 0.9
GROUP BY date
ORDER BY date ASC;
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed', 'details' => $mysqli->error]);
    exit;
}
$stmt->bind_param('s', $start);
$stmt->execute();
$result = $stmt->get_result();
$data = mysqli_fetch_all($result, MYSQLI_ASSOC);
$stmt->close();
$jsonResult = json_encode($data);
Cache::setCache($jsonResult, $cacheKey, 'day');

echo $jsonResult;
