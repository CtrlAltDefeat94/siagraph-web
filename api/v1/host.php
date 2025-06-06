<?php

header('Content-Type: application/json');
include_once "../../include/database.php";
include_once "../../include/utils.php";
include_once "../../include/redis.php";
include_once "../../include/config.php";

$query = "SELECT * FROM Hosts WHERE ";
// Fetch host ID from URL
if (isset($_GET['id'])) {
    $host_id = $_GET['id'];
    $query = $query . " host_id = $host_id";
} else if (isset($_GET['public_key'])) {
    $public_key = $_GET['public_key'];
    $query = "SELECT * FROM Hosts WHERE public_key = '$public_key'";
} else {
    $response['error'] = "Host ID or public key not provided in the URL.";
}

$result = mysqli_query($mysqli, $query);
$settings = mysqli_fetch_assoc($result);
$public_key = $settings['public_key'];

$cacheKey = 'host' . http_build_query($_GET);
$cacheresult = getCache($cacheKey);
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
    $json_data = json_decode($response, true);
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
// Perform the query
$result = mysqli_query($mysqli, $query);
$settings = mysqli_fetch_assoc($result);

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
    $hostsinsubnetquery = "SELECT net_address, resolved_ipv4 FROM Hosts WHERE resolved_ipv4 LIKE '" . $subnet . ".%'";# and not resolved_ipv4='".$settings['resolved_ipv4']."'";
    $hostsinsubnetesult = mysqli_query($mysqli, $hostsinsubnetquery);
    while ($host = mysqli_fetch_assoc($hostsinsubnetesult)) {
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
    $hostsinsubnetquery = "SELECT net_address, resolved_ipv6 FROM Hosts WHERE resolved_ipv6 LIKE '" . $subnet_prefix . ":%'"; // and not resolved_ipv6='".$settings['resolved_ipv6']."'";

    $hostsinsubnetesult = mysqli_query($mysqli, $hostsinsubnetquery);
    while ($host = mysqli_fetch_assoc($hostsinsubnetesult)) {
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

    $benchmarkquery = "SELECT
            avg(download_speed) AS download_speed,
            avg(upload_speed) AS upload_speed,
            avg(ttfb) AS ttfb
            FROM Benchmarks WHERE public_key= '$public_key' AND timestamp >= UTC_TIMESTAMP() - INTERVAL 7 DAY";
    $benchmarkresult = mysqli_query($mysqli, $benchmarkquery);
    $response['benchmark'] = mysqli_fetch_assoc($benchmarkresult);

    // Fetch daily data
    $dailydataquery = "SELECT date, used_storage, total_storage, storage_price, upload_price, download_price, accepting_contracts FROM HostsDailyStats WHERE public_key= '$public_key' ORDER BY date";
    $dailydataresult = mysqli_query($mysqli, $dailydataquery);
    $response['dailydata'] = array();
    while ($row = mysqli_fetch_assoc($dailydataresult)) {
        $response['dailydata'][] = $row;
    }

    // Fetch benchmark scores
    $benchmarkscorequery = "SELECT * FROM BenchmarkScores WHERE public_key= '$public_key' ORDER BY date ASC";
    $benchmarkscoreresult = mysqli_query($mysqli, $benchmarkscorequery);
    $response['node_scores']['global'] = array();
    while ($benchmarkscore = mysqli_fetch_assoc($benchmarkscoreresult)) {
        $date = $benchmarkscore['date'];
        $node = $benchmarkscore['node'];
        $download_score = $benchmarkscore['download_score'];
        $upload_score = $benchmarkscore['upload_score'];
        $ttfb_score = $benchmarkscore['ttfb_score'];
        $total_score = round($benchmarkscore['total_score']);

        if (!isset($response['node_scores'][$node])) {
            $response['node_scores'][$node] = array();
        }
        $stats = array();
        $stats['date'] = $date;
        $stats['download_score'] = (int) $download_score;
        $stats['upload_score'] = (int) $upload_score;
        $stats['ttfb_score'] = (int) $ttfb_score;
        $stats['total_score'] = (int) $total_score;
        $response['node_scores'][$node][] = $stats;

    }

    // Calculate global total score
    $globaltotalscore = ($response['node_scores']['Global']['total_score'] ?? 0);
    $sectioncomparequery = "SELECT
            AVG(contract_price) AS contractprice,
            AVG(storage_price) AS storageprice,
            AVG(upload_price) AS uploadprice,
            AVG(download_price) AS downloadprice,
            AVG(collateral) AS collateral,
            AVG(used_storage) AS used_storage
        FROM Hosts
        WHERE public_key IN (
            SELECT public_key
            FROM BenchmarkScores
            WHERE node = 'Global' AND total_score = $globaltotalscore);";
    $sectioncompareresult = mysqli_query($mysqli, $sectioncomparequery);
    $response['segment_averages'] = mysqli_fetch_assoc($sectioncompareresult);
} else {
    $response['error'] = "No settings found for the given host ID or public key.";
}


// update cache
setCache(json_encode($response), $cacheKey, 'hour');


echo json_encode($response);
