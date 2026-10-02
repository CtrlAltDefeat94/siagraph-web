<?php
// Run with: php tests/markets/exchange-rates.php
// Only connection-local temporary tables are written; persistent tables and Redis are untouched.
namespace Siagraph\Utils {
    class Cache {
        public static string $lifetime = '';
        public static function getCache(string $key): ?string { return null; }
        public static function setCache(string $data, string $key, string $lifetime): void { self::$lifetime = $lifetime; }
    }
}
namespace {
    require __DIR__ . '/../../vendor/autoload.php';
    require __DIR__ . '/../../include/config.php';
    require __DIR__ . '/../../include/bcmath_polyfill.php';
    $db = $SETTINGS['database'];
    $mysqli = new mysqli($db['servername'], $db['username'], $db['password'], $db['database']);
    $currencies = ['btc', 'cad', 'cny', 'eth', 'eur', 'gbp', 'jpy', 'rub', 'usd'];
    $columns = implode(', ', array_map(fn($c) => "$c DECIMAL(24,14) NULL", $currencies));
    $mysqli->query("CREATE TEMPORARY TABLE ExchangeRates (currency_code VARCHAR(10), timestamp DATETIME, $columns)");
    $mysqli->query("INSERT INTO ExchangeRates (currency_code,timestamp,usd,eur,btc) VALUES
        ('sc','2026-09-01 00:00:00',0.001,0.002,0.00000000001),
        ('sc','2026-09-01 12:00:00',0.003,NULL,0.00000000003),
        ('sc','2026-09-02 09:00:00',0.004,0.006,NULL),
        ('sc','2026-09-02 23:00:00',0.006,0.008,NULL),
        ('sc','2026-09-04 00:00:00',0.009,0.01,NULL),
        ('other','2026-09-01 12:00:00',100,100,100)");
    function check(bool $ok, string $message): void { if (!$ok) throw new \RuntimeException($message); }
    function daily(mysqli $mysqli, array $params): array {
        $_GET = $params;
        $source = file_get_contents(__DIR__ . '/../../api/v1/daily/exchange_rate.php');
        $source = preg_replace('/include_once[^;]+;/', '', $source, 1);
        ob_start();
        eval(substr($source, 5));
        return json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
    }
    $rows = daily($mysqli, ['start'=>'2026-09-01T00:00:00Z','end'=>'2026-09-02T00:00:00Z']);
    check(count($rows) === 2, 'One row per day, including days without midnight observations');
    check($rows[0]['date'] === '2026-09-01T00:00:00Z', 'Stable date labels');
    check(abs($rows[0]['usd'] - 0.002) < 1e-12, 'Average every hourly observation');
    check(abs($rows[0]['eur'] - 0.002) < 1e-12, 'NULL rates do not count as zero');
    check(abs($rows[0]['btc'] - 0.00000000002) < 1e-20, 'Small currency rates preserve precision');
    check(!isset($rows[0]['cad']), 'Missing currency remains omitted');
    check(abs($rows[1]['usd'] - 0.005) < 1e-12, 'Inclusive end day includes late hourly snapshots');
    check(\Siagraph\Utils\Cache::$lifetime === 'hour', 'Partial-day means expire hourly');
    check(count(daily($mysqli, [])) === 3, 'Missing days are not interpolated');
    check(daily($mysqli, ['start'=>'2026-09-03T00:00:00Z','end'=>'2026-09-03T23:59:59Z']) === [], 'Empty ranges remain empty');

    $mysqli->query('CREATE TEMPORARY TABLE NetworkAggregates (date DATE, contract_revenue DECIMAL(65,0))');
    $mysqli->query("INSERT INTO NetworkAggregates VALUES ('2026-09-01',1000000000000000000000000),('2026-09-02',2000000000000000000000000)");
    $source = file_get_contents(__DIR__ . '/../../api/v1/daily/compare_metrics.php');
    $start = strpos($source, 'function sumRevenue(');
    $end = strpos($source, '$currentRev =', $start);
    eval(substr($source, $start, $end-$start));
    $revenue = sumRevenue($mysqli, '2026-09-01', '2026-09-02');
    check($revenue['sc'] === '3000000000000000000000000', 'Hourly snapshots must not multiply SC revenue');
    check(abs($revenue['usd'] - 0.012) < 1e-10, 'Daily revenue converts using each day\'s average');
    preg_match('/\$stmt = \$mysqli->prepare\("(SELECT usd, eur FROM ExchangeRates[^"\n]+)"\)/', $source, $match);
    $stmt = $mysqli->prepare($match[1]);
    $cutoff = '2026-09-02 13:00:00';
    $stmt->bind_param('s', $cutoff); $stmt->execute();
    check(abs((float)$stmt->get_result()->fetch_assoc()['usd'] - 0.004) < 1e-12, '24-hour reference must not use a later observation');
    $mysqli->close();
    echo "PASS daily averages, date boundaries, gaps, precision, hourly cache, revenue totals and historical cutoff\n";
}
