<?php
include_once "../../../include/database.php";
include_once "../../../include/redis.php";


header('Content-Type: application/json');

// Get all GET parameters and build a query string
$queryString = http_build_query($_GET);

// Combine the filename and query string
$combinedString = basename(__FILE__) . $queryString;
// Generate the MD5 hash
$revenueMonthlyKey = empty(http_build_query(data: $_GET)) ? $revenueMonthlyKey : md5($combinedString);
$cacheresult = getCache($revenueMonthlyKey);
if ($cacheresult) {
    echo $cacheresult;
    die;
}


// Fetch current date and calculate the end date
date_default_timezone_set('UTC');
$start_date = "2015-12-01T00:00:00Z";
$end_date = date('Y-m-t\T00:00:00\Z', strtotime('first day of next month'));

// Build the URL
$url = "https://api.siascan.com/metrics/revenue/monthly?start=$start_date&end=$end_date";
// Fetch data
$json_data = null;
try {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    if (curl_errno($ch)) {
        throw new Exception(curl_error($ch));
    }
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($http_code != 200) {
        throw new Exception("Unexpected HTTP code: $http_code");
    }
    $json_data = json_decode($response, true);
    curl_close($ch);
} catch (Exception $err) {
    echo "Error fetching data: " . $err->getMessage() . "\n";
}
if ($json_data) {
    $result = [];
    $prev_data = null;

    foreach ($json_data as $entry) {
        $timestamp = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $entry["timestamp"]);
        if ($timestamp > new DateTime()) {
            continue; // Skip future dates
        }
        $formatted_timestamp = $timestamp->format('Y-m');
        $current_revenue = $entry["revenue"];

        if ($prev_data) {
            $difference = [];
            foreach ($prev_data["revenue"] as $currency => $prev_revenue_value) {
                if (floatval($prev_revenue_value) <= 0.0) {
                    $difference[$currency] = 0.0;
                } else {
                    if (in_array($currency, ['usd', 'eur'])) {
                        $difference[$currency] = number_format(floatval($current_revenue[$currency]) - floatval($prev_revenue_value), 2, '.', '');
                    } elseif ($currency === 'sc') {
                        $difference[$currency] = intval(floatval($current_revenue[$currency]) - floatval($prev_revenue_value)) / (10 ** 24);
                    } else {
                        $difference[$currency] = number_format(floatval($current_revenue[$currency]) - floatval($prev_revenue_value), 8, '.', '');
                    }
                }
            }

            if (!empty($difference)) {
                // Add the 'date' field to the difference data
                $difference['date'] = $formatted_timestamp;

                // Append the modified difference data to the result array
                $result[] = $difference;
            }
        }

        $prev_data = $entry;
    }
    $jsonResult = json_encode($result, JSON_PRETTY_PRINT);
    setCache($jsonResult, $revenueMonthlyKey, 'day');
    // Print result

    echo $jsonResult;
}
