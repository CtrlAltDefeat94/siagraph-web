<?php
include_once "../../include/database.php";
include_once "../../include/redis.php";

header('Content-Type: application/json');

// Define your SQL query
$query = "SELECT address, version, network, last_scanned FROM Peers WHERE last_synced > DATE_SUB(UTC_DATE(), INTERVAL 3 DAY)";

$groupedData = [];

// Generate the MD5 hash
$cacheresult = getCache($peerListKey);
if ($cacheresult) {
    // Convert the JSON string back to an associative array
    $rows = json_decode($cacheresult, true);

} else {
    // Execute the query
    $result = mysqli_query($mysqli, $query);

    // Fetch all rows from the result
    $rows = [];
    while ($row = mysqli_fetch_assoc($result)) {
        $rows[] = $row;
    }

    // Convert the rows array into a JSON string
    $jsonResult = json_encode($rows);
    setCache($jsonResult, $peerListKey, 'hour');
}
shuffle($rows);
// Fetch the result rows and group them by the 'network' column
foreach ($rows as $row) {
    $network = $row['network'];

    if (!isset($groupedData[$network])) {
        $groupedData[$network] = [];
    }

    $groupedData[$network][] = $row;
}

// Encode the grouped data as a JSON object
$jsonResult = json_encode($groupedData);
// Output the JSON object
echo $jsonResult;
