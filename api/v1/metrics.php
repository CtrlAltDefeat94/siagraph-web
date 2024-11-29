<?php
include_once "../../include/database.php";
include_once "../../include/redis.php";

header('Content-Type: application/json');

// Get all GET parameters and build a query string
$queryString = http_build_query($_GET);

// Combine the filename and query string
$combinedString = basename(__FILE__) . $queryString;

$metricsKey = empty(http_build_query(data: $_GET)) ? $metricsKey : md5($combinedString);
// Generate the MD5 hash
$cacheresult = getCache($metricsKey);
if ($cacheresult) {
    echo $cacheresult;
    die;
}
if (isset($_GET['start'])) {
    $start_date = $_GET['start'];
} else {
    $start_date="1970-01-01";
}

$query = "SELECT 
    date, 
    NULLIF(ROUND(total_storage, 0), 0) AS total_storage, 
    NULLIF(ROUND(utilized_storage, 0), 0) AS utilized_storage, 
    NULLIF(active_hosts, 0) AS active_hosts, 
    NULLIF(total_hosts, 0) AS total_hosts
FROM 
    NetworkStats 
WHERE date>='{$start_date}'
ORDER BY 
    date";
#$query = "SELECT date, ROUND(total_storage,0) AS total_storage, ROUND(remaining_storage,0) AS remaining_storage, active_hosts, total_hosts FROM NetworkStats ORDER BY date";

// Execute the query
$result = mysqli_query($mysqli, $query);
// $rows = getData($query, 'hour');

// Initialize the jsonlist array
$jsonlist = [];
/*
// Read and decode siastats_storage_converted.json
$siastats_content = file_get_contents('../../include/siastats_storage_stats.json');
if ($siastats_content === false) {
    echo "Failed to read siastats_storage_converted.json";
    exit();
}

$siastatsdata = json_decode($siastats_content, true);
if ($siastatsdata === null) {
    echo "Failed to decode siastats_storage_converted.json: " . json_last_error_msg();
    exit();
} else {
    foreach ($siastatsdata as $row) {
        $row['source'] = 'SiaStats';
        // Filter out keys with zero values
        $row = array_filter($row, function($value) {
            return $value !== 0;
        });
        $jsonlist[] = $row;
    }
}

// Read and decode blockchain_storage_converted.json
$blockchain_content = file_get_contents('../../include/blockchain_storage_calc.json');
if ($blockchain_content === false) {
    echo "Failed to read blockchain_storage_converted.json";
    exit();
}

$blockchaindata = json_decode($blockchain_content, true);
if ($blockchaindata === null) {
    echo "Failed to decode blockchain_storage_converted.json: " . json_last_error_msg();
    exit();
} else {
    foreach ($blockchaindata as $row) {
        $row['source'] = 'Blockchain';
        // Filter out keys with zero values
        $row = array_filter($row, function($value) {
            return $value !== 0;
        });
        $jsonlist[] = $row;
    }
}
*/
// Fetch all results as associative array
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
foreach ($rows as $row) {
    $date = new DateTime($row['date'], new DateTimeZone('UTC'));
    $dateiso = $date->format('Y-m-d\TH:i:s\Z');
    $formatted_date = $date->format('Y-m-d');
    $time_of_day = $date->format('H:i:s');
    $jsonlist_entry = array(
        'date' => $dateiso,
        'total_storage' => $row['total_storage'],
        #'utilized_storage' => ($row['total_storage']-  $row['remaining_storage']),
        'utilized_storage' => $row['utilized_storage'],
        'active_hosts' => $row['active_hosts'],
        'total_hosts' => $row['total_hosts'],
        #'source' => 'SiaGraph'
    );

    // Filter out keys with zero values
    $jsonlist_entry = array_filter($jsonlist_entry, function ($value) {
        return $value !== 0;
    });

    if ($time_of_day == '00:00:00') {
        $jsonlist[] = $jsonlist_entry;
    }
}

// Encode the grouped data as a JSON object
$jsonResult = json_encode($jsonlist);

// update cache
setCache($jsonResult, $metricsKey, 'day');

// Output the JSON object
echo $jsonResult;

