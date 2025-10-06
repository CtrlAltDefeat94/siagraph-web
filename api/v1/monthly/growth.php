<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

$queryString = http_build_query($_GET);
$combinedString = basename(__FILE__) . $queryString;
$cacheKey = md5($combinedString);

if ($cached = Cache::getCache($cacheKey)) {
    echo $cached;
    exit;
}

$start_date = isset($_GET['start']) ? $_GET['start'] : '1970-01-01';

$query = "SELECT
            DATE_FORMAT(date, '%Y-%m-01') AS date,
            ROUND(AVG(total_storage),0) AS total_storage,
            ROUND(AVG(utilized_storage),0) AS utilized_storage,
            AVG(active_hosts) AS active_hosts,
            AVG(total_hosts) AS total_hosts
          FROM NetworkStats
          WHERE date >= ?
          GROUP BY DATE_FORMAT(date, '%Y-%m-01')
          ORDER BY date";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed', 'details' => $mysqli->error]);
    exit;
}

$stmt->bind_param('s', $start_date);
$stmt->execute();
$result = $stmt->get_result();
$stmt->close();

$jsonlist = [];
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
foreach ($rows as $row) {
    $jsonlist[] = array_filter([
        'date' => $row['date'],
        'total_storage' => $row['total_storage'],
        'utilized_storage' => $row['utilized_storage'],
        'active_hosts' => $row['active_hosts'],
        'total_hosts' => $row['total_hosts']
    ], function ($v) { return $v !== null && $v !== 0; });
}

$jsonResult = json_encode($jsonlist);
Cache::setCache($jsonResult, $cacheKey, 'day');

echo $jsonResult;
