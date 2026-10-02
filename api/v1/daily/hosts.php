<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

function daily_hosts_error(int $status, string $message, array $details = []): void
{
    http_response_code($status);
    echo json_encode([
        'error' => $message,
        'details' => (object) $details,
    ]);
    exit;
}

function daily_hosts_date(?string $value, string $field): ?string
{
    if ($value === null || trim($value) === '') {
        return null;
    }

    $value = trim($value);
    $date = DateTime::createFromFormat('Y-m-d', $value, new DateTimeZone('UTC'));
    $errors = DateTime::getLastErrors();
    if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
        daily_hosts_error(400, 'Invalid date parameter', [
            'field' => $field,
            'expected' => 'YYYY-MM-DD',
        ]);
    }

    return $date->format('Y-m-d');
}

function daily_hosts_bind_params(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '') {
        return;
    }

    $bind = [$types];
    foreach ($params as $key => &$value) {
        $bind[] = &$value;
    }
    unset($value);

    call_user_func_array([$stmt, 'bind_param'], $bind);
}

$queryString = http_build_query($_GET);
$cacheKey = md5(basename(__FILE__) . $queryString);
if ($cached = Cache::getCache($cacheKey)) {
    echo $cached;
    exit;
}

$date = daily_hosts_date($_GET['date'] ?? null, 'date');
$start = daily_hosts_date($_GET['start'] ?? null, 'start');
$end = daily_hosts_date($_GET['end'] ?? null, 'end');

if ($date !== null && ($start !== null || $end !== null)) {
    daily_hosts_error(400, 'Use either date or start/end, not both');
}

if ($start !== null && $end !== null && strcmp($start, $end) > 0) {
    daily_hosts_error(400, 'Start date must be before or equal to end date');
}

$publicKey = isset($_GET['public_key']) ? trim((string) $_GET['public_key']) : null;
if ($publicKey === '') {
    $publicKey = null;
}

$sortMap = [
    'date' => 'h.date',
    'public_key' => 'h.public_key',
    'used_storage' => 'h.used_storage',
    'total_storage' => 'h.total_storage',
    'storage_price' => 'h.storage_price',
    'upload_price' => 'h.upload_price',
    'download_price' => 'h.download_price',
    'accepting_contracts' => 'h.accepting_contracts',
    'contract_price' => 'h.contract_price',
    'locked_collateral' => 'h.locked_collateral',
    'risked_collateral' => 'h.risked_collateral',
    'revenue' => 'h.revenue',
    'burned_funds' => 'h.burned_funds',
    'active_contracts' => 'h.active_contracts',
    'successful_contracts' => 'h.successful_contracts',
    'renewed_contracts' => 'h.renewed_contracts',
    'failed_contracts' => 'h.failed_contracts',
];

$sortValue = isset($_GET['sort']) ? trim((string) $_GET['sort']) : '';
$sortColumn = $sortMap[$sortValue] ?? null;
$orderValue = strtolower(trim((string) ($_GET['order'] ?? '')));
$sortOrder = $orderValue === 'desc' ? 'DESC' : 'ASC';

$page = isset($_GET['page']) && is_numeric($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$limit = isset($_GET['limit']) && is_numeric($_GET['limit']) ? (int) $_GET['limit'] : 100;
$limit = $limit === 0 ? 0 : max(1, min($limit, 1000));
$offset = ($page - 1) * $limit;

$whereParts = [];
$params = [];
$types = '';

if ($date !== null) {
    $whereParts[] = 'h.date = ?';
    $params[] = $date;
    $types .= 's';
} elseif ($start !== null || $end !== null) {
    if ($start !== null) {
        $whereParts[] = 'h.date >= ?';
        $params[] = $start;
        $types .= 's';
    }
    if ($end !== null) {
        $whereParts[] = 'h.date <= ?';
        $params[] = $end;
        $types .= 's';
    }
} elseif ($publicKey === null) {
    $whereParts[] = 'h.date = (SELECT MAX(date) FROM HostsDailyStats)';
}

if ($publicKey !== null) {
    $whereParts[] = 'h.public_key = ?';
    $params[] = $publicKey;
    $types .= 's';
}

$whereSql = count($whereParts) > 0 ? 'WHERE ' . implode(' AND ', $whereParts) : '';
$orderSql = $sortColumn !== null
    ? $sortColumn . ' ' . $sortOrder . ', h.public_key ASC'
    : 'h.date ASC, h.public_key ASC';

$countSql = "SELECT COUNT(*) AS total_rows FROM HostsDailyStats h {$whereSql}";
$countStmt = $mysqli->prepare($countSql);
if (!$countStmt) {
    daily_hosts_error(500, 'Prepare failed', ['details' => $mysqli->error]);
}
daily_hosts_bind_params($countStmt, $types, $params);
$countStmt->execute();
$countResult = $countStmt->get_result();
$countRow = $countResult ? $countResult->fetch_assoc() : ['total_rows' => 0];
$totalRows = (int) ($countRow['total_rows'] ?? 0);
$countStmt->close();

$sql = "SELECT
            h.public_key,
            h.date,
            h.used_storage,
            h.total_storage,
            h.storage_price,
            h.upload_price,
            h.download_price,
            h.accepting_contracts,
            h.contract_price,
            h.locked_collateral,
            h.risked_collateral,
            h.revenue,
            h.burned_funds,
            h.active_contracts,
            h.successful_contracts,
            h.renewed_contracts,
            h.failed_contracts
        FROM HostsDailyStats h
        {$whereSql}
        ORDER BY {$orderSql}";

if ($limit > 0) {
    $sql .= " LIMIT ? OFFSET ?";
}

$stmt = $mysqli->prepare($sql);
if (!$stmt) {
    daily_hosts_error(500, 'Prepare failed', ['details' => $mysqli->error]);
}

$queryTypes = $types;
$queryParams = $params;
if ($limit > 0) {
    $queryTypes .= 'ii';
    $queryParams[] = $limit;
    $queryParams[] = $offset;
}
daily_hosts_bind_params($stmt, $queryTypes, $queryParams);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    daily_hosts_error(500, 'Database query failed', ['details' => mysqli_error($mysqli)]);
}

$hosts = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['accepting_contracts'] = isset($row['accepting_contracts']) ? (int) $row['accepting_contracts'] : null;
    $row['active_contracts'] = isset($row['active_contracts']) ? (int) $row['active_contracts'] : null;
    $row['successful_contracts'] = isset($row['successful_contracts']) ? (int) $row['successful_contracts'] : null;
    $row['renewed_contracts'] = isset($row['renewed_contracts']) ? (int) $row['renewed_contracts'] : null;
    $row['failed_contracts'] = isset($row['failed_contracts']) ? (int) $row['failed_contracts'] : null;
    $hosts[] = $row;
}
$stmt->close();

$output = [
    'hosts' => $hosts,
    'pagination' => [
        'current_page' => $page,
        'per_page' => $limit,
        'total_rows' => $totalRows,
        'total_pages' => $limit > 0 ? (int) ceil($totalRows / $limit) : 1,
    ],
];

$jsonResult = json_encode($output);
Cache::setCache($jsonResult, $cacheKey, 'hour');

echo $jsonResult;
