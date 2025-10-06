<?php
include_once "../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');
$cacheKey = md5(basename(__FILE__) . http_build_query($_GET));
$cacheresult = Cache::getCache($cacheKey);
if ($cacheresult) {
    echo $cacheresult;
    die;
}


// Get the selected sorting criteria
$sortMap = [
    // For ranking, sort by computed global rank (smaller is better)
    "rank" => "gr.rnk",
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
// Ascending for fields where smaller is better or alphabetical
$sortOrder = in_array($sortValue, ["age", "net_address", "rank", "storage_price"]) ? "asc" : "desc";

// Build filter conditions
$whereParts = [];
$wherePartsForRank = [];
$params = [];
$types = '';

if (!(isset($_GET['showinactive']) && $_GET['showinactive'] === 'true')) {
    $whereParts[] = "h.last_successful_scan >= UTC_TIMESTAMP() - INTERVAL {$SETTINGS['last_scan_host_online']}";
    $wherePartsForRank[] = "h3.last_successful_scan >= UTC_TIMESTAMP() - INTERVAL {$SETTINGS['last_scan_host_online']}";
}

if (isset($_GET['country']) && $_GET['country'] !== '') {
    $countryParam = trim($_GET['country']);

    // Normalize country parameter: accept either 2-letter code or country name
    if (strlen($countryParam) !== 2) {
        // Try to resolve country name to country code
        $countryLookup = $mysqli->prepare('SELECT country_code FROM Countries WHERE country_name = ? LIMIT 1');
        if ($countryLookup) {
            $countryLookup->bind_param('s', $countryParam);
            $countryLookup->execute();
            $countryRes = $countryLookup->get_result();
            $countryRow = $countryRes ? $countryRes->fetch_assoc() : null;
            if ($countryRow && isset($countryRow['country_code'])) {
                $countryParam = $countryRow['country_code'];
            }
            $countryLookup->close();
        }
    }

    $whereParts[] = 'h.country = ?';
    $params[] = $countryParam;
    $types .= 's';
    $escCountry = $mysqli->real_escape_string($countryParam);
    $wherePartsForRank[] = "h3.country = '" . $escCountry . "'";
}

if (isset($_GET['version']) && $_GET['version'] !== '') {
    $whereParts[] = 'h.software_version = ?';
    $params[] = $_GET['version'];
    $types .= 's';
    $escVer = $mysqli->real_escape_string($_GET['version']);
    $wherePartsForRank[] = "h3.software_version = '" . $escVer . "'";
}

if (isset($_GET['minStorage']) && is_numeric($_GET['minStorage'])) {
    $whereParts[] = 'h.total_storage >= ?';
    $value = (int)$_GET['minStorage'];
    $params[] = $value;
    $types .= 'i';
    $wherePartsForRank[] = 'h3.total_storage >= ' . $value;
}

if (isset($_GET['maxStoragePrice']) && is_numeric($_GET['maxStoragePrice'])) {
    $whereParts[] = 'h.storage_price <= ?';
    $value = (int)$_GET['maxStoragePrice'];
    $params[] = $value;
    $types .= 'i';
    $wherePartsForRank[] = 'h3.storage_price <= ' . $value;
}

if (isset($_GET['maxContractPrice']) && is_numeric($_GET['maxContractPrice'])) {
    $whereParts[] = 'h.contract_price <= ?';
    $value = (int)$_GET['maxContractPrice'];
    $params[] = $value;
    $types .= 'i';
    $wherePartsForRank[] = 'h3.contract_price <= ' . $value;
}

if (isset($_GET['maxUploadPrice']) && is_numeric($_GET['maxUploadPrice'])) {
    $whereParts[] = 'h.upload_price <= ?';
    $value = (int)$_GET['maxUploadPrice'];
    $params[] = $value;
    $types .= 'i';
    $wherePartsForRank[] = 'h3.upload_price <= ' . $value;
}

if (isset($_GET['maxDownloadPrice']) && is_numeric($_GET['maxDownloadPrice'])) {
    $whereParts[] = 'h.download_price <= ?';
    $value = (int)$_GET['maxDownloadPrice'];
    $params[] = $value;
    $types .= 'i';
    $wherePartsForRank[] = 'h3.download_price <= ' . $value;
}

if (isset($_GET['acceptingContracts']) && $_GET['acceptingContracts'] === 'true') {
    $whereParts[] = 'h.accepting_contracts = 1';
    $wherePartsForRank[] = 'h3.accepting_contracts = 1';
}

if (isset($_GET['query']) && $_GET['query'] !== '') {
    $whereParts[] = 'h.net_address LIKE CONCAT("%", ?, "%")';
    $params[] = $_GET['query'];
    $types .= 's';
}

$whereClause = $whereParts ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$whereClauseForRank = $wherePartsForRank ? 'WHERE ' . implode(' AND ', $wherePartsForRank) : '';

// Pagination parameters
$resultsPerPage = isset($_GET["limit"]) ? intval($_GET["limit"]) : 15;
if ($resultsPerPage == 0) {
    $resultsPerPage = 1000000;
}
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? intval($_GET['page']) : 1;
$offset = ($page - 1) * $resultsPerPage;

// Count total rows for pagination
$countQuery = "SELECT COUNT(*) AS cnt FROM Hosts h $whereClause";
$countStmt = $mysqli->prepare($countQuery);
if ($types !== '') {
    $bind = [&$types];
    foreach ($params as $k => $val) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$countStmt, 'bind_param'], $bind);
}
$countStmt->execute();
$countResult = $countStmt->get_result();
$totalRows = $countResult->fetch_assoc()['cnt'] ?? 0;
$countStmt->close();
$totalPages = ceil($totalRows / $resultsPerPage);

$sortColumnForRank = $sortColumn;
// Map base table alias to h3 for the ranking subquery
$sortColumnForRank = str_replace('h.', 'h3.', $sortColumnForRank);
// Special cases for rank and growth
if ($sortValue === 'rank') {
    $sortColumnForRank = 'gr2.rnk';
} elseif ($sortValue === 'growth') {
    $sortColumnForRank = 'COALESCE(today_fr.used_storage - yesterday_fr.used_storage, 0)';
}

$query = "SELECT
    h.host_id,
    h.public_key,
    h.net_address,
    h.used_storage,
    h.contract_price,
    h.storage_price,
    h.upload_price,
    h.download_price,
    h.total_storage,
    h.storage_price,
    h.accepting_contracts,
    h.protocol_version,
    h.software_version,
    h.location,
    c.country_name,
    c.region,
    CEIL(COALESCE(s.total_score, 0)) AS total_score,
    COALESCE(today_stats.used_storage - yesterday_stats.used_storage, 0) AS used_storage_diff,
    COALESCE(gr.rnk, 0) AS `rank`,
    COALESCE(fr.filtered_rnk, 0) AS filtered_rank
FROM
    Hosts h
LEFT JOIN Countries c ON h.country = c.country_code
LEFT JOIN (
    SELECT public_key, total_score
    FROM BenchmarkScores
    WHERE node = 'Global' AND date = UTC_DATE()
) s ON h.public_key = s.public_key
LEFT JOIN (
    SELECT public_key, used_storage
    FROM HostsDailyStats
    WHERE date = UTC_DATE() - INTERVAL 1 DAY
) yesterday_stats ON h.public_key = yesterday_stats.public_key
LEFT JOIN (
    SELECT public_key, used_storage
    FROM HostsDailyStats
    WHERE date = UTC_DATE()
) today_stats ON h.public_key = today_stats.public_key
/* Global ranks by total score (active-only), using window function for stability */
LEFT JOIN (
    SELECT ranked.public_key, ranked.rnk
    FROM (
        SELECT
            h2.public_key,
            ROW_NUMBER() OVER (
                ORDER BY COALESCE(s2.total_score, 0) DESC,
                         h2.used_storage DESC,
                         h2.total_storage DESC,
                         h2.net_address ASC,
                         h2.public_key ASC
            ) AS rnk
        FROM Hosts h2
        LEFT JOIN (
            SELECT public_key, total_score
            FROM BenchmarkScores
            WHERE node = 'Global' AND date = UTC_DATE()
        ) s2 ON h2.public_key = s2.public_key
        WHERE h2.last_successful_scan >= UTC_TIMESTAMP() - INTERVAL {$SETTINGS['last_scan_host_online']}
    ) AS ranked
) gr ON h.public_key = gr.public_key
/* Filtered ranks (ignore search query, honor other filters), ordered same as selection */
LEFT JOIN (
    SELECT ranked3.public_key, ranked3.filtered_rnk
    FROM (
        SELECT
            h3.public_key,
            ROW_NUMBER() OVER (
                ORDER BY $sortColumnForRank $sortOrder,
                         COALESCE(s3.total_score, 0) DESC,
                         h3.used_storage DESC,
                         h3.total_storage DESC,
                         h3.net_address ASC,
                         h3.public_key ASC
            ) AS filtered_rnk
        FROM Hosts h3
        LEFT JOIN (
            SELECT public_key, total_score
            FROM BenchmarkScores
            WHERE node = 'Global' AND date = UTC_DATE()
        ) s3 ON h3.public_key = s3.public_key
        LEFT JOIN (
            SELECT public_key, used_storage
            FROM HostsDailyStats
            WHERE date = UTC_DATE() - INTERVAL 1 DAY
        ) yesterday_fr ON h3.public_key = yesterday_fr.public_key
        LEFT JOIN (
            SELECT public_key, used_storage
            FROM HostsDailyStats
            WHERE date = UTC_DATE()
        ) today_fr ON h3.public_key = today_fr.public_key
        /* Global ranks for ordering when sort=rank */
        LEFT JOIN (
            SELECT ranked2.public_key, ranked2.rnk
            FROM (
                SELECT
                    h4.public_key,
                    ROW_NUMBER() OVER (
                        ORDER BY COALESCE(s4.total_score, 0) DESC,
                                 h4.used_storage DESC,
                                 h4.total_storage DESC,
                                 h4.net_address ASC,
                                 h4.public_key ASC
                    ) AS rnk
                FROM Hosts h4
                LEFT JOIN (
                    SELECT public_key, total_score
                    FROM BenchmarkScores
                    WHERE node = 'Global' AND date = UTC_DATE()
                ) s4 ON h4.public_key = s4.public_key
                WHERE h4.last_successful_scan >= UTC_TIMESTAMP() - INTERVAL {$SETTINGS['last_scan_host_online']}
            ) AS ranked2
        ) gr2 ON h3.public_key = gr2.public_key
        /* growth metric for ordering */
        CROSS JOIN (
            SELECT 1 AS dummy
        ) _
        $whereClauseForRank
    ) AS ranked3
) fr ON h.public_key = fr.public_key
$whereClause
ORDER BY $sortColumn $sortOrder,
         COALESCE(s.total_score, 0) DESC,
         h.used_storage DESC,
         h.total_storage DESC,
         h.net_address ASC,
         h.public_key ASC
LIMIT $resultsPerPage OFFSET $offset";

$stmt = $mysqli->prepare($query);
if ($types !== '') {
    $bind = [&$types];
    foreach ($params as $k => $val) {
        $bind[] = &$params[$k];
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}
$stmt->execute();
$result = $stmt->get_result();

$hosts = [];
$versions = [];
while ($row = mysqli_fetch_assoc($result)) {
    $hosts[] = $row;
}
$stmt->close();

$output = [
    'hosts' => $hosts,
    'pagination' => [
        'total_pages' => $totalPages,
        'current_page' => $page,
        'per_page' => $resultsPerPage,
        'total_rows' => $totalRows,
    ],
];
$output['versions'] = $versions;
Cache::setCache(json_encode($output), $cacheKey, 'hour');

echo json_encode($output);
