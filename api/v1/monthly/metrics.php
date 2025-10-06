<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

$queryString = http_build_query($_GET);
$combinedString = basename(__FILE__) . $queryString;
$cacheKey = md5($combinedString);
if ($cached = Cache::getCache($cacheKey)) {
    echo $cached;
    die;
}

$start_date = isset($_GET['start']) ? $_GET['start'] : '1970-01-01';

$query = "SELECT
            DATE_FORMAT(dm.date, '%Y-%m-01') AS date,
            MIN(dm.block_height) AS block_height,
            AVG(ns.utilized_storage) AS utilized_storage,
            AVG(ns.total_storage) AS total_storage,
            AVG(ns.active_hosts) AS active_hosts,
            AVG(ns.total_hosts) AS total_hosts,
            MAX(dm.circulating_supply) AS circulating_supply,
            MAX(dm.active_contracts) AS active_contracts,
            MAX(dm.total_successful_contracts) AS total_successful_contracts,
            MAX(dm.total_failed_contracts) AS total_failed_contracts,
            MAX(dm.total_renewed_contracts) AS total_renewed_contracts,
            MAX(dm.total_burned_funds) AS total_burned_funds,
            MAX(dm.renter_collateral_locked) AS renter_collateral_locked,
            MAX(dm.host_collateral_locked) AS host_collateral_locked,
            MAX(dm.contract_filesize_total) AS contract_filesize_total
          FROM DailyMetrics dm
          LEFT JOIN NetworkStats ns ON dm.date = ns.date
          WHERE dm.date >= ?
          GROUP BY DATE_FORMAT(dm.date, '%Y-%m-01')
          ORDER BY date ASC";

$stmt = $mysqli->prepare($query);
if (!$stmt) {
    echo json_encode(['error' => 'Prepare failed', 'details' => $mysqli->error]);
    exit();
}
$stmt->bind_param('s', $start_date);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    echo json_encode(['error' => 'Database query failed', 'details' => mysqli_error($mysqli)]);
    exit();
}

$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Mask untrusted fields before cutoff date
    $cutoff = '2024-06-01';
    $rowDate = $row['date'] ?? null; // format YYYY-MM-01
    if ($rowDate !== null && strcmp($rowDate, $cutoff) < 0) {
        // Fields prior to 2024-01-01 are not reliable; return nulls
        $row['renter_collateral_locked'] = null;
        $row['host_collateral_locked'] = null;
        $row['contract_filesize_total'] = null;
        // Also null out series used on Contracts & Funds so charts start from 2024
        $row['active_contracts'] = null;
        $row['circulating_supply'] = null;
    }

    $data[] = $row;
}

$jsonResult = json_encode($data);
Cache::setCache($jsonResult, $cacheKey, 'day');

echo $jsonResult;
