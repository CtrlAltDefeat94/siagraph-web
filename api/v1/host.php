<?php

header('Content-Type: application/json');
include_once "../../bootstrap.php";

use Siagraph\Utils\Cache;
use Siagraph\Utils\Formatter;

function priceDict($value, $usdRate = null, $eurRate = null): array {
    return [
        'sc'  => $value,
        'usd' => $usdRate ? round(($value / 1e24) * $usdRate, 2) : null,
        'eur' => $eurRate ? round(($value / 1e24) * $eurRate, 2) : null,
    ];
}

$stmt = null;
// Fetch host ID from URL
if (isset($_GET['id'])) {
    $host_id = $_GET['id'];
    $stmt = $mysqli->prepare("SELECT * FROM Hosts WHERE host_id = ?");
    if ($stmt) {
        $stmt->bind_param('i', $host_id);
    }
} else if (isset($_GET['public_key'])) {
    $public_key = $_GET['public_key'];
    $stmt = $mysqli->prepare("SELECT * FROM Hosts WHERE public_key = ?");
    if ($stmt) {
        $stmt->bind_param('s', $public_key);
    }
} else {
    $response['error'] = "Host ID or public key not provided in the URL.";
}

if (isset($stmt)) {
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = mysqli_fetch_assoc($result);
    $stmt->close();
} else {
    $settings = null;
}
$public_key = $settings['public_key'];

$cacheKey = 'host' . http_build_query($_GET);
$cacheresult = Cache::getCache($cacheKey);
if ($cacheresult) {
    echo $cacheresult;
    die;
}

// Build the URL
$url = $SETTINGS['explorer'] . '/hosts/' . $public_key;
// Fetch data
$json_data = null;
try {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception(message: curl_error($ch));
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 200) {
        throw new Exception("Unexpected HTTP code: $http_code");
    }
    $json_data = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
    curl_close($ch);
} catch (Exception $err) {
    $response['error'] = "Error fetching data.";
}
$response = [
    "host_id" => 0,
    "public_key" => "",
    "v2" => "",
    "net_address" => "",
    "first_seen" => "",
    "last_announced" => "",
    "country" => "",
    "location" => "",
    "software_version" => "",
    "protocol_version" => "",
    "resolved_ipv4" => "",
    "resolved_ipv6" => "",
    "uptime" => 0,
    "last_successful_scan" => "",
    "last_updated" => "",
    "settings" => [],
    "benchmark" => [],
    "node_scores" => [],
    "segment_averages" => [],
    "dailydata" => []



];


if ($settings) {
    $public_key = $settings['public_key'];
    $host_id = $settings['host_id'];

    $response = [
        "host_id" => (int) $settings['host_id'],
        "public_key" => $settings['public_key'],
        "v2" => $json_data['v2'],
        "net_address" => $settings['net_address'],
        "online" => $settings['last_successful_scan'], ## todo $settings['last_successful_scan'] >=utc now - 12 hours 
        "first_seen" => $settings['first_seen'],
        "last_announced" => $settings['last_announced'],
        "country" => $settings['country'],
        "location" => $settings['location'],
        "used_storage" => $settings['used_storage'],
        "total_storage" => $settings['total_storage'],
        "software_version" => $settings['software_version'],
        "protocol_version" => $settings['protocol_version'],
        "resolved_ipv4" => $settings['resolved_ipv4'],
        "hosts_in_subnetv4" => [],
        "resolved_ipv6" => $settings['resolved_ipv6'],
        "hosts_in_subnetv6" => [],
        "uptime" => (float) $settings['uptime'],
        "last_successful_scan" => $settings['last_successful_scan'],
        "last_updated" => $settings['last_updated'],
        "settings" => [
            "acceptingcontracts" => 0,
            "baserpcprice" => 0,
            "collateral" => 0,
            "contractprice" => 0,
            "egressprice" => 0,
            "ingressprice" => 0,
            "ephemeralaccountexpiry" => 0,
            "maxcollateral" => 0,
            "maxdownloadbatchsize" => 0,
            "maxephemeralaccountbalance" => 0,
            "maxrevisebatchsize" => 0,
            "maxduration" => 0,
            "freesectorprice" => 0,
            "sectorsize" => 0,
            "siamuxport" => 0,
            "storageprice" => 0,
            "windowsize" => 0,
        ],
        "benchmark" => [],
        "node_scores" => [],
        "segment_averages" => [],
        "dailydata" => []
    ];
    $subnet = implode('.', array_slice(explode('.', $settings['resolved_ipv4']), 0, 3));
    $pattern = $subnet . '.%';
    $hostsinsubnetstmt = $mysqli->prepare("SELECT net_address, resolved_ipv4 FROM Hosts WHERE resolved_ipv4 LIKE ?");
    if ($hostsinsubnetstmt) {
        $hostsinsubnetstmt->bind_param('s', $pattern);
        $hostsinsubnetstmt->execute();
        $hostsinsubnetesult = $hostsinsubnetstmt->get_result();
    }
    while (isset($hostsinsubnetesult) && ($host = mysqli_fetch_assoc($hostsinsubnetesult))) {
        $response['hosts_in_subnetv4'][] = [
            'net_address' => $host['net_address'],
            'resolved_ipv4' => $host['resolved_ipv4']
        ];
    }
    // Assume $settings['resolved_ipv6'] is a valid IPv6 address like "2001:0db8:85a3:0000:0000:8a2e:0370:7334"
    $ipv6_parts = explode(':', $settings['resolved_ipv6']);

    // Use the first 4 hextets (you can adjust to match your subnet size, e.g., 3 for /48)
    $subnet_prefix = implode(':', array_slice($ipv6_parts, 0, 4));

    // Create a LIKE clause using the subnet prefix
    $ipv6pattern = $subnet_prefix . ':%';
    $hostsinsubnetstmt = $mysqli->prepare("SELECT net_address, resolved_ipv6 FROM Hosts WHERE resolved_ipv6 LIKE ?");
    if ($hostsinsubnetstmt) {
        $hostsinsubnetstmt->bind_param('s', $ipv6pattern);
        $hostsinsubnetstmt->execute();
        $hostsinsubnetesult = $hostsinsubnetstmt->get_result();
    }
    while (isset($hostsinsubnetesult) && ($host = mysqli_fetch_assoc($hostsinsubnetesult))) {
        $response['hosts_in_subnetv6'][] = [
            'net_address' => $host['net_address'],
            'resolved_ipv6' => $host['resolved_ipv6']
        ];
    }
    if (!$response['v2']) {
        ### V1 host
        $response['settings']["acceptingcontracts"] = $json_data['settings']['acceptingcontracts'];
        $response['settings']["baserpcprice"] = $json_data['settings']['baserpcprice'];
        $response['settings']["collateral"] = $json_data['settings']['collateral'];
        $response['settings']["contractprice"] = $json_data['settings']['contractprice'];
        $response['settings']["egressprice"] = $json_data['settings']['downloadbandwidthprice'];
        $response['settings']["ingressprice"] = $json_data['settings']['uploadbandwidthprice'];
        $response['settings']["ephemeralaccountexpiry"] = $json_data['settings']['ephemeralaccountexpiry'];
        $response['settings']["maxcollateral"] = $json_data['settings']['maxcollateral'];
        $response['settings']["maxdownloadbatchsize"] = $json_data['settings']['maxdownloadbatchsize'];
        $response['settings']["maxephemeralaccountbalance"] = $json_data['settings']['maxephemeralaccountbalance'];
        $response['settings']["maxrevisebatchsize"] = $json_data['settings']['maxrevisebatchsize'];
        $response['settings']["maxduration"] = $json_data['settings']['maxduration'];
        $response['settings']["freesectorprice"] = $json_data['settings']['sectoraccessprice'];
        $response['settings']["sectorsize"] = $json_data['settings']['sectorsize'];
        $response['settings']["siamuxport"] = $json_data['settings']['siamuxport'];
        $response['settings']["storageprice"] = $json_data['settings']['storageprice'];
        $response['settings']["windowsize"] = $json_data['settings']['windowsize'];

    } else {
        ### V2 host
        $response['settings']["acceptingcontracts"] = $json_data['v2Settings']['acceptingContracts'];
        $response['settings']["collateral"] = $json_data['v2Settings']['prices']['collateral'];
        $response['settings']["contractprice"] = $json_data['v2Settings']['prices']['contractPrice'];
        $response['settings']["egressprice"] = $json_data['v2Settings']['prices']['egressPrice'];
        $response['settings']["ingressprice"] = $json_data['v2Settings']['prices']['ingressPrice'];
        $response['settings']["maxcollateral"] = $json_data['v2Settings']['maxCollateral'];
        $response['settings']["maxduration"] = $json_data['v2Settings']['maxContractDuration'];
        $response['settings']["storageprice"] = $json_data['v2Settings']['prices']['storagePrice'];
        $response['settings']["freesectorprice"] = $json_data['v2Settings']['prices']['freeSectorPrice'];

    }

    $benchmarkstmt = $mysqli->prepare(
        "SELECT avg(download_speed) AS download_speed,"
        . " avg(upload_speed) AS upload_speed,"
        . " avg(ttfb) AS ttfb"
        . " FROM Benchmarks WHERE public_key = ?"
        . " AND timestamp >= UTC_TIMESTAMP() - INTERVAL 7 DAY"
    );
    if ($benchmarkstmt) {
        $benchmarkstmt->bind_param('s', $public_key);
        $benchmarkstmt->execute();
        $benchmarkresult = $benchmarkstmt->get_result();
        $response['benchmark'] = mysqli_fetch_assoc($benchmarkresult);
    }

    // Fetch daily data
    $dailydatastmt = $mysqli->prepare(
        "SELECT h.date, h.used_storage, h.total_storage, h.storage_price, h.upload_price, h.download_price, h.accepting_contracts, er.usd, er.eur "
        . "FROM HostsDailyStats h "
        . "LEFT JOIN (SELECT DATE(timestamp) AS date, AVG(usd) AS usd, AVG(eur) AS eur FROM ExchangeRates WHERE currency_code = 'sc' GROUP BY DATE(timestamp)) er "
        . "ON DATE(h.date) = er.date WHERE h.public_key = ? ORDER BY h.date"
    );
    if ($dailydatastmt) {
        $dailydatastmt->bind_param('s', $public_key);
        $dailydatastmt->execute();
        $dailydataresult = $dailydatastmt->get_result();
        $response['dailydata'] = array();
        while ($row = mysqli_fetch_assoc($dailydataresult)) {
            $usdRate = isset($row['usd']) ? (float)$row['usd'] : null;
            $eurRate = isset($row['eur']) ? (float)$row['eur'] : null;
            $response['dailydata'][] = [
                'date' => $row['date'],
                'used_storage' => $row['used_storage'],
                'total_storage' => $row['total_storage'],
                'storage_price' => priceDict($row['storage_price'], $usdRate, $eurRate),
                'upload_price' => priceDict($row['upload_price'], $usdRate, $eurRate),
                'download_price' => priceDict($row['download_price'], $usdRate, $eurRate),
                'accepting_contracts' => $row['accepting_contracts'],
            ];
        }
    }

    // Fetch benchmark scores
    $benchmarkscorestmt = $mysqli->prepare(
        "SELECT * FROM BenchmarkScores WHERE public_key = ? ORDER BY date ASC"
    );
    if ($benchmarkscorestmt) {
        $benchmarkscorestmt->bind_param('s', $public_key);
        $benchmarkscorestmt->execute();
        $benchmarkscoreresult = $benchmarkscorestmt->get_result();
    }
    $response['node_scores']['global'] = array();
    while (isset($benchmarkscoreresult) && ($benchmarkscore = mysqli_fetch_assoc($benchmarkscoreresult))) {
        $date = $benchmarkscore['date'];
        $node = $benchmarkscore['node'];
        $download_score = $benchmarkscore['download_score'];
        $upload_score = $benchmarkscore['upload_score'];
        $ttfb_score = $benchmarkscore['ttfb_score'];
        $total_score = ceil(($benchmarkscore['total_score']));
        

        if (!isset($response['node_scores'][$node])) {
            $response['node_scores'][$node] = array();
        }
        $stats = array();
        $stats['date'] = $date;
        $stats['download_score'] = (int) ceil($download_score);
        $stats['upload_score'] = (int) ceil($upload_score);
        $stats['ttfb_score'] = (int) ceil($ttfb_score);
        $stats['total_score'] = (int) $total_score;
        $response['node_scores'][$node][] = $stats;

    }
    // Calculate global total score
    $globaltotalscore = isset($response['node_scores']['global']) 
    ? ($response['node_scores']['global'][array_key_last($response['node_scores']['global'])]['total_score'] ?? 0) 
    : 0;
    $sectioncomparestmt = $mysqli->prepare(
        "SELECT
                    ROUND(AVG(contract_price)) AS contractprice,
                    ROUND(AVG(storage_price)) AS storageprice,
                    ROUND(AVG(upload_price)) AS uploadprice,
                    ROUND(AVG(download_price)) AS downloadprice,
                    ROUND(AVG(collateral)) AS collateral,
                    ROUND(AVG(used_storage)) AS used_storage
                FROM Hosts
                WHERE public_key IN (
                    SELECT public_key
                    FROM BenchmarkScores
                    WHERE node = 'Global'
                    AND CEIL(total_score) = ?
                    AND date = CURDATE()
                );"
    );
    if ($sectioncomparestmt) {
        $sectioncomparestmt->bind_param('d', $globaltotalscore);
        $sectioncomparestmt->execute();
        $sectioncompareresult = $sectioncomparestmt->get_result();
        $response['segment_averages'] = mysqli_fetch_assoc($sectioncompareresult);
    }
} else {
    $response['error'] = "No settings found for the given host ID or public key.";
}

// Add fiat conversions using current coin price
$coinPrice = Cache::getCache(Cache::COIN_PRICE_KEY);
$coinRate = $coinPrice ? json_decode($coinPrice, true, 512, JSON_BIGINT_AS_STRING) : [];
$usdRateNow = $coinRate['usd'] ?? null;
$eurRateNow = $coinRate['eur'] ?? null;

foreach ([
    'baserpcprice',
    'collateral',
    'contractprice',
    'egressprice',
    'ingressprice',
    'maxcollateral',
    'freesectorprice',
    'storageprice'
] as $field) {
    if (isset($response['settings'][$field])) {
        $response['settings'][$field] = priceDict($response['settings'][$field], $usdRateNow, $eurRateNow);
    }
}

if (!empty($response['segment_averages'])) {
    foreach (['contractprice','storageprice','uploadprice','downloadprice','collateral'] as $f) {
        if (isset($response['segment_averages'][$f])) {
            $response['segment_averages'][$f] = priceDict($response['segment_averages'][$f], $usdRateNow, $eurRateNow);
        }
    }
}

// update cache
Cache::setCache(json_encode($response), $cacheKey, 'hour');


echo json_encode($response);
