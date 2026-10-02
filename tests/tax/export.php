<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../api/v2/contracts/_tax-export.php';
use Siagraph\Services\ContractTaxExport as Export;
use Siagraph\Utils\TaxDecimal as Decimal;
use Siagraph\Database\Database;

$count = 0;
function check(bool $ok, string $message): void { global $count; $count++; if (!$ok) throw new RuntimeException($message); }
$key = str_repeat('b', 64);
$now = new DateTimeImmutable('2026-09-13T00:30:00Z');
$p = Export::parameters(['host_public_key'=>'ed25519:' . strtoupper($key)], $now);
check($p['from'] === '2025-07-01' && $p['to'] === '2026-09-12' && $p['currency'] === 'eur' && $p['host_public_key'] === $key, 'defaults and key normalization');
foreach ([['from'=>'2025-06-30'], ['from'=>'2026-02-30'], ['from'=>'2026-9-01'], ['from'=>[]], ['to'=>'2025-07-01','from'=>'2025-07-02'], ['host_public_key'=>'bad'], ['format'=>'xml'], ['currency'=>'eur`'], ['to'=>'2025-06-30']] as $bad) {
    try { Export::parameters($bad + ['host_public_key'=>$key], $now); check(false, 'invalid request accepted'); } catch (InvalidArgumentException $e) { check(true, 'invalid input'); }
}
check(Decimal::scale('1', 24) === '0.000000000000000000000001', 'one Hastings');
check(Decimal::multiply('0.000000000000000000000001', '0.00000001') === '0.00000000000000000000000000000001', 'tiny exact fiat');
check(Decimal::multiply('99999999999999999999999999.999999999999999999999999', '1.00000000') === '99999999999999999999999999.99999999999999999999999900000000', '50 digit amount');
check(Decimal::average('0.04800000', 24) === '0.002000000000000000000000', 'average exact');
check(Decimal::average('1', 24) === '0.041666666666666666666667', 'repeating average rounds half up');
check(Decimal::add('999999999999999999999999.999', '0.001') === '1000000000000000000000000.000', 'exact carry');
$_GET = ['host_public_key'=>$key, 'from'=>'2025-06-30']; $_SERVER['REQUEST_METHOD'] = 'GET';
ob_start(); contract_tax_export_endpoint(static function () { throw new RuntimeException('Must validate before connecting'); }); $error = json_decode(ob_get_clean(), true);
check(http_response_code() === 400 && $error['errors'][0]['code'] === 'invalid_parameter', 'early date HTTP 400');
$_SERVER['REQUEST_METHOD'] = 'POST';
ob_start(); contract_tax_export_endpoint(); ob_end_clean(); check(http_response_code() === 405, 'read-only HTTP method');
$_SERVER['REQUEST_METHOD'] = 'GET';
if (!in_array('--database', $argv, true)) { echo "PASS $count checks; pass --database for disposable-schema MySQL integration tests.\n"; exit; }

if (in_array('--local', $argv, true)) {
    $SETTINGS = ['database'=>['servername'=>'127.0.0.1:13377','username'=>'root','password'=>'tax-fixture-only','database'=>'mysql']];
} else require __DIR__ . '/../../include/config.php';
Database::initialize($SETTINGS['database'] + ['connect_timeout'=>5,'read_timeout'=>30]);
$db = Database::getConnection();
$schema = 'siagraph_tax_test_' . bin2hex(random_bytes(6));
$db->query("CREATE DATABASE `$schema`");
register_shutdown_function(static function () use ($db, $schema) { $db->query("DROP DATABASE `$schema`"); });
$db->select_db($schema);
$db->query("CREATE TABLE Contracts (
    contract_id CHAR(64) NOT NULL, v2 TINYINT NOT NULL, block_height INT NOT NULL, revisionnumber DECIMAL(21,0) NOT NULL,
    host_public_key VARCHAR(72), host_wallet_address VARCHAR(78), renewed_from_contract_id CHAR(64),
    confirmation_height INT, resolution_height INT, resolution_type VARCHAR(20), resolution_transaction_id CHAR(64),
    host_balance DECIMAL(50,0), missed_host_output DECIMAL(50,0), burned_host_output DECIMAL(50,0),
    revenue_unlocked DECIMAL(50,0), revenue_locked DECIMAL(50,0),
    PRIMARY KEY(contract_id,v2,block_height,revisionnumber),
    KEY idx_contracts_v2_host_state(v2,host_public_key,contract_id,block_height,revisionnumber))");
$db->query('CREATE TABLE BlockTime (block_height INT PRIMARY KEY, timestamp DATETIME)');
$db->query('CREATE TABLE ExchangeRates (currency_code VARCHAR(10), timestamp DATETIME, eur DECIMAL(20,8), btc DECIMAL(20,14), UNIQUE KEY uq_exchange_rate_currency_timestamp(currency_code,timestamp))');
// Irregular intervals deliberately make estimation from block height incorrect.
foreach ([[10,'2025-07-01 23:59:59'], [100,'2026-09-01 03:00:00'], [244,'2026-09-03 23:59:59'],
    [9,'2025-06-30 23:59:59'], [153,'2026-09-03 12:00:00'],
    [101,'2026-09-02 10:00:00'], [245,'2026-09-04 00:00:00'], [102,'2026-09-02 11:00:00'], [246,'2026-09-05 00:00:00'],
    [103,'2026-09-02 12:00:00'], [247,'2026-09-06 00:00:00'], [104,'2026-09-02 13:00:00'], [248,'2026-09-13 00:00:00'],
    [105,'2026-09-02 14:00:00'], [249,'2026-09-07 00:00:00'], [106,'2026-09-02 15:00:00'], [250,'2026-09-08 00:00:00']] as $block) $db->execute_query('INSERT INTO BlockTime VALUES (?,?)', $block);
$sc = static fn(string $value) => Decimal::multiply($value, '1000000000000000000000000');
$insert = static function (int $id, string $type, int $height = 100, array $changes = []) use ($db, $key, $sc): string {
    $row = $changes + ['contract_id'=>str_pad(dechex($id),64,'0',STR_PAD_LEFT),'v2'=>1,'block_height'=>$height,'revisionnumber'=>'18446744073709551615',
        'host_public_key'=>$key, 'host_wallet_address'=>str_repeat('a',76),'renewed_from_contract_id'=>null,
        'confirmation_height'=>10,'resolution_height'=>$height,'resolution_type'=>$type,'resolution_transaction_id'=>str_repeat('c',64),
        'host_balance'=>$sc('10'),'missed_host_output'=>$sc('10'),'burned_host_output'=>'0','revenue_unlocked'=>$sc('2'),'revenue_locked'=>'0'];
    $db->execute_query('INSERT INTO Contracts (' . implode(',',array_keys($row)) . ') VALUES (' . implode(',',array_fill(0,count($row),'?')) . ')', array_values($row));
    return $row['contract_id'];
};
$proof = $insert(1,'storage_proof');
$failed = $insert(2,'expiration',100,['missed_host_output'=>$sc('8'),'burned_host_output'=>$sc('5'),'revenue_unlocked'=>'0','revenue_locked'=>$sc('3')]);
$empty = $insert(3,'expiration',100,['host_balance'=>'0','missed_host_output'=>'0','burned_host_output'=>'0','revenue_locked'=>'0','revenue_unlocked'=>'0']);
$renewal = $insert(4,'renewal',100,['revenue_unlocked'=>$sc('1'),'revenue_locked'=>$sc('3'),'renewed_from_contract_id'=>str_repeat('d',64)]);
$successor = $insert(17,'renewal',110,['block_height'=>100,'revisionnumber'=>'0','resolution_type'=>null,
    'resolution_height'=>null,'renewed_from_contract_id'=>$renewal,'revenue_unlocked'=>'0','revenue_locked'=>$sc('3')]);
$insert(1,'storage_proof',100,['block_height'=>99,'revisionnumber'=>'1','resolution_type'=>null,'revenue_unlocked'=>$sc('999')]);
$insert(1,'storage_proof',100,['block_height'=>99,'revisionnumber'=>'2','revenue_unlocked'=>$sc('998')]);
$insert(1,'storage_proof',100,['block_height'=>110,'revisionnumber'=>'3','revenue_unlocked'=>$sc('997')]);
$insert(1,'storage_proof',100,['revisionnumber'=>'4','revenue_unlocked'=>$sc('996')]);
$insert(5,'expiration',100,['resolution_height'=>999]); // missing maturity
$insert(6,'storage_proof',100,['v2'=>0]);
$insert(7,'storage_proof',100,['host_public_key'=>str_repeat('e',64)]);
$insert(8,'storage_proof',101); // 23 hours
$insert(9,'storage_proof',102); // missing day
$insert(10,'storage_proof',103); // 24 hours, NULL rate
$insert(11,'storage_proof',104); // current day
$insert(12,'storage_proof',105); // duplicate hour
$insert(13,'storage_proof',106,['revenue_unlocked'=>'1']); // tiny BTC
$fallback = $insert(14,'renewal',100,['block_height'=>90,'revisionnumber'=>'1','revenue_unlocked'=>$sc('999'),'revenue_locked'=>'0']);
$insert(14,'renewal',100,['block_height'=>91,'revisionnumber'=>'1','revenue_locked'=>'0']);
$insert(14,'renewal',100,['block_height'=>91,'revisionnumber'=>'2','revenue_unlocked'=>$sc('4'),'revenue_locked'=>'0']);
$badBurn = $insert(15,'expiration',100,['missed_host_output'=>$sc('8'),'burned_host_output'=>$sc('6'),'revenue_unlocked'=>'0','revenue_locked'=>$sc('3')]);
$insert(16,'expiration',100,['host_balance'=>'-2','missed_host_output'=>'-3','burned_host_output'=>'-1','revenue_unlocked'=>'-2','revenue_locked'=>'-1']);
$beforeCutoff = $insert(18,'storage_proof',9,['confirmation_height'=>9,'resolution_height'=>9]);
foreach (['2026-09-03'=>24,'2026-09-04'=>23,'2026-09-06'=>24,'2026-09-07'=>24,'2026-09-08'=>24,'2026-09-13'=>24] as $date=>$hours) {
    for ($hour=0; $hour<$hours; $hour++) $db->execute_query('INSERT INTO ExchangeRates VALUES (?,?,?,?)', ['sc',sprintf('%s %02d:00:00',$date,$hour),$date==='2026-09-06' && $hour===12 ? null : ($hour<12?'0.00100000':'0.00300000'),'0.00000000000001']);
}
$db->execute_query('INSERT INTO ExchangeRates VALUES (?,?,?,?)', ['sc','2026-09-07 12:30:00','0.10000000',null]);
$db->execute_query('INSERT INTO ExchangeRates VALUES (?,?,?,?)', ['scp','2026-09-03 00:00:00','100.00000000',null]);
$service = new Export($db, $schema);
$p = Export::parameters(['host_public_key'=>$key,'from'=>'2026-09-03','to'=>'2026-09-03'], $now);
$rows = iterator_to_array($service->rows($p,$service->chainHeight()),false);
$byId = array_column($rows,null,'contract_id');
check(count($rows) === 7 && count($byId) === 7, 'one row per resolution, host scope, V2 only and maturity required');
check(!isset($byId[$beforeCutoff]), 'contracts resolved before 2025-07-01 are excluded');
check($byId[$proof]['revenue_unlocked_hastings'] === $sc('2'), 'authoritative resolution row, then highest revision');
check($byId[$fallback]['revenue_unlocked_hastings'] === $sc('4'), 'highest height and revision fallback');
check($byId[$proof]['start_date_utc'] === '2025-07-01' && $byId[$proof]['end_date_utc'] === '2026-09-01' && $byId[$proof]['settlement_height'] === 244 && $byId[$proof]['settlement_date_utc'] === '2026-09-03', 'actual three BlockTime mappings and +144');
check($byId[$proof]['collateral_lost_hastings'] === '0', 'successful contract has no collateral loss');
check($byId[$failed]['revenue_forfeited_hastings'] === $sc('3') && $byId[$failed]['collateral_lost_hastings'] === $sc('2') && $byId[$failed]['protocol_burned_hastings'] === $sc('5') && $byId[$failed]['burn_reconciliation_valid'] === true, 'forfeiture and principal loss reconciliation');
check($byId[$empty]['protocol_burned_hastings'] === '0' && $byId[$empty]['burn_reconciliation_valid'] === true, 'empty expiration');
check($byId[$renewal]['revenue_unlocked_hastings'] === $sc('1') && $byId[$renewal]['revenue_rolled_hastings'] === $sc('3') && $byId[$renewal]['successor_contract_id'] === $successor && $byId[$renewal]['collateral_lost_hastings'] === '0', 'renewal revenue and successor');
check($byId[$badBurn]['burn_reconciliation_valid'] === false, 'inconsistent burn exposed');
check($byId[$proof]['average_sc_price_eur'] === '0.002000000000000000000000', 'daily average excludes non-SC rates');
check($byId[$proof]['revenue_eur'] === '0.004' . str_repeat('0',45), 'exact SC to EUR');
check(count(iterator_to_array($service->rows($p,243))) === 0, 'tip excludes immature contracts');
$summary = Export::summary($rows,$p,250);
check($summary['contracts'] === 7 && $summary['successful_contracts'] === 1 && $summary['renewed_contracts'] === 2 && $summary['failed_contracts'] === 4, 'outcome summary');
check($summary['total_revenue_sc'] === '7.' . str_repeat('0',24), 'summary revenue excludes rolled and forfeited amounts');
foreach (['2026-09-04'=>'available','2026-09-05'=>'missing','2026-09-06'=>'available','2026-09-07'=>'available','2026-09-13'=>'available'] as $date=>$status) {
    $range = Export::parameters(['host_public_key'=>$key,'from'=>$date,'to'=>$date],$now);
    $data = iterator_to_array($service->rows($range,250));
    check(count($data) === 1 && $data[0]['exchange_rate_status'] === $status && ($data[0]['revenue_eur'] === null) === ($status === 'missing') && $data[0]['revenue_unlocked_sc'] !== null, "$status day uses the daily average when available");
    $s = Export::summary($data,$range,250);
    check(($s['total_revenue_eur'] === null) === ($status === 'missing') && $s['rows_without_exchange_rate'] === ($status === 'missing' ? 1 : 0), 'only a day without any price leaves the currency total unavailable');
}
$btc = Export::parameters(['host_public_key'=>$key,'from'=>'2026-09-08','to'=>'2026-09-08','currency'=>'BTC'],$now);
$btcRow = iterator_to_array($service->rows($btc,250))[0];
check($btcRow['revenue_btc'] === '0.' . str_repeat('0',37) . '1' . str_repeat('0',10), 'one Hastings times tiny BTC remains exact');
$SETTINGS['database']['raw_database'] = $schema;
$_GET = ['host_public_key'=>$key,'from'=>'2026-09-03','to'=>'2026-09-03'];
ob_start(); contract_tax_export_endpoint(static function () {}); $jsonText = ob_get_clean(); $json = json_decode($jsonText,true,512,JSON_THROW_ON_ERROR);
check($json['errors'] === [] && count($json['data']['items']) === 7, 'JSON endpoint envelope');
$public = $json['data']['items'];
check(array_keys($public[0]) === Export::columns('eur'), 'exact simplified public fields');
check($public[0]['resolution_date'] === '2026-09-01' && $public[0]['payout_maturity_date'] === '2026-09-03', 'public resolution and maturity dates');
check($public[0]['revenue_rolled_sc'] === '0.' . str_repeat('0',24), 'proof has no rolled revenue');
check($public[0]['sc_price_eur'] === '0.002', 'price removes padding without rounding');
check($public[1]['collateral_lost_sc'] === '2.' . str_repeat('0',24) && $public[1]['revenue_forfeited_sc'] === '3.' . str_repeat('0',24) && !array_key_exists('protocol_burned_sc', $public[1]), 'public failure exposes burn components without a redundant total');
check($public[0]['collateral_lost_sc'] === '0.' . str_repeat('0',24) && $public[3]['collateral_lost_sc'] === '0.' . str_repeat('0',24), 'proof and renewal expose zero collateral loss');
check(Export::exportRow($btcRow, 'btc')['sc_price_btc'] === '0.00000000000001', 'compact tiny rate stays exact');
$missing = $byId[$proof]; $missing['sc_price_eur'] = null;
check(Export::exportRow($missing, 'eur')['sc_price_eur'] === null, 'public missing rate remains null');
$_GET['format'] = 'csv';
ob_start(); contract_tax_export_endpoint(static function () {}); $csvText = ob_get_clean();
$stream = fopen('php://temp','w+'); fwrite($stream,$csvText); rewind($stream);
$headers = fgetcsv($stream,0,',','"',''); $csvRows = [];
while (($csvRow = fgetcsv($stream,0,',','"','')) !== false) $csvRows[] = array_combine($headers,$csvRow);
check(count($csvRows) === count($json['data']['items']), 'CSV row count');
foreach ($json['data']['items'] as $i=>$row) foreach ($headers as $field) {
    $expected = is_bool($row[$field]) ? ($row[$field]?'true':'false') : (string) $row[$field];
    check($csvRows[$i][$field] === $expected, 'JSON/CSV equivalent ' . $field);
}
fclose($stream);
if (in_array('--examples',$argv,true)) {
    file_put_contents(__DIR__ . '/../../docs/contract-tax-example.json', json_encode($json,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES) . "\n");
    file_put_contents(__DIR__ . '/../../docs/contract-tax-example.csv',$csvText);
}
echo "PASS $count checks including MySQL resolution selection, amounts, dates, rates, summaries and JSON/CSV endpoints.\n";
