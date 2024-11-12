<?php
ini_set('serialize_precision', -1);
require __DIR__ . '/../vendor/autoload.php'; // Ensure Predis is installed via Composer

// Function to convert scientific notation to normal integer
function convertScientificToInteger($value) {
    if (strpos($value, 'e') !== false || strpos($value, 'E') !== false) {
        return (int) number_format($value, 0, '', '');
    }
    return (int) $value;
}

function formatBytes($bytes) {
    // Handle negative bytes
    $isNegative = $bytes < 0;
    $bytes = abs($bytes);

    if ($bytes === 0) return '0 Bytes'; // Special case for 0 bytes

    $units = ['Bytes', 'KB', 'MB', 'GB', 'TB', 'PB', 'EB', 'ZB', 'YB'];
    $unitIndex = 0;

    // Convert bytes to the largest unit
    while ($bytes >= 1000 && $unitIndex < count($units) - 1) {
        $bytes /= 1000;
        $unitIndex++;
    }

    // Round to 3 decimal places
    $formatted = number_format($bytes, 3) . ' ' . $units[$unitIndex];

    // Add the negative sign back if the original number was negative
    return $isNegative ? "-$formatted" : $formatted;
}

function prependPlusIfNeeded($string) {
    // Check if the first character is not a minus
    if (substr($string, 0, 1) !== '-') {
        // Prepend a plus sign and return the new string
        return '+' . $string;
    }
    // Return the original string if it starts with a minus
    return $string;
}


function getData($query = false, $data = false, $cacheLifetimeOption = 'hour')
{
    // Create a hash of the query to use as the cache key
    $cacheKey = 'query_cache_' . md5($query);

    // Calculate the cache lifetime
    $cacheLifetime = calculateCacheLifetime($cacheLifetimeOption);

    // Connect to Redis
    $redis = new Predis\Client([
        'scheme' => 'tcp',
        'host' => '127.0.0.1', // Change this to your Redis server address if needed
        'port' => 6379,        // Change this to your Redis server port if needed
    ]);

    // Check if the data is already cached in Redis
    $cachedData = $redis->get($cacheKey);
    if ($cachedData) {
        // If cached data exists, return it
        return json_decode($cachedData, true);
    } else {
        // If no cached data, fetch from database
        global $mysqli;
        $result = mysqli_query($mysqli, $query);

        // Check if the query was successful
        if (!$result) {
            die("Query failed: " . mysqli_error($mysqli));
        }

        // Ensure $result is a mysqli_result object
        if (!($result instanceof mysqli_result)) {
            die("Query did not return a mysqli_result object. Received: " . gettype($result));
        }

        // Fetch the result rows
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        // Encode the data as a JSON object and store in Redis cache
        $jsonData = json_encode($data);
        $redis->setex($cacheKey, $cacheLifetime, $jsonData);

        // Return the fresh data
        return $data;
    }
}