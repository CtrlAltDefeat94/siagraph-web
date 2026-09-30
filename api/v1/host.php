<?php

header('Content-Type: application/json');
include_once "../../bootstrap.php";

use Siagraph\Utils\Cache;
use Predis\Client;

function normalizeProtocolVersion($value): string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') {
        return '';
    }
    $compact = preg_replace('/\s+/', '', $raw);
    if (!is_string($compact) || $compact === '') {
        return $raw;
    }
    $hasVPrefix = preg_match('/^v/i', $compact) === 1;
    $core = preg_replace('/^v\.?/i', '', $compact);
    if (!is_string($core)) {
        return $raw;
    }
    $core = preg_replace('/[^0-9.]/', '.', $core);
    $core = preg_replace('/\.+/', '.', (string) $core);
    $core = trim((string) $core, '.');
    if ($core === '') {
        return $raw;
    }
    return ($hasVPrefix ? 'v' : '') . $core;
}

function priceDict($value, $usdRate = null, $eurRate = null): array
{
    return [
        'sc' => $value,
        'usd' => $usdRate ? round(($value / 1e24) * $usdRate, 2) : null,
        'eur' => $eurRate ? round(($value / 1e24) * $eurRate, 2) : null,
    ];
}

$requestHostId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$requestPublicKey = isset($_GET['public_key']) ? trim((string) $_GET['public_key']) : '';

$response = [
    'known' => false,
    'host_id' => 0,
    'public_key' => $requestPublicKey,
    'v2' => false,
    'net_address' => '',
    'online' => false,
    'first_seen' => '',
    'last_announced' => '',
    'country' => '',
    'location' => '',
    'used_storage' => 0,
    'total_storage' => 0,
    'software_version' => '',
    'protocol_version' => '',
    'resolved_ipv4' => '',
    'hosts_in_subnetv4' => [],
    'resolved_ipv6' => '',
    'hosts_in_subnetv6' => [],
    'uptime' => 0,
    'last_successful_scan' => '',
    'last_updated' => '',
    'settings' => [
        'acceptingcontracts' => 0,
        'baserpcprice' => 0,
        'collateral' => 0,
        'contractprice' => 0,
        'egressprice' => 0,
        'ingressprice' => 0,
        'ephemeralaccountexpiry' => 0,
        'maxcollateral' => 0,
        'maxdownloadbatchsize' => 0,
        'maxephemeralaccountbalance' => 0,
        'maxrevisebatchsize' => 0,
        'maxduration' => 0,
        'freesectorprice' => 0,
        'sectorsize' => 0,
        'siamuxport' => 0,
        'storageprice' => 0,
        'windowsize' => 0,
    ],
    'benchmark' => [],
    'node_scores' => [],
    'segment_averages' => [],
    'dailydata' => [],
];

if ($requestHostId === null && $requestPublicKey === '') {
    $response['error'] = 'Host ID or public key not provided in the URL.';
    echo json_encode($response);
    exit;
}

$cacheKey = 'host' . http_build_query($_GET);
$cacheresult = Cache::getCache($cacheKey);
if ($cacheresult) {
    $cachedPayload = json_decode($cacheresult, true);
    if (is_array($cachedPayload)) {
        if (!array_key_exists('known', $cachedPayload)) {
            $cachedPayload['known'] = ((int) ($cachedPayload['host_id'] ?? 0) > 0);
        }
        if (isset($cachedPayload['protocol_version'])) {
            $cachedPayload['protocol_version'] = normalizeProtocolVersion($cachedPayload['protocol_version']);
        }
        $normalizedCached = json_encode($cachedPayload);
        if (is_string($normalizedCached)) {
            echo $normalizedCached;
            exit;
        }
    }
    echo $cacheresult;
    exit;
}

$stmt = null;
if ($requestHostId !== null) {
    $stmt = $mysqli->prepare('SELECT * FROM Hosts WHERE host_id = ?');
    if ($stmt) {
        $stmt->bind_param('i', $requestHostId);
    }
} elseif ($requestPublicKey !== '') {
    $stmt = $mysqli->prepare('SELECT * FROM Hosts WHERE public_key = ?');
    if ($stmt) {
        $stmt->bind_param('s', $requestPublicKey);
    }
}

$settings = null;
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $settings = mysqli_fetch_assoc($result) ?: null;
    $stmt->close();
}

if (!$settings) {
    $response['error'] = 'Host not indexed yet.';
    try {
        $redis = new Client([
            'scheme' => 'tcp',
            'host' => $SETTINGS['redis_ip'] ?? '127.0.0.1',
            'port' => 6379,
        ]);
        $redis->setex($cacheKey, 300, json_encode($response));
    } catch (Exception $e) {
        // ignore cache errors
    }
    echo json_encode($response);
    exit;
}

$public_key = (string) $settings['public_key'];
$host_id = (int) $settings['host_id'];
$response['known'] = true;
$response['host_id'] = $host_id;
$response['public_key'] = $public_key;
$response['net_address'] = (string) ($settings['net_address'] ?? '');
$response['online'] = (bool) ($settings['last_successful_scan'] ?? false);
$response['first_seen'] = (string) ($settings['first_seen'] ?? '');
$response['last_announced'] = (string) ($settings['last_announced'] ?? '');
$response['country'] = (string) ($settings['country'] ?? '');
$response['location'] = (string) ($settings['location'] ?? '');
$response['used_storage'] = (float) ($settings['used_storage'] ?? 0);
$response['total_storage'] = (float) ($settings['total_storage'] ?? 0);
$response['software_version'] = (string) ($settings['software_version'] ?? '');
$response['protocol_version'] = normalizeProtocolVersion($settings['protocol_version'] ?? '');
$response['resolved_ipv4'] = (string) ($settings['resolved_ipv4'] ?? '');
$response['resolved_ipv6'] = (string) ($settings['resolved_ipv6'] ?? '');
$response['uptime'] = (float) ($settings['uptime'] ?? 0);
$response['last_successful_scan'] = (string) ($settings['last_successful_scan'] ?? '');
$response['last_updated'] = (string) ($settings['last_updated'] ?? '');

$url = $SETTINGS['explorer'] . '/hosts/' . $public_key;
$json_data = null;
try {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $apiResponse = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 200) {
        throw new Exception('Unexpected HTTP code: ' . $http_code);
    }
    $json_data = json_decode($apiResponse, true, 512, JSON_BIGINT_AS_STRING);
    curl_close($ch);
} catch (Exception $err) {
    $response['error'] = 'Error fetching data.';
}

$response['v2'] = (bool) ($json_data['v2'] ?? false);

$resolvedIpv4 = (string) ($settings['resolved_ipv4'] ?? '');
if ($resolvedIpv4 !== '') {
    $subnet = implode('.', array_slice(explode('.', $resolvedIpv4), 0, 3));
    $pattern = $subnet . '.%';
    $hostsinsubnetstmt = $mysqli->prepare('SELECT net_address, resolved_ipv4 FROM Hosts WHERE resolved_ipv4 LIKE ?');
    if ($hostsinsubnetstmt) {
        $hostsinsubnetstmt->bind_param('s', $pattern);
        $hostsinsubnetstmt->execute();
        $hostsinsubnetesult = $hostsinsubnetstmt->get_result();
        while ($host = mysqli_fetch_assoc($hostsinsubnetesult)) {
            $response['hosts_in_subnetv4'][] = [
                'net_address' => $host['net_address'],
                'resolved_ipv4' => $host['resolved_ipv4'],
            ];
        }
    }
}

$resolvedIpv6 = (string) ($settings['resolved_ipv6'] ?? '');
if ($resolvedIpv6 !== '') {
    $ipv6_parts = explode(':', $resolvedIpv6);
    $subnet_prefix = implode(':', array_slice($ipv6_parts, 0, 4));
    $ipv6pattern = $subnet_prefix . ':%';
    $hostsinsubnetstmt = $mysqli->prepare('SELECT net_address, resolved_ipv6 FROM Hosts WHERE resolved_ipv6 LIKE ?');
    if ($hostsinsubnetstmt) {
        $hostsinsubnetstmt->bind_param('s', $ipv6pattern);
        $hostsinsubnetstmt->execute();
        $hostsinsubnetesult = $hostsinsubnetstmt->get_result();
        while ($host = mysqli_fetch_assoc($hostsinsubnetesult)) {
            $response['hosts_in_subnetv6'][] = [
                'net_address' => $host['net_address'],
                'resolved_ipv6' => $host['resolved_ipv6'],
            ];
        }
    }
}

if (!$response['v2']) {
    $response['settings']['acceptingcontracts'] = $json_data['settings']['acceptingcontracts'] ?? 0;
    $response['settings']['baserpcprice'] = $json_data['settings']['baserpcprice'] ?? 0;
    $response['settings']['collateral'] = $json_data['settings']['collateral'] ?? 0;
    $response['settings']['contractprice'] = $json_data['settings']['contractprice'] ?? 0;
    $response['settings']['egressprice'] = $json_data['settings']['downloadbandwidthprice'] ?? 0;
    $response['settings']['ingressprice'] = $json_data['settings']['uploadbandwidthprice'] ?? 0;
    $response['settings']['ephemeralaccountexpiry'] = $json_data['settings']['ephemeralaccountexpiry'] ?? 0;
    $response['settings']['maxcollateral'] = $json_data['settings']['maxcollateral'] ?? 0;
    $response['settings']['maxdownloadbatchsize'] = $json_data['settings']['maxdownloadbatchsize'] ?? 0;
    $response['settings']['maxephemeralaccountbalance'] = $json_data['settings']['maxephemeralaccountbalance'] ?? 0;
    $response['settings']['maxrevisebatchsize'] = $json_data['settings']['maxrevisebatchsize'] ?? 0;
    $response['settings']['maxduration'] = $json_data['settings']['maxduration'] ?? 0;
    $response['settings']['freesectorprice'] = $json_data['settings']['sectoraccessprice'] ?? 0;
    $response['settings']['sectorsize'] = $json_data['settings']['sectorsize'] ?? 0;
    $response['settings']['siamuxport'] = $json_data['settings']['siamuxport'] ?? 0;
    $response['settings']['storageprice'] = $json_data['settings']['storageprice'] ?? 0;
    $response['settings']['windowsize'] = $json_data['settings']['windowsize'] ?? 0;
} else {
    $response['settings']['acceptingcontracts'] = $json_data['v2Settings']['acceptingContracts'] ?? 0;
    $response['settings']['collateral'] = $json_data['v2Settings']['prices']['collateral'] ?? 0;
    $response['settings']['contractprice'] = $json_data['v2Settings']['prices']['contractPrice'] ?? 0;
    $response['settings']['egressprice'] = $json_data['v2Settings']['prices']['egressPrice'] ?? 0;
    $response['settings']['ingressprice'] = $json_data['v2Settings']['prices']['ingressPrice'] ?? 0;
    $response['settings']['maxcollateral'] = $json_data['v2Settings']['maxCollateral'] ?? 0;
    $response['settings']['maxduration'] = $json_data['v2Settings']['maxContractDuration'] ?? 0;
    $response['settings']['storageprice'] = $json_data['v2Settings']['prices']['storagePrice'] ?? 0;
    $response['settings']['freesectorprice'] = $json_data['v2Settings']['prices']['freeSectorPrice'] ?? 0;
}

$benchmarkstmt = $mysqli->prepare(
    'SELECT avg(download_speed) AS download_speed,'
    . ' avg(upload_speed) AS upload_speed,'
    . ' avg(ttfb) AS ttfb'
    . ' FROM Benchmarks WHERE public_key = ?'
    . ' AND timestamp >= UTC_TIMESTAMP() - INTERVAL 7 DAY'
);
if ($benchmarkstmt) {
    $benchmarkstmt->bind_param('s', $public_key);
    $benchmarkstmt->execute();
    $benchmarkresult = $benchmarkstmt->get_result();
    $response['benchmark'] = mysqli_fetch_assoc($benchmarkresult) ?: [];
}

$dailydatastmt = $mysqli->prepare(
    'SELECT h.date, h.used_storage, h.total_storage, h.storage_price, h.upload_price, h.download_price, h.accepting_contracts, er.usd, er.eur '
    . 'FROM HostsDailyStats h '
    . "LEFT JOIN (SELECT DATE(timestamp) AS date, AVG(usd) AS usd, AVG(eur) AS eur FROM ExchangeRates WHERE currency_code = 'sc' GROUP BY DATE(timestamp)) er "
    . 'ON DATE(h.date) = er.date WHERE h.public_key = ? ORDER BY h.date'
);
if ($dailydatastmt) {
    $dailydatastmt->bind_param('s', $public_key);
    $dailydatastmt->execute();
    $dailydataresult = $dailydatastmt->get_result();
    while ($row = mysqli_fetch_assoc($dailydataresult)) {
        $usdRate = isset($row['usd']) ? (float) $row['usd'] : null;
        $eurRate = isset($row['eur']) ? (float) $row['eur'] : null;
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

$benchmarkscorestmt = $mysqli->prepare('SELECT * FROM BenchmarkScores WHERE public_key = ? ORDER BY date ASC');
if ($benchmarkscorestmt) {
    $benchmarkscorestmt->bind_param('s', $public_key);
    $benchmarkscorestmt->execute();
    $benchmarkscoreresult = $benchmarkscorestmt->get_result();
}
$response['node_scores']['global'] = [];
while (isset($benchmarkscoreresult) && ($benchmarkscore = mysqli_fetch_assoc($benchmarkscoreresult))) {
    $date = $benchmarkscore['date'];
    $node = $benchmarkscore['node'];
    $download_score = $benchmarkscore['download_score'];
    $upload_score = $benchmarkscore['upload_score'];
    $ttfb_score = $benchmarkscore['ttfb_score'];
    $total_score = ceil(($benchmarkscore['total_score']));

    if (!isset($response['node_scores'][$node])) {
        $response['node_scores'][$node] = [];
    }

    $stats = [];
    $stats['date'] = $date;
    $stats['download_score'] = (int) ceil($download_score);
    $stats['upload_score'] = (int) ceil($upload_score);
    $stats['ttfb_score'] = (int) ceil($ttfb_score);
    $stats['total_score'] = (int) $total_score;
    $response['node_scores'][$node][] = $stats;
}

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
    $response['segment_averages'] = mysqli_fetch_assoc($sectioncompareresult) ?: [];
}

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
    'storageprice',
] as $field) {
    if (isset($response['settings'][$field])) {
        $response['settings'][$field] = priceDict($response['settings'][$field], $usdRateNow, $eurRateNow);
    }
}

if (!empty($response['segment_averages'])) {
    foreach (['contractprice', 'storageprice', 'uploadprice', 'downloadprice', 'collateral'] as $f) {
        if (isset($response['segment_averages'][$f])) {
            $response['segment_averages'][$f] = priceDict($response['segment_averages'][$f], $usdRateNow, $eurRateNow);
        }
    }
}

Cache::setCache(json_encode($response), $cacheKey, 'hour');

echo json_encode($response);
