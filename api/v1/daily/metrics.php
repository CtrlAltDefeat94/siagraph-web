<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

// Build cache key - append suffix so new structure doesn't clash with old cache
$queryString = http_build_query($_GET);
$combinedString = basename(__FILE__) . $queryString . '-noagg';
$cacheKey = md5($combinedString);

// Return cached response if available
if ($cached = Cache::getCache($cacheKey)) {
    echo $cached;
    exit;
}

// Query to fetch columns from DailyMetrics and NetworkStats joined by date
$query = "
    SELECT 
        dm.date,
        dm.block_height,
        ns.utilized_storage,
        ns.total_storage,
        ns.active_hosts,
        ns.total_hosts,

        dm.circulating_supply,
        dm.active_contracts,
        dm.total_successful_contracts,
        dm.total_failed_contracts,
        dm.total_renewed_contracts,
        dm.total_burned_funds,
        dm.renter_collateral_locked,
        dm.host_collateral_locked,
        dm.contract_filesize_total


    FROM DailyMetrics dm
    LEFT JOIN NetworkStats ns ON dm.date = ns.date
    ORDER BY dm.date ASC
";

$result = mysqli_query($mysqli, $query);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed', 'details' => mysqli_error($mysqli)]);
    exit;
}

// Format results
$data = [];
while ($row = mysqli_fetch_assoc($result)) {
    // Mask untrusted fields before cutoff date
    $cutoff = '2024-06-01';
    $rowDate = $row['date'] ?? null; // format YYYY-MM-DD
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

// Cache the result
// setCache($jsonResult, $cacheKey, 'day');

// Output the result
echo $jsonResult;
