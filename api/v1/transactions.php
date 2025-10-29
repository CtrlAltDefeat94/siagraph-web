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
    'transactions_%s_%s.csv',
    substr($address, 0, 12),
    $sinceDate->format('Ymd')
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

while ($row = $result->fetch_assoc()) {
    $row['total_siacoins'] = $row['total_siacoins'] !== null ? (float)$row['total_siacoins'] : null;
    $row['total_siafunds'] = $row['total_siafunds'] !== null ? (float)$row['total_siafunds'] : null;
    $timestampFormatted = $row['timestamp'];

    if (!empty($row['timestamp'])) {
        try {
            $timestamp = new \DateTime($row['timestamp'], new \DateTimeZone('UTC'));
            $timestampFormatted = $timestamp->format('Y-m-d\TH:i:s\Z');
        } catch (\Exception $e) {
            // Leave timestamp as-is if it cannot be parsed
        }
    }

    $transactions[] = [
        'timestamp' => $timestampFormatted,
        'transaction_id' => $row['transaction_id'],
        'transaction_type' => $row['transaction_type'],
        'total_siacoins' => $row['total_siacoins'],
        'total_siafunds' => $row['total_siafunds'],
    ];
}
$stmt->close();
$rawConnection->close();

$payload = '';

if ($format === 'csv') {
    $memory = fopen('php://temp', 'r+');
    fputcsv($memory, ['timestamp', 'transaction_id', 'transaction_type', 'total_siacoins', 'total_siafunds']);
    foreach ($transactions as $row) {
        fputcsv($memory, [
            $row['timestamp'],
            $row['transaction_id'],
            $row['transaction_type'] ?? '',
            $row['total_siacoins'],
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
