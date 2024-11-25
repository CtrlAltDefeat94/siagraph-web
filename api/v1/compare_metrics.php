<?php
include_once "../../include/redis.php";

header('Content-Type: application/json');
$queryString = http_build_query($_GET);
$combinedString = basename(__FILE__) . $queryString;

$recentStatsKey = empty(http_build_query(data: $_GET)) ? $recentStatsKey : md5($combinedString);
$cacheresult = getCache($recentStatsKey);

if ($cacheresult) {
    echo $cacheresult;
    die;
}


include_once "../../include/database.php";

// Fetch start and end dates from query parameters
$start_date = isset($_GET['change']) ? $_GET['change'] : null;
$end_date = isset($_GET['actual']) ? $_GET['actual'] : null;

if (empty($start_date) && empty($end_date)) {
    $end_date_obj = new DateTime('now', new DateTimeZone('UTC'));
    $end_date_obj->setTime($end_date_obj->format('H'), 0, 0); // Set to the most recent full hour
    $start_date_obj = clone $end_date_obj;
    $start_date_obj->modify('-24 hours');
    // Format the DateTime objects to YYYY-MM-DD HH:MM:SS
    $start_date = $start_date_obj->format('Y-m-d H:i:s');
    $end_date = $end_date_obj->format('Y-m-d H:i:s');
} else if (!$start_date || !$end_date) {
    echo json_encode(["error" => "Missing start or end date."]);
    die;
} else {
    $start_date_obj = !empty($start_date) ? DateTime::createFromFormat('Y-m-d', $start_date) : null;
    $end_date_obj = !empty($end_date) ? DateTime::createFromFormat('Y-m-d', $end_date) : null;
}


if (!$start_date_obj || !$end_date_obj) {
    echo json_encode(["error" => "Invalid date format. Please use YYYY-MM-DD."]);
    return;
}

if ($end_date_obj < $start_date_obj) {
    echo json_encode(["error" => "End date must be after the start date."]);
    return;
}

// Define the API URLs
$api_url = "https://api.siascan.com/metrics/revenue/daily";
$coingecko_url = "https://api.coingecko.com/api/v3/coins/siacoin";

// Convert DateTime objects to ISO 8601 format for API request
$start_date_iso = $start_date_obj->modify('-31 days')->format('c');
$end_date_iso = $end_date_obj->format('c');

// Fetch metrics data
$params = http_build_query(array('start' => $start_date_iso, 'end' => $end_date_iso));
$cacheKey = "revenuedata";
$cacheresult = getCache($cacheKey);

if ($cacheresult) {
    $response = $cacheresult;
} else {
    $response = file_get_contents($api_url . '?' . $params);
    if ($response === FALSE) {
        echo json_encode(["error" => "Unable to fetch data."]);
        return;
    } else {
        setCache($response, $cacheKey, 'day');
    }
}
$data = json_decode($response, true);
$past30Days = array();
$past30DaysYesterday = array();
$currencies = array_keys($data[0]['revenue']);

foreach ($currencies as $currency) {
    #todo verify
    $past30Days[$currency] = round((float) $data[count($data) - 2]['revenue'][$currency] - (float) $data[count($data) - 32]['revenue'][$currency], 2);
    $yesterday[$currency] = round((float) $data[count($data) - 3]['revenue'][$currency] - (float) $data[count($data) - 33]['revenue'][$currency],2);
    $past30DaysDifference[$currency] = $past30Days[$currency] - $yesterday[$currency];
}

$coinPriceResult = getCache($coinPriceKey);
if ($coinPriceResult) {
    $response = $coinPriceResult;
} else {
    $coin_price_response = file_get_contents($coingecko_url);
    if ($coin_price_response === FALSE) {
        echo json_encode(["error" => "Unable to fetch data."]);
        return;
    } else {
        $coin_price_data = json_decode($coin_price_response, true);

        $coin_price_today = $coin_price_data['market_data']['current_price'];
        if ($coin_price_today) {
            setCache($coin_price_today, $coinPriceKey, 'hour');
        }
    }
}
// Fetch current network stats
$query = "SELECT ROUND(total_storage,0) AS total_storage, ROUND(utilized_storage,0) AS utilized_storage, active_hosts, total_hosts FROM NetworkStats WHERE date = '" . $mysqli->real_escape_string($end_date) . "'";
$result = mysqli_query($mysqli, $query);
if (!$result) {
    echo json_encode(["error" => "Error: " . mysqli_error($mysqli)]);
    return;
}

$actualstats = mysqli_fetch_assoc($result);

// Fetch previous network stats
$query = "SELECT ROUND(total_storage,0) AS total_storage, ROUND(utilized_storage,0) AS utilized_storage, active_hosts, total_hosts FROM NetworkStats WHERE date = '" . $mysqli->real_escape_string($start_date) . "'";
$result = mysqli_query($mysqli, $query);
if (!$result) {
    echo json_encode(["error" => "Error: " . mysqli_error($mysqli)]);
    return;
}
$changestats = mysqli_fetch_assoc($result);
// Handle large numbers as whole numbers
$total_storage = $actualstats['total_storage'];
#$remaining_storage = $actualstats['remaining_storage'];
$utilized_storage = $actualstats['utilized_storage'];
$previous_total_storage = $changestats['total_storage'];
#$previous_remaining_storage = $changestats['remaining_storage'];
$utilized_difference = $utilized_storage - $changestats['utilized_storage'];
;
$total_difference = $total_storage - $previous_total_storage;

$active_hosts = (int) $actualstats['active_hosts'];
$previous_active_hosts = (int) $changestats['active_hosts'];
$active_hosts_difference = $previous_active_hosts - $active_hosts;

// Fetch coin price data for yesterday
$date = $start_date_obj->format("d-m-Y");
#todo cache
$coin_price_yesterday_response = file_get_contents($coingecko_url . '/history?date=' . $date);
$coin_price_yesterday_data = json_decode($coin_price_yesterday_response, true);
$coin_price_yesterday = $coin_price_yesterday_data['market_data']['current_price'];
$eur_diff = number_format($coin_price_today['eur'] - $coin_price_yesterday['eur'], 5);
$usd_diff = number_format($coin_price_today['usd'] - $coin_price_yesterday['usd'], 5);
$dateformat = "d F Y";

$data = [
    "actual_date" => $end_date_obj->format($dateformat),
    "change_date" => $start_date_obj->format($dateformat),
    "actual" => [
        "utilized_storage" => (int) $utilized_storage,
        "total_storage" => (int) $total_storage,
        "online_hosts" => (int) $active_hosts,
        "30_day_revenue" => $past30Days,
        "active_contracts" => 0,  // Adjust as needed
        "coin_price" => [
            "eur" => number_format($coin_price_today['eur'], 5),
            "usd" => number_format($coin_price_today['usd'], 5)
        ] # number_format instead of round for testing
    ],
    "change" => [
        "utilized_storage" => $utilized_difference,
        "total_storage" => $total_difference,
        "online_hosts" => $active_hosts_difference,
        "30_day_revenue" => $past30DaysDifference,
        "active_contracts" => 0,  // Placeholder for active contract change
        "coin_price" => [
            "eur" => $eur_diff,
            "usd" => $usd_diff
        ]
    ]
];

// Output the data
$jsonResult = json_encode($data, JSON_PRETTY_PRINT);
setCache($jsonResult, $recentStatsKey, 'hour');
// Print result
echo $jsonResult;