<?php
include_once "../../bootstrap.php";

use Siagraph\Utils\Cache;

$format = isset($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';
if ($format === '') {
    $format = 'json';
}

if (!in_array($format, ['json', 'csv'], true)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported format. Use json or csv.']);
    exit;
}

$address = isset($_GET['address']) ? trim($_GET['address']) : '';
$dateParam = isset($_GET['date']) ? trim($_GET['date']) : '';
$currencyParam = isset($_GET['currency']) ? strtolower(trim($_GET['currency'])) : 'usd';

$currencyMap = [
    'usd' => 'usd',
    'eur' => 'eur',
    'cad' => 'cad',
    'cny' => 'cny',
    'gbp' => 'gbp',
    'jpy' => 'jpy',
    'rub' => 'rub',
];

if ($currencyParam === '') {
    $currencyParam = 'usd';
}

if (!array_key_exists($currencyParam, $currencyMap)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported currency. Choose one of: ' . implode(', ', array_keys($currencyMap)) . '.']);
    exit;
}

$currencyColumn = $currencyMap[$currencyParam];
$currencyCode = strtoupper($currencyParam);

if (!function_exists('format_decimal_value')) {
    /**
     * Format numeric values without scientific notation.
     */
    function format_decimal_value(?float $value, int $precision): ?string
    {
        if ($value === null) {
            return null;
        }
        if (!is_finite($value)) {
            return null;
        }
        $precision = max(0, $precision);
        $formatted = number_format($value, $precision, '.', '');
        // Trim trailing zeros and dot if necessary
        $formatted = rtrim(rtrim($formatted, '0'), '.');
        if ($formatted === '' || $formatted[0] === '.') {
            $formatted = '0' . $formatted;
        }
        if ($formatted === '-0') {
            $formatted = '0';
        }
        return $formatted;
    }
}

if ($address === '' || $dateParam === '') {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters. Provide both address and date.']);
    exit;
}

if (!preg_match('/^[0-9a-f]{76}$/i', $address)) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid address format. Expecting a 76-character hexadecimal string.']);
    exit;
}

try {
    $sinceDate = new \DateTime($dateParam, new \DateTimeZone('UTC'));
} catch (\Exception $e) {
    header('Content-Type: application/json');
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format. Use an ISO 8601 value or YYYY-MM-DD.']);
    exit;
}

$since = $sinceDate->format('Y-m-d H:i:s');
$csvFilename = sprintf(
    'transactions_%s_%s_%s.csv',
    substr($address, 0, 12),
    $sinceDate->format('Ymd'),
    $currencyCode
);

$cacheKey = md5(basename(__FILE__) . http_build_query($_GET));
$cached = Cache::getCache($cacheKey);
if ($cached) {
    if ($format === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $csvFilename . '"');
    } else {
        header('Content-Type: application/json');
    }
    echo $cached;
    exit;
}

try {
    $mainConnection = \Siagraph\Database\Database::getConnection();
} catch (\Exception $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to access primary database.', 'details' => $e->getMessage()]);
    exit;
}

$scCode = 'sc';
$rateTimeline = [];
$tzUtc = new \DateTimeZone('UTC');

$priorQuery = "
    SELECT timestamp, {$currencyColumn} AS rate
    FROM ExchangeRates
    WHERE currency_code = ?
      AND timestamp <= ?
    ORDER BY timestamp DESC
    LIMIT 1
";
$priorStmt = $mainConnection->prepare($priorQuery);
if (!$priorStmt) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare exchange rate query.', 'details' => $mainConnection->error]);
    exit;
}
$priorStmt->bind_param('ss', $scCode, $since);
if (!$priorStmt->execute()) {
    $rateError = $priorStmt->error;
    $priorStmt->close();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to execute exchange rate query.', 'details' => $rateError]);
    exit;
}
$priorResult = $priorStmt->get_result();
$priorRow = $priorResult ? $priorResult->fetch_assoc() : null;
$priorStmt->close();

if ($priorRow && isset($priorRow['rate']) && $priorRow['rate'] !== null) {
    try {
        $rateTime = new \DateTime($priorRow['timestamp'], $tzUtc);
        $rateTimeline[] = [
            'timestamp' => $rateTime,
            'rate' => (float)$priorRow['rate'],
        ];
    } catch (\Exception $e) {
        // ignore invalid timestamp
    }
}

$rangeQuery = "
    SELECT timestamp, {$currencyColumn} AS rate
    FROM ExchangeRates
    WHERE currency_code = ?
      AND timestamp > ?
    ORDER BY timestamp ASC
";
$rangeStmt = $mainConnection->prepare($rangeQuery);
if (!$rangeStmt) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare exchange rate range query.', 'details' => $mainConnection->error]);
    exit;
}
$rangeStmt->bind_param('ss', $scCode, $since);
if (!$rangeStmt->execute()) {
    $rateError = $rangeStmt->error;
    $rangeStmt->close();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to execute exchange rate range query.', 'details' => $rateError]);
    exit;
}
$rangeResult = $rangeStmt->get_result();
while ($rangeRow = $rangeResult->fetch_assoc()) {
    if (!isset($rangeRow['rate']) || $rangeRow['rate'] === null) {
        continue;
    }
    try {
        $rateTime = new \DateTime($rangeRow['timestamp'], $tzUtc);
    } catch (\Exception $e) {
        continue;
    }

    $last = end($rateTimeline);
    if ($last && $last['timestamp']->getTimestamp() === $rateTime->getTimestamp()) {
        continue;
    }

    $rateTimeline[] = [
        'timestamp' => $rateTime,
        'rate' => (float)$rangeRow['rate'],
    ];
}
$rangeStmt->close();

if (!$rateTimeline) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Fiat exchange rate unavailable for the requested currency.']);
    exit;
}

$dbConfig = $SETTINGS['database'] ?? [];
if (!isset($dbConfig['raw_database'])) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Raw database configuration missing.']);
    exit;
}

$rawConnection = new \mysqli(
    $dbConfig['servername'],
    $dbConfig['username'],
    $dbConfig['password'],
    $dbConfig['raw_database']
);

if ($rawConnection->connect_errno) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to connect to raw database.', 'details' => $rawConnection->connect_error]);
    exit;
}

$sql = "
    SELECT
        b.timestamp,
        t.transaction_id,
        t.transaction_type,
        SUM(t.siacoins) / 1e24 AS total_siacoins,
        SUM(t.siafunds) AS total_siafunds
    FROM Transactions t
    JOIN BlockTime b
        ON t.block_height = b.block_height
    WHERE
        t.address = ?
        AND b.timestamp >= ?
    GROUP BY
        t.transaction_id,
        b.timestamp,
        t.transaction_type
    ORDER BY
        b.timestamp
";

$stmt = $rawConnection->prepare($sql);
if (!$stmt) {
    header('Content-Type: application/json');
    $rawConnection->close();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to prepare query.', 'details' => $rawConnection->error]);
    exit;
}

$stmt->bind_param('ss', $address, $since);
if (!$stmt->execute()) {
    $error = $stmt->error;
    $stmt->close();
    $rawConnection->close();
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Failed to execute query.', 'details' => $error]);
    exit;
}

$result = $stmt->get_result();
$transactions = [];
$currencyLower = strtolower($currencyCode);
$fiatFieldName = 'total_' . $currencyLower;
$rateFieldName = $currencyLower . '_exchange_rate';
$rateCount = count($rateTimeline);
$rateIndex = 0;
$missingRate = false;

while ($row = $result->fetch_assoc()) {
$siacoinsValue = $row['total_siacoins'] !== null ? (float)$row['total_siacoins'] : null;
$siafundsValue = $row['total_siafunds'] !== null ? (float)$row['total_siafunds'] : null;
    $timestampFormatted = $row['timestamp'];
    $txDateTime = null;

    if (!empty($row['timestamp'])) {
        try {
            $txDateTime = new \DateTime($row['timestamp'], $tzUtc);
            $timestampFormatted = $txDateTime->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            $txDateTime = null;
        }
    }

    if (!$txDateTime) {
        $missingRate = true;
        break;
    }

    if ($txDateTime < $rateTimeline[0]['timestamp']) {
        $missingRate = true;
        break;
    }

    while (($rateIndex + 1) < $rateCount && $rateTimeline[$rateIndex + 1]['timestamp'] <= $txDateTime) {
        $rateIndex++;
    }

    $exchangeRate = $rateTimeline[$rateIndex]['rate'];
    $totalValueFiat = $siacoinsValue !== null ? $siacoinsValue * $exchangeRate : null;

    $siacoinsFormatted = format_decimal_value($siacoinsValue, 24);
    $fiatRateFormatted = format_decimal_value($exchangeRate, 12);
    $fiatValueFormatted = format_decimal_value($totalValueFiat, 12);
    $siafundsFormatted = format_decimal_value($siafundsValue, 0);

    $transactions[] = [
        'timestamp' => $timestampFormatted,
        'transaction_id' => $row['transaction_id'],
        'transaction_type' => $row['transaction_type'],
        'total_siacoins' => $siacoinsFormatted,
        $rateFieldName => $fiatRateFormatted,
        $fiatFieldName => $fiatValueFormatted,
        'total_siafunds' => $siafundsFormatted,
    ];
}
$stmt->close();
$rawConnection->close();

if ($missingRate) {
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'No exchange rate found on or before transaction timestamp.']);
    exit;
}

$payload = '';

if ($format === 'csv') {
    $memory = fopen('php://temp', 'r+');
    fputcsv($memory, [
        'timestamp',
        'transaction_id',
        'transaction_type',
        'total_siacoins',
        $rateFieldName,
        $fiatFieldName,
        'total_siafunds',
    ]);
    foreach ($transactions as $row) {
        fputcsv($memory, [
            $row['timestamp'],
            $row['transaction_id'],
            $row['transaction_type'] ?? '',
            $row['total_siacoins'],
            $row[$rateFieldName],
            $row[$fiatFieldName],
            $row['total_siafunds'],
        ]);
    }
    rewind($memory);
    $payload = stream_get_contents($memory);
    fclose($memory);

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $csvFilename . '"');
} else {
    $payload = json_encode($transactions);
    header('Content-Type: application/json');
}

Cache::setCache($payload, $cacheKey, 'hour');

echo $payload;
