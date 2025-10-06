<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

// Handle GET parameters
$start = isset($_GET['start']) ? $_GET['start'] : null;
$end   = isset($_GET['end'])   ? $_GET['end']   : null;

// Validate and normalize dates
$whereClauses = ["currency_code = 'sc'"];

if ($start) {
    $startDate = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $start, new DateTimeZone('UTC'));
    if (!$startDate) {
        echo json_encode(["error" => "Invalid start date format. Use ISO8601 like 2025-04-01T00:00:00Z"]);
        exit();
    }
    $whereClauses[] = "timestamp >= '" . $startDate->format('Y-m-d') . "'";
}

if ($end) {
    $endDate = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $end, new DateTimeZone('UTC'));
    if (!$endDate) {
        echo json_encode(["error" => "Invalid end date format. Use ISO8601 like 2025-04-01T00:00:00Z"]);
        exit();
    }
    $whereClauses[] = "timestamp <= '" . $endDate->format('Y-m-d') . "'";
}

$whereSQL = implode(" AND ", $whereClauses);

// Create unique cache key
$queryString = http_build_query($_GET);
$combinedString = basename(__FILE__) . $queryString;
$cacheKey = md5($combinedString);

$cacheresult = Cache::getCache($cacheKey);
if ($cacheresult) {
    echo $cacheresult;
    die;
}

// Build query
$query = "
    SELECT timestamp,
           btc,
           cad,
           cny,
           eth,
           eur,
           gbp,
           jpy,
           rub,
           usd
    FROM ExchangeRates
    WHERE $whereSQL
    ORDER BY timestamp ASC;
";
$result = mysqli_query($mysqli, $query);

if (!$result) {
    http_response_code(500);
    echo json_encode(["error" => "Query failed: " . mysqli_error($mysqli)]);
    exit();
}

$jsonlist = [];
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);

foreach ($rows as $row) {
    $date = new DateTime($row['timestamp']);
    $formatted_date = (new DateTime($row['timestamp'], new DateTimeZone('UTC')))
                    ->format('Y-m-d\T00:00:00\Z');

    $entry = ['date' => $formatted_date];

    foreach (['btc', 'cad', 'cny', 'eth', 'eur', 'gbp', 'jpy', 'rub', 'usd'] as $currency) {
        if (!empty($row[$currency]) && $row[$currency] != 0) {
            $entry[$currency] = (float)$row[$currency];
        }
    }

    $jsonlist[] = $entry;
}

$jsonResult = json_encode($jsonlist);
Cache::setCache($jsonResult, $cacheKey, 'day');

echo $jsonResult;
