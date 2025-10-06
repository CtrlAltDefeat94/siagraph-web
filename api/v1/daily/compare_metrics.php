<?php
include_once "../../../bootstrap.php";
include_once "../../../config.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');
$queryString = http_build_query($_GET);
$combinedString = basename(__FILE__) . $queryString;

$recentStatsKey = empty(http_build_query(data: $_GET)) ? Cache::RECENT_STATS_KEY : md5($combinedString);
$cacheresult = Cache::getCache($recentStatsKey);

if ($cacheresult) {
    echo $cacheresult;
    die;
}

$format = 'Y-m-d\TH:i:s\Z';

// Fetch start and end dates from query parameters
$end_date = isset($_GET['actual_date']) ? $_GET['actual_date'] : null;
$start_date = isset($_GET['compare_date']) ? $_GET['compare_date'] : null;

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

// Exchange rate API endpoints for current price
$eur_url = $SETTINGS['explorer']."/exchange-rate/siacoin/eur";
$usd_url = $SETTINGS['explorer']."/exchange-rate/siacoin/usd";

// Calculate date ranges for revenue calculations
$periodEnd = clone $end_date_obj;
$periodEnd->modify('-1 day');
$periodStart = clone $periodEnd;
$periodStart->modify('-29 days');

$prevPeriodEnd = clone $periodEnd;
$prevPeriodEnd->modify('-1 day');
$prevPeriodStart = clone $prevPeriodEnd;
$prevPeriodStart->modify('-29 days');

$periodStartStr = $periodStart->format('Y-m-d');
$periodEndStr = $periodEnd->format('Y-m-d');
$prevPeriodStartStr = $prevPeriodStart->format('Y-m-d');
$prevPeriodEndStr = $prevPeriodEnd->format('Y-m-d');

// Helper function to sum revenue for a period
function sumRevenue(mysqli $mysqli, string $start, string $end): array {
    $query = "SELECT na.contract_revenue, er.usd, er.eur
              FROM NetworkAggregates na
              LEFT JOIN ExchangeRates er ON DATE(na.date) = DATE(er.timestamp) AND er.currency_code = 'sc'
              WHERE na.date BETWEEN ? AND ?";

    $stmt = $mysqli->prepare($query);
    if (!$stmt) {
        return ['sc' => '0', 'usd' => 0.0, 'eur' => 0.0];
    }
    $stmt->bind_param('ss', $start, $end);
    $stmt->execute();
    $result = $stmt->get_result();
    $sc = '0';
    $usd = 0.0;
    $eur = 0.0;
    while ($row = $result->fetch_assoc()) {
        $sc = bcadd($sc, $row['contract_revenue'], 0);
        $scCoins = bcdiv($row['contract_revenue'], '1000000000000000000000000', 24);
        if (!is_null($row['usd'])) {
            $usd += (float) bcmul($scCoins, $row['usd'], 8);
        }
        if (!is_null($row['eur'])) {
            $eur += (float) bcmul($scCoins, $row['eur'], 8);
        }
    }
    $stmt->close();
    return ['sc' => $sc, 'usd' => $usd, 'eur' => $eur];
}

$currentRev = sumRevenue($mysqli, $periodStartStr, $periodEndStr);
$previousRev = sumRevenue($mysqli, $prevPeriodStartStr, $prevPeriodEndStr);

$past30Days = [
    'sc' => $currentRev['sc'],
    'usd' => round($currentRev['usd'], 2),
    'eur' => round($currentRev['eur'], 2)
];

$past30DaysDifference = [
    'sc' => (string) bcsub($currentRev['sc'], $previousRev['sc'], 0),
    'usd' => (string) round($currentRev['usd'] - $previousRev['usd'], 2),
    'eur' => (string) round($currentRev['eur'] - $previousRev['eur'], 2)
];

// Fetch active contract counts. Use DATE(date) in case the column is stored as a
// DATETIME rather than just a DATE
$stmt = $mysqli->prepare("SELECT active_contracts FROM DailyMetrics WHERE DATE(date) = ?");
$activeContracts = 0;
if ($stmt) {
    $dateStr = $periodEnd->format('Y-m-d');
    $stmt->bind_param('s', $dateStr);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $activeContracts = (int)$row['active_contracts'];
    }
    $stmt->close();
}

$stmt = $mysqli->prepare("SELECT active_contracts FROM DailyMetrics WHERE DATE(date) = ?");
$activeContractsYesterday = 0;
if ($stmt) {
    $dateStr = $prevPeriodEnd->format('Y-m-d');
    $stmt->bind_param('s', $dateStr);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($row = $res->fetch_assoc()) {
        $activeContractsYesterday = (int)$row['active_contracts'];
    }
    $stmt->close();
}

$coinPriceResult = Cache::getCache(Cache::COIN_PRICE_KEY);
if ($coinPriceResult) {
    $coin_price_today = json_decode($coinPriceResult, true, 512, JSON_BIGINT_AS_STRING);
    if (!isset($coin_price_today['sc'])) {
        $coin_price_today['sc'] = 1.0;
    }
} else {
    $eur = @file_get_contents($eur_url);
    $usd = @file_get_contents($usd_url);
    if ($eur === false || $usd === false) {
        echo json_encode(["error" => "Unable to fetch coin price"]);
        return;
    }
    $coin_price_today = [
        'eur' => (float) $eur,
        'usd' => (float) $usd,
        'sc'  => 1.0,
    ];
    Cache::setCache(json_encode($coin_price_today), Cache::COIN_PRICE_KEY, 'hour');
}
// Fetch current network stats
$stmt = $mysqli->prepare("SELECT ROUND(total_storage,0) AS total_storage, ROUND(utilized_storage,0) AS utilized_storage, active_hosts, total_hosts FROM NetworkStats WHERE date = ?");
if (!$stmt) {
    echo json_encode(["error" => "Prepare failed: " . $mysqli->error]);
    return;
}
$stmt->bind_param('s', $end_date);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    echo json_encode(["error" => "Error: " . mysqli_error($mysqli)]);
    return;
}

$actualstats = mysqli_fetch_assoc($result);
$stmt->close();

// Fetch previous network stats
$stmt_prev = $mysqli->prepare("SELECT ROUND(total_storage,0) AS total_storage, ROUND(utilized_storage,0) AS utilized_storage, active_hosts, total_hosts FROM NetworkStats WHERE date = ?");
if (!$stmt_prev) {
    echo json_encode(["error" => "Prepare failed: " . $mysqli->error]);
    return;
}
$stmt_prev->bind_param('s', $start_date);
$stmt_prev->execute();
$result = $stmt_prev->get_result();
if (!$result) {
    echo json_encode(["error" => "Error: " . mysqli_error($mysqli)]);
    return;
}
$comparestats = mysqli_fetch_assoc($result);
$stmt_prev->close();
// Handle large numbers as whole numbers
$total_storage = $actualstats['total_storage'];
#$remaining_storage = $actualstats['remaining_storage'];
$utilized_storage = $actualstats['utilized_storage'];
$previous_total_storage = $comparestats['total_storage'];
$utilized_difference = $utilized_storage - $comparestats['utilized_storage'];
;
$total_difference = $total_storage - $previous_total_storage;

$active_hosts = (int) $actualstats['active_hosts'];
$previous_active_hosts = (int) $comparestats['active_hosts'];
$active_hosts_difference = $active_hosts - $previous_active_hosts;

// Fetch coin price data for yesterday from the database
$stmt = $mysqli->prepare("SELECT usd, eur FROM ExchangeRates WHERE DATE(timestamp) = ? AND currency_code = 'sc' ORDER BY timestamp DESC LIMIT 1");
$coin_price_yesterday = ['usd' => 0.0, 'eur' => 0.0, 'sc' => 1.0];
if ($stmt) {
    $dateStr = $start_date_obj->format('Y-m-d');
    $stmt->bind_param('s', $dateStr);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && ($row = $res->fetch_assoc())) {
        $coin_price_yesterday['usd'] = (float) $row['usd'];
        $coin_price_yesterday['eur'] = (float) $row['eur'];
        $coin_price_yesterday['sc'] = 1.0;
    }
    $stmt->close();
}

// Ensure the price differences do not use scientific notation and keep full raw value as string
// Helper to normalize numeric input to a non-scientific string for bcmath
$toDecimalString = function ($num): string {
    if (is_string($num)) {
        // If already a numeric string, return as-is
        if (preg_match('/^[-+]?\d*(?:\.\d+)?$/', $num)) {
            return $num;
        }
        // Fallback for any other string representation
        $num = (float) $num;
    }
    // Format with high precision and strip trailing zeros
    $s = sprintf('%.16F', (float) $num);
    $s = rtrim(rtrim($s, '0'), '.');
    return $s === '' || $s === '-' ? '0' : $s;
};

// Use bcmath (or polyfill) to compute precise string differences
$eur_diff = rtrim(rtrim(bcsub($toDecimalString($coin_price_today['eur']), $toDecimalString($coin_price_yesterday['eur']), 16), '0'), '.');
$usd_diff = rtrim(rtrim(bcsub($toDecimalString($coin_price_today['usd']), $toDecimalString($coin_price_yesterday['usd']), 16), '0'), '.');
if ($eur_diff === '' || $eur_diff === '-') { $eur_diff = '0'; }
if ($usd_diff === '' || $usd_diff === '-') { $usd_diff = '0'; }
$sc_diff = '0';
$data = [
   # "actual_date" => $end_date_obj->format('Y-m-d\TH:i:s\Z'),
 #   "compare_date" => $start_date_obj->format('Y-m-d\TH:i:s\Z'),
    "actual" => [
        "utilized_storage" => (int) $utilized_storage,
        "total_storage" => (int) $total_storage,
        "online_hosts" => (int) $active_hosts,
        "30_day_revenue" => $past30Days,
        "active_contracts" => $activeContracts,  
        "coin_price" => [
            "eur" => (float) round($coin_price_today['eur'], 5),
            "usd" => (float) round($coin_price_today['usd'], 5),
            "sc"  => 1.0
        ]
    ],
    "change" => [
        "utilized_storage" => $utilized_difference,
        "total_storage" => $total_difference,
        "online_hosts" => $active_hosts_difference,
        "30_day_revenue" => $past30DaysDifference,
        "active_contracts" => $activeContracts - $activeContractsYesterday, 
        "coin_price" => [
            // Keep as strings to avoid scientific notation in JSON
            "eur" => (string) $eur_diff,
            "usd" => (string) $usd_diff,
            "sc"  => (string) $sc_diff
        ]
    ]
];

// Output the data
$jsonResult = json_encode($data, JSON_PRETTY_PRINT);
Cache::setCache($jsonResult, $recentStatsKey, 'hour');
// Print result
echo $jsonResult;
