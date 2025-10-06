<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

// Build cache key
$queryString = http_build_query($_GET);
$combinedString = basename(__FILE__) . "daily" . $queryString;
$cacheKey = md5($combinedString);

// Return cached response if available
if ($cached = Cache::getCache($cacheKey)) {
    echo $cached;
    exit;
}

// Determine start date: default to last 365 days if not provided
$start_date = isset($_GET['start']) ? $_GET['start'] : '1970-01-01';
// Ensure it’s not earlier than the earliest supported date

$stmt = $mysqli->prepare("SELECT
            na.date,
            na.start_block_height,
            na.end_block_height,
            na.end_block_height - na.start_block_height AS blocks_mined,
            na.contracts_formed,
            na.renewed_contracts,
            na.failed_contracts,
            na.successful_contracts,
            na.unique_contract_renters,
            na.contract_revenue,
            (na.contract_revenue / 1e24) * IFNULL(er.usd, 0) AS contract_revenue_usd,
            (na.contract_revenue / 1e24) * IFNULL(er.eur, 0) AS contract_revenue_eur,
            na.siafund_tax_revenue,
            (na.siafund_tax_revenue / 1e24) * IFNULL(er.usd, 0) AS siafund_tax_revenue_usd,
            (na.siafund_tax_revenue / 1e24) * IFNULL(er.eur, 0) AS siafund_tax_revenue_eur,
            na.siacoin_volume,
            na.siafund_volume,
            na.total_fees,
            na.unique_transaction_addresses,
            na.burned_funds,
            (na.burned_funds / 1e24) * IFNULL(er.usd, 0) AS burned_funds_usd,
            (na.burned_funds / 1e24) * IFNULL(er.eur, 0) AS burned_funds_eur,
            na.avg_difficulty
          FROM NetworkAggregates na
          LEFT JOIN (SELECT DATE(timestamp) AS date, AVG(usd) AS usd, AVG(eur) AS eur FROM ExchangeRates WHERE currency_code = 'sc' GROUP BY DATE(timestamp)) er ON DATE(na.date) = er.date
          WHERE na.date >= ?
          ORDER BY na.date ASC");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Prepare failed', 'details' => $mysqli->error]);
    exit;
}

$stmt->bind_param('s', $start_date);
$stmt->execute();
$result = $stmt->get_result();
if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed', 'details' => mysqli_error($mysqli)]);
    exit;
}

$data = mysqli_fetch_all($result, MYSQLI_ASSOC);
$stmt->close();

// Structure fiat values in a dictionary
foreach ($data as &$row) {
    $row['siafund_tax_revenue'] = [
        'sc'  => $row['siafund_tax_revenue'],
        'usd' => isset($row['siafund_tax_revenue_usd']) ? round((float)$row['siafund_tax_revenue_usd'], 2) : null,
        'eur' => isset($row['siafund_tax_revenue_eur']) ? round((float)$row['siafund_tax_revenue_eur'], 2) : null,
    ];

    $row['contract_revenue'] = [
        'sc'  => $row['contract_revenue'],
        'usd' => isset($row['contract_revenue_usd']) ? round((float)$row['contract_revenue_usd'], 2) : null,
        'eur' => isset($row['contract_revenue_eur']) ? round((float)$row['contract_revenue_eur'], 2) : null,
    ];

    if (isset($row['burned_funds'])) {
        $row['burned_funds'] = [
            'sc'  => $row['burned_funds'],
            'usd' => isset($row['burned_funds_usd']) ? round((float)$row['burned_funds_usd'], 2) : null,
            'eur' => isset($row['burned_funds_eur']) ? round((float)$row['burned_funds_eur'], 2) : null,
        ];
    }

    unset($row['siafund_tax_revenue_usd'], $row['siafund_tax_revenue_eur']);
    unset($row['contract_revenue_usd'], $row['contract_revenue_eur']);
    unset($row['burned_funds_usd'], $row['burned_funds_eur']);
}
unset($row);

$jsonResult = json_encode($data);
Cache::setCache($jsonResult, $cacheKey, 'hour');

echo $jsonResult;
