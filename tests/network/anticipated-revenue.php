<?php
// Uses a connection-local temporary table; persistent data is untouched.
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../include/config.php';
$db = $SETTINGS['database'];
$conn = new mysqli($db['servername'], $db['username'], $db['password'], $db['database']);
$conn->query('CREATE TEMPORARY TABLE HostRevenueForecast (public_key VARCHAR(72), unlock_date DATE, revenue_hastings DECIMAL(65,0) UNSIGNED, contract_count INT UNSIGNED, PRIMARY KEY (public_key, unlock_date))');
$today = new DateTimeImmutable(gmdate('Y-m-d'), new DateTimeZone('UTC'));
$future = $today->modify('first day of next month');
$stmt = $conn->prepare('INSERT INTO HostRevenueForecast VALUES (?, ?, ?, ?)');
foreach ([
    ['host-a', $today, '1000000000000000000000000', 1],
    ['host-b', $today, '2500000000000000000000000', 2],
    ['host-a', $future, '3000000000000000000000000', 4],
    ['host-a', $today->modify('-1 day'), '99000000000000000000000000', 99],
    ['host-b', $today->modify('-1 month'), '99000000000000000000000000', 99],
] as [$key, $day, $amount, $count]) {
    $date = $day->format('Y-m-d');
    $stmt->bind_param('sssi', $key, $date, $amount, $count);
    $stmt->execute();
}
function check(bool $ok, string $message): void { if (!$ok) throw new RuntimeException($message); }
$rows = \Siagraph\Services\NetworkAnticipatedRevenue::query($conn, $db['database']);
check(count($rows) === 2, 'Only current and future buckets');
check($rows[0]['unlock_date'] === $today->format('Y-m-01'), 'Current month label');
check((float)$rows[0]['revenue_sc'] === 3.5 && (int)$rows[0]['contract_count'] === 3, 'Sum across hosts, excluding dates before today even in the same month');
check($rows[1]['unlock_date'] === $future->format('Y-m-d') && (float)$rows[1]['revenue_sc'] === 3.0 && (int)$rows[1]['contract_count'] === 4, 'Reuse future forecast dates and counts');
$conn->query('DELETE FROM HostRevenueForecast');
check(\Siagraph\Services\NetworkAnticipatedRevenue::query($conn, $db['database']) === [], 'Empty forecast');
echo "Network forecast aggregation and daily cutoff checks passed.\n";
