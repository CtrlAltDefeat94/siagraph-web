<?php
include_once "../../include/database.php";
include_once "../../include/redis.php";
include_once "../../include/config.php";

header('Content-Type: application/json');
$cacheKey = md5(basename(__FILE__) . http_build_query($_GET));
$cacheresult = getCache($cacheKey);
if ($cacheresult) {
    echo $cacheresult;
    die;
}


// Get the selected sorting criteria
$sortMap = [
    "rank" => "s.total_score",
    "used_storage" => "h.used_storage",
    "total_storage" => "h.total_storage",
    "storage_price" => "h.storage_price",
    "net_address" => "h.net_address",
    "age" => "h.host_id",
    "growth" => "used_storage_diff",
];
$sortValue = isset($_GET["sort"]) ? $_GET["sort"] : "rank";

// Map the sort value to the corresponding column name
$sortColumn = isset($sortMap[$sortValue]) ? $sortMap[$sortValue] : "rank";
$sortOrder = in_array($sortValue, ["age", "net_address"]) ? "asc" : "desc";

// Fetch all data from the database with dynamic sorting
if (isset($_GET["showinactive"]) && $_GET["showinactive"] === "true") {
    $whereClause = "";
} else {
    $whereClause = "WHERE last_successful_scan >= UTC_TIMESTAMP() - INTERVAL " . $SETTINGS['last_scan_host_online'];
}

$query = "SELECT
    h.host_id,
    h.public_key,
    h.net_address,
    h.used_storage,
    h.storage_price,
    h.upload_price,
    h.download_price,
    h.total_storage,
    h.storage_price,
    h.protocol_version,
    h.software_version,
    c.country_name,
    c.region,
    COALESCE(s.total_score, 0) AS total_score,
    COALESCE(today_stats.used_storage - yesterday_stats.used_storage, 0) AS used_storage_diff
FROM
    Hosts h
LEFT JOIN Countries c
    ON h.country = c.country_code
LEFT JOIN (
    SELECT
        public_key,
        total_score
    FROM
        BenchmarkScores
    WHERE
        node = 'Global'
        AND date = UTC_DATE()
) s
    ON h.public_key = s.public_key
LEFT JOIN (
    SELECT
        public_key,
        used_storage
    FROM
        HostsDailyStats
    WHERE
        date = UTC_DATE() - INTERVAL 1 DAY
) yesterday_stats
    ON h.public_key = yesterday_stats.public_key
LEFT JOIN (
    SELECT
        public_key,
        used_storage
    FROM
        HostsDailyStats
    WHERE
        date = UTC_DATE()
) today_stats
    ON h.public_key = today_stats.public_key
$whereClause
ORDER BY $sortColumn $sortOrder";
$result = mysqli_query($mysqli, $query);
#$result = getData($query, 'hour');

// Store all rows in an array
$hosts = array();
$versions = array();
$queryParam = isset($_GET["query"]) ? $_GET["query"] : '';
$rank=0;
while ($row = mysqli_fetch_assoc($result)) {
#foreach ($result as $row) {


    $rank+=1;
    if ($queryParam === '' || stripos($row['net_address'], $queryParam) !== false) {
        $row['rank']=$rank;
    $hosts[] = $row;
    }
    
}

// Pagination parameters
$resultsPerPage =  isset($_GET["limit"]) ? $_GET["limit"] : 15;
if ($resultsPerPage==0) {
    $resultsPerPage=1000000;
}
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1; // Current page number

// Calculate total number of pages
$totalRows = count($hosts);
$totalPages = ceil($totalRows / $resultsPerPage);

// Calculate the starting and ending indices for the current page
$startIndex = ($page - 1) * $resultsPerPage;
$endIndex = $startIndex + $resultsPerPage - 1;
$endIndex = min($endIndex, $totalRows - 1); // Ensure endIndex does not exceed totalRows - 1

// Prepare array of hosts for the current page
$hostsPerPage = array_slice($hosts, $startIndex, $resultsPerPage);

// Output the results (for example, as JSON)
$output = [
    'hosts' => $hostsPerPage,
    'pagination' => [
        'total_pages' => $totalPages,
        'current_page' => $page,
    ],
];
$output['versions'] = $versions;
// update cache
setCache(json_encode($output), $cacheKey, 'hour');


echo json_encode($output);