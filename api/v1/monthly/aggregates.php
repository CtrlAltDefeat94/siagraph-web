<?php
include_once "../../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');

$queryString = http_build_query($_GET);
$combinedString = basename(__FILE__) . "monthly" . $queryString;
$cacheKey = md5($combinedString);

if ($cached = Cache::getCache($cacheKey)) {
    echo $cached;
    exit;
}

$start_date = isset($_GET['start']) ? $_GET['start'] : '1970-01-01';

$query = "SELECT
            DATE_FORMAT(na.date, '%Y-%m-01') AS date,
            MIN(na.start_block_height) AS start_block_height,
            MAX(na.end_block_height) AS end_block_height,
            SUM(na.end_block_height - na.start_block_height) AS blocks_mined,
            SUM(na.contracts_formed) AS contracts_formed,
            SUM(na.renewed_contracts) AS renewed_contracts,
            SUM(na.failed_contracts) AS failed_contracts,
            SUM(na.successful_contracts) AS successful_contracts,
            SUM(na.unique_contract_renters) AS unique_contract_renters,
            SUM(na.contract_revenue) AS contract_revenue,
            SUM((na.contract_revenue / 1e24) * IFNULL(er.usd, 0)) AS contract_revenue_usd,
            SUM((na.contract_revenue / 1e24) * IFNULL(er.eur, 0)) AS contract_revenue_eur,
            SUM(na.siafund_tax_revenue) AS siafund_tax_revenue,
            SUM((na.siafund_tax_revenue / 1e24) * IFNULL(er.usd, 0)) AS siafund_tax_revenue_usd,
            SUM((na.siafund_tax_revenue / 1e24) * IFNULL(er.eur, 0)) AS siafund_tax_revenue_eur,
            SUM(na.siacoin_volume) AS siacoin_volume,
            SUM(na.siafund_volume) AS siafund_volume,
            SUM(na.total_fees) AS total_fees,
            SUM(na.unique_transaction_addresses) AS unique_transaction_addresses,
            SUM(na.burned_funds) AS burned_funds,
            SUM((na.burned_funds / 1e24) * IFNULL(er.usd, 0)) AS burned_funds_usd,
            SUM((na.burned_funds / 1e24) * IFNULL(er.eur, 0)) AS burned_funds_eur,
            AVG(na.avg_difficulty) AS avg_difficulty
          FROM NetworkAggregates na
          LEFT JOIN (SELECT DATE(timestamp) AS date, AVG(usd) AS usd, AVG(eur) AS eur FROM ExchangeRates WHERE currency_code = 'sc' GROUP BY DATE(timestamp)) er ON DATE(na.date) = er.date
          WHERE na.date >= ?
          GROUP BY DATE_FORMAT(na.date, '%Y-%m-01')
          ORDER BY date ASC";

$stmt = $mysqli->prepare($query);
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
