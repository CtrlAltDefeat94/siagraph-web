<?php
require_once __DIR__ . '/../../bootstrap.php';

header('Content-Type: application/json');
header('Cache-Control: public, max-age=60');

// Return the latest recorded rate with its actual observation time.
try {
    $result = $mysqli->query("SELECT timestamp, btc, cad, cny, eth, eur, gbp, jpy, rub, usd FROM ExchangeRates WHERE currency_code = 'sc' ORDER BY timestamp DESC LIMIT 1");
    $row = $result ? $result->fetch_assoc() : null;
    if (!$row) {
        throw new RuntimeException('No exchange rate available');
    }
    $data = ['date' => (new DateTimeImmutable($row['timestamp'], new DateTimeZone('UTC')))->format('Y-m-d\TH:i:s\Z')];
    foreach (['btc', 'cad', 'cny', 'eth', 'eur', 'gbp', 'jpy', 'rub', 'usd'] as $currency) {
        $value = $row[$currency] ?? null;
        $data[$currency] = is_numeric($value) && (float) $value > 0 ? (float) $value : null;
    }
    echo json_encode($data);
} catch (Throwable $error) {
    http_response_code(503);
    header('Cache-Control: no-store');
    echo json_encode(['error' => 'Latest exchange rate unavailable']);
}
