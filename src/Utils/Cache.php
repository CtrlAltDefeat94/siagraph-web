<?php
namespace Siagraph\Utils;

use Predis\Client;
use DateTime;
use DateTimeZone;
use DateInterval;
use InvalidArgumentException;
use Exception;
use Siagraph\Database\Database;

class Cache
{
    public const PEER_LIST_KEY = 'peerlist';
    public const COIN_PRICE_KEY = 'coinprice';
    public const RECENT_STATS_KEY = 'compare_metrics';
    public const REVENUE_MONTHLY_KEY = 'revenue_monthly';
    public const METRICS_KEY = 'metrics';
    public const HOST_KEY = 'host';
    public const EXPLORER_METRICS_KEY = 'explorer_metrics';

    /** @var array */
    protected static $redisConfig = [];

    public static function setConfig(array $config): void
    {
        self::$redisConfig = $config;
    }

    public static function calculateCacheLifetime(string $option): int
    {
        $currentTime = new DateTime('now', new DateTimeZone('UTC'));

        if ($option === 'day') {
            $midnight = new DateTime('tomorrow midnight', new DateTimeZone('UTC'));
            $midnight->add(new DateInterval('PT1M'));
            $interval = $midnight->getTimestamp() - $currentTime->getTimestamp();
        } elseif ($option === 'hour') {
            $nextHour = new DateTime('now', new DateTimeZone('UTC'));
            $nextHour->setTime($nextHour->format('H'), 0, 0);
            $nextHour->add(new DateInterval('PT1H1M'));
            $interval = $nextHour->getTimestamp() - $currentTime->getTimestamp();
        } else {
            throw new InvalidArgumentException("Invalid cache lifetime option. Use 'day' or 'hour'.");
        }
        return $interval;
    }

    public static function getCache(string $cacheKey): ?string
    {
        try {
            $redis = new Client(self::$redisConfig);
            return $redis->get($cacheKey) ?: null;
        } catch (Exception $e) {
            error_log('Error: ' . $e->getMessage());
            return null;
        }
    }

    public static function setCache(string $data, string $cacheKey, string $cacheLifetimeOption = 'hour'): void
    {
        $cacheLifetime = self::calculateCacheLifetime($cacheLifetimeOption);
        try {
            $redis = new Client(self::$redisConfig);
            if (!$redis->ping()) {
                throw new Exception('Redis connection failed.');
            }
            $redis->setex($cacheKey, $cacheLifetime, $data);
        } catch (Exception $e) {
            error_log('Error: ' . $e->getMessage());
        }
    }

    public static function getData(string $query, $data = null, string $cacheLifetimeOption = 'hour')
    {
        $cacheKey = 'query_cache_' . md5($query);
        $cacheLifetime = self::calculateCacheLifetime($cacheLifetimeOption);

        try {
            $redis = new Client(self::$redisConfig);
            $cachedData = $redis->get($cacheKey);
            if ($cachedData) {
                return json_decode($cachedData, true);
            }
        } catch (Exception $e) {
            // continue without cache
        }

        $result = \Siagraph\Database\Database::query($query);
        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }
        $jsonData = json_encode($data);
        try {
            $redis = new Client(self::$redisConfig);
            $redis->setex($cacheKey, $cacheLifetime, $jsonData);
        } catch (Exception $e) {
            // ignore cache errors
        }
        return $data;
    }
}
