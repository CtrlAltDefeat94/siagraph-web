<?php
include_once "../../include/database.php";
include_once "../../include/redis.php";


header('Content-Type: application/json');

// Get all GET parameters and build a query string
$queryString = http_build_query($_GET);

// Combine the filename and query string
$combinedString = basename(__FILE__) . $queryString;

// Generate the MD5 hash
$cacheKey = md5($combinedString);
$cacheresult = getCache($cacheKey);
if ($cacheresult) {
    echo $cacheresult;
    die;
}




$query = "SELECT DATE_FORMAT(date, '%Y-%m') AS period,max(used_storage) FROM HostsDailyStats GROUP BY period;";

// Execute the query
$result = mysqli_query($mysqli, $query);

// Check if the query was successful
if (!$result) {
    echo "Error executing query: " . mysqli_error($mysqli);
    exit();
}

// Initialize the jsonlist array
$jsonlist = [];

// Fetch all results as associative array
$rows = mysqli_fetch_all($result, MYSQLI_ASSOC);
foreach ($rows as $row) {
    $date = new DateTime($row['period']);
    $formatted_date = $date->format('Y-m-01');

    $jsonlist_entry = array(
        'date' => $formatted_date,
        'max_used_storage' => $row['max(used_storage)']
    );

    // Filter out keys with zero values
    $jsonlist_entry = array_filter($jsonlist_entry, function($value) {
        return $value !== 0;
    });
 $jsonlist[] = $jsonlist_entry;
    
}

// Encode the grouped data as a JSON object
$jsonResult = json_encode($jsonlist);

// update cache
setCache($jsonResult, $cacheKey, 'day');

// Output the JSON object
echo $jsonResult;

