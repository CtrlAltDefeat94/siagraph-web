<?php
// Exercise the actual explorer aggregation against connection-local temporary tables.
require __DIR__ . '/../../include/config.php';
$db = $SETTINGS['database'];
$mysqli = new mysqli($db['servername'], $db['username'], $db['password'], $db['database']);
$mysqli->query('CREATE TEMPORARY TABLE HostsDailyStats (public_key VARCHAR(32), date DATETIME, revenue DECIMAL(50,0), estimated_egress_gb DOUBLE)');
$mysqli->query('CREATE TEMPORARY TABLE ExchangeRates (currency_code VARCHAR(10), timestamp DATETIME, eur DOUBLE, usd DOUBLE, cad DOUBLE, gbp DOUBLE)');
$mysqli->query("INSERT INTO ExchangeRates VALUES
    ('sc', UTC_DATE() - INTERVAL 2 DAY, 2, NULL, NULL, NULL),
    ('sc', UTC_DATE() - INTERVAL 2 DAY + INTERVAL 12 HOUR, 4, NULL, NULL, NULL),
    ('sc', UTC_DATE() - INTERVAL 1 DAY, 5, NULL, NULL, NULL),
    ('other', UTC_DATE() - INTERVAL 1 DAY, 999, NULL, NULL, NULL),
    ('sc', UTC_DATE(), 999, NULL, NULL, NULL)");
$mysqli->query("INSERT INTO HostsDailyStats VALUES
    ('complete', UTC_DATE() - INTERVAL 2 DAY, 2000000000000000000000000, 10),
    ('complete', UTC_DATE() - INTERVAL 1 DAY, 3000000000000000000000000, 20),
    ('complete', UTC_DATE(), 999000000000000000000000000, 999),
    ('complete', UTC_DATE() - INTERVAL 31 DAY, 999000000000000000000000000, 999),
    ('missing', UTC_DATE() - INTERVAL 3 DAY, 1000000000000000000000000, NULL),
    ('missing', UTC_DATE() - INTERVAL 1 DAY, 1000000000000000000000000, NULL),
    ('zero', UTC_DATE() - INTERVAL 3 DAY, 0, NULL),
    ('unknown', UTC_DATE() - INTERVAL 1 DAY, NULL, NULL)");
$source = file_get_contents(__DIR__ . '/../../api/v1/hosts.php');
$start = strpos($source, '$revenueCurrencies =');
$end = strpos($source, '$sortColumnForRank =', $start);
eval(substr($source, $start, $end - $start));
$rows = [];
foreach ($mysqli->query($egressTotalsSql) as $row) $rows[$row['public_key']] = $row;
function check($ok, $message) { if (!$ok) throw new RuntimeException($message); }
check(abs((float)$rows['complete']['revenue_30d_eur'] - 21) < 1e-9, 'Sum daily SC times daily mean rate');
check((float)$rows['complete']['revenue_30d_sc'] === 5.0, 'Hourly rates do not duplicate SC revenue; exclude today and older dates');
check((float)$rows['complete']['estimated_egress_gb'] === 30.0, 'Egress remains unchanged');
check($rows['complete']['revenue_30d_usd'] === null, 'Missing currency remains unavailable');
check($rows['missing']['revenue_30d_eur'] === null, 'Missing daily rates do not produce partial fiat totals');
check((float)$rows['missing']['revenue_30d_sc'] === 2.0, 'SC remains available without rates');
check((float)$rows['zero']['revenue_30d_eur'] === 0.0, 'Zero revenue needs no rate');
check($rows['unknown']['revenue_30d_eur'] === null, 'Unknown revenue stays unknown');
echo "PASS explorer daily fiat revenue aggregation, hourly averages, missing rates, and UTC bounds\n";
