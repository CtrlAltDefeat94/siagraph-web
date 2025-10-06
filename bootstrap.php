<?php
$autoloadPath = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoloadPath)) {
require $autoloadPath;
}

// Load BCMath polyfill if the extension is missing
include_once __DIR__ . '/include/bcmath_polyfill.php';

// Initialize locale (detect, cache, and provide formatters)
// This sets a cookie 'locale' and exposes Siagraph\Utils\Locale helpers
if (class_exists(\Siagraph\Utils\Locale::class)) {
    \Siagraph\Utils\Locale::init();
}

// Composer handles autoloading; no fallback needed

// Ensure floats are encoded consistently
ini_set('serialize_precision', -1);

use Siagraph\Utils\Cache;
use Siagraph\Database\Database;

// Load configuration
include_once __DIR__ . '/include/config.php';

// MySQL database connection
Database::initialize($SETTINGS['database']);
$mysqli = Database::getConnection(); // backwards compatibility

// Redis configuration
$redisConfig = [
    'scheme' => 'tcp',
    'host' => $SETTINGS['redis_ip'] ?? '127.0.0.1',
    'port' => 6379,
];

Cache::setConfig($redisConfig);
