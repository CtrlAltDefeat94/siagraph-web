<?php
require __DIR__ . '/../vendor/autoload.php'; // Ensure Predis is installed via Composer

### Key names start ###

$peerListKey = "peerlist";
$coinPriceKey = "coinprice";
$recentStatsKey = 'compare_metrics';
$revenueMonthlyKey = 'revenue_monthly';
$metricsKey = 'metrics';
$hostKey = 'host';

### Key names end ###

$redisConfig = [
    'scheme' => 'tcp',
    'host' => '127.0.0.1', // Change this to your Redis server address if needed
    'port' => 6379,        // Change this to your Redis server port if needed
];

function calculateCacheLifetime($option)
{
    $currentTime = new DateTime("now", new DateTimeZone("UTC"));

    if ($option === 'day') {
        // Calculate the number of seconds until the next minute after UTC midnight
        $midnight = new DateTime("tomorrow midnight", new DateTimeZone("UTC"));
        $midnight->add(new DateInterval('PT1M')); // Add one minute to the midnight
        $interval = $midnight->getTimestamp() - $currentTime->getTimestamp();
    } elseif ($option === 'hour') {
        // Calculate the number of seconds until the next minute after the full hour
        $nextHour = new DateTime("now", new DateTimeZone("UTC"));
        $nextHour->setTime($nextHour->format('H'), 0, 0); // Set to the beginning of the current hour
        $nextHour->add(new DateInterval('PT1H1M')); // Add one hour and one minute
        $interval = $nextHour->getTimestamp() - $currentTime->getTimestamp();
    } else {
        throw new InvalidArgumentException("Invalid cache lifetime option. Use 'day' or 'hour'.");
    }
    return $interval;
}


function getCache($cacheKey)
{
    global $redisConfig;
    try {
        $redis = new Predis\Client($redisConfig);

        // Retrieve the data from Redis
        $cachedData = $redis->get($cacheKey);

        if ($cachedData === null) {
            return null;
        }
        return $cachedData;
    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
        return null;
    }
}


function setCache($data, $cacheKey, $cacheLifetimeOption = 'hour')
{
    global $redisConfig;
    $cacheLifetime = calculateCacheLifetime($cacheLifetimeOption);

    // Connect to Redis
    try {
        $redis = new Predis\Client($redisConfig);

        // Check Redis connection
        if (!$redis->ping()) {
            throw new Exception('Redis connection failed.');
        }

        // Store the data in Redis
        $result = $redis->setex($cacheKey, $cacheLifetime, $data);

        if (!$result) {
            throw new Exception('Failed to set cache.');
        }


    } catch (Exception $e) {
        error_log("Error: " . $e->getMessage());
    }
}

