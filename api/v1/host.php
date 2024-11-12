<?php

include_once "../../include/database.php";
include_once "../../include/utils.php";
include_once "../../include/redis.php";


// Fetch host ID from URL
if (isset($_GET['id'])) {
    $host_id = $_GET['id'];
    $query = "SELECT * FROM Hosts WHERE host_id = $host_id";
} else if (isset($_GET['public_key'])) {
    $public_key = $_GET['public_key'];
    $query = "SELECT * FROM Hosts WHERE public_key = '$public_key'";
}

header('Content-Type: application/json');
$cacheKey = 'host' . http_build_query($_GET);
$cacheresult = getCache($cacheKey);
if ($cacheresult) {
    echo $cacheresult;
    die;
}
$response = array(); // Initialize an array to hold the response data

if (isset($query)) {
    // Perform the query
    $result = mysqli_query($mysqli, $query);
    $settings = mysqli_fetch_assoc($result);
    
    if ($settings) {
        $public_key = $settings['public_key'];
        $host_id = $settings['host_id'];

        // Fetch benchmark data
        $benchmarkquery = "SELECT
            avg(download_speed) AS download_speed,
            avg(upload_speed) AS upload_speed,
            avg(ttfb) AS ttfb
            FROM Benchmarks WHERE public_key= '$public_key' AND timestamp >= UTC_TIMESTAMP() - INTERVAL 7 DAY";
        $benchmarkresult = mysqli_query($mysqli, $benchmarkquery);
        $benchmark = mysqli_fetch_assoc($benchmarkresult);

        // Fetch daily data
        $dailydataquery = "SELECT date, used_storage, total_storage, storage_price, upload_price, download_price, accepting_contracts FROM HostsDailyStats WHERE public_key= '$public_key' ORDER BY date";
        $dailydataresult = mysqli_query($mysqli, $dailydataquery);
        $dailydata = array();
        while ($row = mysqli_fetch_assoc($dailydataresult)) {
            $dailydata[] = $row;
        }

        // Fetch benchmark scores
        $benchmarkscorequery = "SELECT * FROM BenchmarkScores WHERE public_key= '$public_key' ORDER BY date ASC";
        $benchmarkscoreresult = mysqli_query($mysqli, $benchmarkscorequery);
        $node_scores = array();
        $node_scores['global'] = array();
        while ($benchmarkscore = mysqli_fetch_assoc($benchmarkscoreresult)) {
            $date = $benchmarkscore['date'];
            $node = $benchmarkscore['node'];
            $download_score = $benchmarkscore['download_score'];
            $upload_score = $benchmarkscore['upload_score'];
            $ttfb_score = $benchmarkscore['ttfb_score'];
            $total_score = round($benchmarkscore['total_score']);
            
            if (!isset($node_scores[$node])) {
                $node_scores[$node] = array();
            }
            $stats = array();
            $stats['date'] = $date;
            $stats['download_score'] = (int) $download_score;
            $stats['upload_score'] = (int) $upload_score;
            $stats['ttfb_score'] = (int) $ttfb_score;
            $stats['total_score'] = (int) $total_score;
            $node_scores[$node][] = $stats;

        }

        // Calculate global total score
        $globaltotalscore = ($node_scores['Global']['total_score'] ?? 0);
        $sectioncomparequery = "SELECT
            AVG(contract_price) AS contract_price,
            AVG(storage_price) AS storage_price,
            AVG(upload_price) AS upload_price,
            AVG(download_price) AS download_price,
            AVG(collateral) AS collateral,
            AVG(used_storage) AS used_storage
        FROM Hosts
        WHERE public_key IN (
            SELECT public_key
            FROM BenchmarkScores
            WHERE node = 'Global' AND total_score = $globaltotalscore);";
        $sectioncompareresult = mysqli_query($mysqli, $sectioncomparequery);
        $sectioncompare = mysqli_fetch_assoc($sectioncompareresult);

        // Assemble the response
        $response['settings'] = $settings;
        $response['benchmark'] = $benchmark;
        $response['dailydata'] = $dailydata;
        $response['node_scores'] = $node_scores;
        $response['segment_averages'] = $sectioncompare;
    } else {
        $response['error'] = "No settings found for the given host ID or public key.";
    }
} else {
    $response['error'] = "Host ID or public key not provided in the URL.";
}


// update cache
setCache(json_encode($response), $cacheKey, 'hour');


echo json_encode($response);