<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

$cacheKey = md5(basename(__FILE__) . http_build_query($_GET));
if ($cached = Cache::getCache($cacheKey)) {
    echo $cached;
    exit;
}

$dbConfig = $SETTINGS['database'] ?? [];
if (
    !isset($dbConfig['servername'], $dbConfig['username'], $dbConfig['password'], $dbConfig['raw_database'])
    || $dbConfig['raw_database'] === ''
) {
    http_response_code(500);
    echo json_encode(['error' => 'Raw database configuration missing.']);
    exit;
}

$rawConnection = new mysqli(
    $dbConfig['servername'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['raw_database']
);

if ($rawConnection->connect_errno) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to raw database.', 'details' => $rawConnection->connect_error]);
    exit;
}

$limitParam = isset($_GET['limit']) ? (int) $_GET['limit'] : 10;
$limit = $limitParam > 0 ? $limitParam : 10;

$query = "
    SELECT SUM(filesize) AS total_filesize
    FROM Contracts
    WHERE (contract_id, revisionnumber) IN (
        SELECT contract_id, MAX(revisionnumber)
        FROM Contracts
        WHERE windowend >= (SELECT MAX(block_height) FROM Contracts)
        GROUP BY contract_id
    )
    AND resolution_type IS NULL
    GROUP BY renter_wallet_address
    HAVING total_filesize > 0
    ORDER BY total_filesize DESC
";

$result = $rawConnection->query($query);

if ($result === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to execute renter totals query.', 'details' => $rawConnection->error]);
    exit;
}

$sizes = [];
$totalFilesize = 0;
while ($row = $result->fetch_assoc()) {
    $size = isset($row['total_filesize']) ? (int) $row['total_filesize'] : 0;
    $sizes[] = $size;
    $totalFilesize += $size;
}

$totalRenters = count($sizes);
$sizes = array_slice($sizes, 0, $limit);

$result->free();
$rawConnection->close();

$response = [
    'largest_sizes' => $sizes,
    'total_filesize' => $totalFilesize,
    'total_renters' => $totalRenters
];

$jsonResult = json_encode($response);
Cache::setCache($jsonResult, $cacheKey, 'day');

echo $jsonResult;
