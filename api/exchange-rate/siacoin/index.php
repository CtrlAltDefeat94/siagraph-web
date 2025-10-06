<?php
include_once "../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: text/plain');

// Determine currency from PATH_INFO or query param
$currency = '';
if (!empty($_SERVER['PATH_INFO'])) {
    $currency = ltrim($_SERVER['PATH_INFO'], '/');
} elseif (isset($_GET['currency'])) {
    $currency = $_GET['currency'];
}

$currency = strtolower(trim($currency));
if ($currency === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Currency not specified']);
    exit;
}

if ($currency === 'sc') {
    echo '1';
    exit;
}

$cached = Cache::getCache(Cache::COIN_PRICE_KEY);
if (!$cached) {
    http_response_code(500);
    echo json_encode(['error' => 'Exchange rate unavailable']);
    exit;
}

$data = json_decode($cached, true, 512, JSON_BIGINT_AS_STRING);
if (!isset($data[$currency])) {
    http_response_code(404);
    echo json_encode(['error' => 'Unsupported currency']);
    exit;
}

echo $data[$currency];
