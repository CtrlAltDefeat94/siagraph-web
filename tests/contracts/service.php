<?php
require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../../include/bcmath_polyfill.php';
use Siagraph\Services\ActiveContracts;
use Siagraph\Database\Database;

function check($condition, string $message): void { if (!$condition) throw new RuntimeException($message); }
$wallet = str_repeat('a', 76); $key = str_repeat('b', 64);
check(ActiveContracts::identity($wallet, '') === ['renter', $wallet], 'wallet identity');
check(ActiveContracts::identity('', 'ed25519:' . strtoupper($key)) === ['host', $key], 'key normalization');
foreach ([['', ''], [$wallet, $key], ['invalid', ''], ['', 'xyz']] as $args) {
    try { ActiveContracts::identity(...$args); throw new RuntimeException('accepted invalid identity'); } catch (InvalidArgumentException $e) {}
}
if (!in_array('--database', $argv, true)) { echo "PASS identity validation; use --database for isolated-schema integration tests.\n"; exit; }
if (in_array('--local', $argv, true)) {
    $SETTINGS = ['database' => ['servername' => '127.0.0.1:13376', 'username' => 'root', 'password' => 'contract-fixture-only', 'database' => 'mysql']];
} else require __DIR__ . '/../../include/config.php';
Database::initialize($SETTINGS['database'] + ['connect_timeout' => 5, 'read_timeout' => 10]);
$db = Database::getConnection();
$fixtureName = 'siagraph_contract_test_' . bin2hex(random_bytes(6));
$schema = '`' . $fixtureName . '`';
// A disposable schema permits the self-join used to deduplicate historical revisions.
// MySQL temporary tables cannot be referenced twice in one query.
$db->query("CREATE DATABASE $schema");
register_shutdown_function(static function () use ($db, $schema) { $db->query("DROP DATABASE $schema"); });
foreach (explode(';', str_replace('{{schema}}', $schema, file_get_contents(__DIR__ . '/fixtures.sql'))) as $ddl) if (trim($ddl) !== '') $db->query($ddl);
$SETTINGS['database']['raw_database'] = $fixtureName;
$db->query("ALTER TABLE $schema.Contracts_Active ADD INDEX idx_active_renter_end (renter_wallet_address, windowend, contract_id), ADD INDEX idx_active_host_end (host_public_key, windowend, contract_id)");
$db->query("ALTER TABLE $schema.Contracts ADD INDEX idx_contracts_host_completed (host_public_key, resolution_height DESC, contract_id DESC, block_height DESC, revisionnumber DESC)");
$db->query("ALTER TABLE $schema.Contracts ADD INDEX idx_contracts_renter_completed (renter_wallet_address, resolution_height DESC, contract_id DESC, block_height DESC, revisionnumber DESC)");
$db->query("INSERT INTO $schema.BlockTime (block_height, timestamp) VALUES (1000, '2026-09-11 00:00:00')");
$service = new ActiveContracts($db, $SETTINGS['database']['raw_database']);
check($service->active('renter', $wallet, 1, 'ending', '')['summary']['contracts'] === '0', 'empty is zero');
$money = str_repeat('9', 50);
for ($i = 1; $i <= 28; $i++) {
    $id = str_pad(dechex($i), 64, '0', STR_PAD_LEFT);
    $host = $i % 2 ? $key : 'ed25519:' . $key;
    $end = [1000, 1001, 1144, 1145, 2008, 2009, 5320, 5321][$i % 8];
    $db->execute_query("INSERT INTO $schema.Contracts_Active (contract_id, revisionnumber, v2, block_height, filesize,
        windowend, renter_wallet_address, host_public_key, revenue_locked, renewed_from_contract_id)
        VALUES (?, 1, 1, 1000, ?, ?, ?, ?, ?, ?)", [$id, $i === 1 ? '0' : '9007199254740993', $end, $wallet, $host, $money, $i === 2 ? str_repeat('f', 64) : null]);
}
$data = $service->active('renter', $wallet, 1, 'ending', '');
check($data['summary']['contracts'] === '28' && $data['summary']['empty_contracts'] === '1', 'empty contracts retained');
check($data['summary']['bytes'] === '243194379878006811', 'bytes stay exact');
check($data['summary']['revenue_locked'] === '27' . str_repeat('9', 48) . '72', '50-digit money stays exact');
check(count($data['distribution']) === 1 && $data['distribution'][0]['contracts'] === '28', 'host encodings grouped');
check(count($data['items']) === 25 && $data['pagination']['has_more'], 'lookahead pagination');
$second = $service->active('renter', $wallet, 2, 'ending', '');
check(count($second['items']) === 3 && !$second['pagination']['has_more'], 'last page');
check(!array_intersect(array_column($data['items'], 'contract_id'), array_column($second['items'], 'contract_id')), 'pages do not overlap');
$periods = array_column($data['expirations'], 'contracts', 'period');
check($periods === ['day' => '8', 'week' => '8', 'month' => '6', 'later' => '3', 'past' => '3'] ||
    ($periods['day'] === '8' && $periods['week'] === '8' && $periods['month'] === '6' && $periods['later'] === '3' && $periods['past'] === '3'), 'exclusive deadline boundaries');
$id = str_pad('2', 64, '0', STR_PAD_LEFT);
$match = $service->active('renter', $wallet, 1, 'size', $id);
check(count($match['items']) === 1 && $match['items'][0]['renewed_from_contract_id'] === str_repeat('f',64), 'search and renewal link');
check($service->active('renter', str_repeat('c',76), 1, 'newest', $id)['items'] === [], 'search stays scoped');
check($service->active('host', $key, 1, 'ending', '')['summary']['contracts'] === '28', 'host includes both encodings');
$db->query("UPDATE $schema.Contracts_Active SET filesize = NULL, revenue_locked = NULL, windowend = NULL WHERE contract_id = '$id'");
$unknown = $service->active('renter', $wallet, 1, 'ending', '');
check($unknown['summary']['bytes'] === null && $unknown['summary']['revenue_locked'] === null, 'unknown does not become partial total');
check(in_array('unknown', array_column($unknown['expirations'], 'period'), true), 'unknown deadline separate');
for ($i = 1; $i <= 16; $i++) {
    $cid = str_pad(dechex($i), 64, '0', STR_PAD_LEFT);
    // Repeated resolved revisions must appear once. Also insert an unresolved earlier revision.
    foreach ([0, 1, 2] as $revision) $db->execute_query("INSERT INTO $schema.Contracts
        (contract_id,v2,block_height,revisionnumber,host_public_key,renter_wallet_address,resolution_height,resolution_type)
        VALUES (?,1,?,?,?, ?,?,?)", [$cid, 1000+$i+$revision, $revision, $i % 2 ? $key : 'ed25519:'.$key, $wallet, $revision ? 1000+$i : null, $revision ? 'storage_proof' : null]);
}
$completed = $service->completed($key);
check(count($completed) === 12 && count(array_unique(array_column($completed, 'contract_id'))) === 12, '12 unique completions');
check($completed[0]['resolution_height'] === '1016' && $completed[0]['revisionnumber'] === '2', 'latest completion and revision');
$renterFirst = $service->completedPage($wallet, '', 'newest', '', 5, 'renter');
$renterSecond = $service->completedPage($wallet, $renterFirst['pagination']['next_after'], 'newest', '', 5, 'renter');
check(count($renterFirst['items']) === 5 && count($renterSecond['items']) === 5, 'renter completed pagination');
check(!array_intersect(array_column($renterFirst['items'], 'contract_id'), array_column($renterSecond['items'], 'contract_id')), 'renter pages do not overlap');
check($renterFirst['items'][0]['host_public_key'] !== null, 'renter completion exposes host navigation');
check($service->completedPage(str_repeat('c',76), '', 'newest', '', 25, 'renter')['items'] === [], 'renter history scoped to wallet');
$renterPlan = $db->execute_query("EXPLAIN SELECT contract_id FROM $schema.Contracts WHERE renter_wallet_address=? AND resolution_height IS NOT NULL ORDER BY resolution_height DESC, contract_id DESC, block_height DESC, revisionnumber DESC LIMIT 27", [$wallet])->fetch_all(MYSQLI_ASSOC);
check($renterPlan[0]['key'] === 'idx_contracts_renter_completed' && !str_contains($renterPlan[0]['Extra'], 'filesort'), 'renter history index provides ordered access');
for ($i = 17; $i <= 40; $i++) {
    $db->execute_query("INSERT INTO $schema.Contracts (contract_id,v2,block_height,revisionnumber,host_public_key,resolution_height,resolution_type)
        VALUES (?,1,1000,?,?,1100,'renewal')", [str_pad(dechex($i),64,'0',STR_PAD_LEFT), '18446744073709551615', $i % 2 ? $key : 'ed25519:'.$key]);
}
foreach (['newest', 'oldest'] as $sort) {
    $all = []; $cursor = ''; $pages = 0;
    do {
        $page = $service->completedPage($key, $cursor, $sort);
        $all = array_merge($all, array_column($page['items'], 'contract_id'));
        $cursor = $page['pagination']['next_after']; $pages++;
        check($pages < 4, 'cursor makes progress');
    } while ($page['pagination']['has_more']);
    check(count($all) === 40 && count(array_unique($all)) === 40 && $pages === 2, 'all completions paginated without duplicates or omissions');
    if ($sort === 'newest') $descending = $all;
    else check($all === array_reverse($descending), 'oldest order reverses newest including tied heights');
}
check(count($service->completedPage($key, '', 'newest', str_pad('28',64,'0',STR_PAD_LEFT))['items']) === 1, 'completed search');
check($service->completedPage(str_repeat('c',64), '', 'newest', str_pad('28',64,'0',STR_PAD_LEFT))['items'] === [], 'completed search stays host-scoped');
foreach ([0,1] as $version) $db->execute_query("INSERT INTO $schema.Contracts (contract_id,v2,block_height,revisionnumber,host_public_key,resolution_height,resolution_type) VALUES (?, ?, 1200, 18446744073709551615, ?, 1200, 'storage_proof')", [str_repeat('f',64),$version,$key]);
$tieFirst = $service->completedPage($key, '', 'newest', '', 1);
$tieSecond = $service->completedPage($key, $tieFirst['pagination']['next_after'], 'newest', '', 1);
check($tieFirst['items'][0]['v2'] === '0' && $tieSecond['items'][0]['v2'] === '1', 'cursor retains both versions when all other ordering fields tie');
try { ActiveContracts::validateCursor('bad:cursor'); throw new RuntimeException('invalid cursor accepted'); } catch (InvalidArgumentException $e) {}
$db->query("DELETE FROM $schema.BlockTime");
check($service->active('renter', $wallet, 1, 'ending', '')['reference'] === null, 'missing tip supported');
// Top eight plus Others must preserve exact totals and unknown identities.
$db->query("DELETE FROM $schema.Contracts_Active");
for ($i = 1; $i <= 11; $i++) {
    $db->execute_query("INSERT INTO $schema.Contracts_Active
        (contract_id, revisionnumber, block_height, filesize, host_public_key, renter_wallet_address, revenue_locked)
        VALUES (?,1,1000,'9007199254740993',?,?,0)", [str_pad(dechex($i),64,'0',STR_PAD_LEFT), $key, $i === 11 ? null : str_pad(dechex($i),76,'0',STR_PAD_LEFT)]);
}
$groups = $service->active('host', $key, 1, 'ending', '');
check(count($groups['distribution']) === 9, 'top eight and Others');
$other = $groups['distribution'][8];
check($other['others'] === true && $other['bytes'] === '27021597764222979' && $other['contracts'] === '3', 'Others exact arithmetic');
check($groups['distribution'][0]['identity'] === null && $groups['distribution'][0]['others'] === false, 'unknown identity distinct from Others');
check($groups['summary']['renter_wallets'] === '10' && $groups['summary']['unknown_wallet_contracts'] === '1', 'unknown wallet excluded from wallet count');
// Index access checks on selective queries, without asserting unstable cost estimates.
$plan = $db->execute_query("EXPLAIN SELECT contract_id FROM $schema.Contracts_Active WHERE renter_wallet_address = ? ORDER BY windowend, contract_id LIMIT 26", [$wallet])->fetch_all(MYSQLI_ASSOC);
check($plan[0]['key'] === 'idx_active_renter_end' && !str_contains($plan[0]['Extra'], 'filesort'), 'wallet index avoids table scan and sort');
$plan = $db->execute_query("EXPLAIN SELECT contract_id FROM $schema.Contracts WHERE host_public_key = ? AND resolution_height IS NOT NULL ORDER BY resolution_height DESC, contract_id DESC, block_height DESC, revisionnumber DESC LIMIT 12", [$key])->fetch_all(MYSQLI_ASSOC);
check($plan[0]['key'] === 'idx_contracts_host_completed' && !str_contains($plan[0]['Extra'], 'filesort'), 'history index avoids chronological sort');
require __DIR__ . '/../../api/v2/contracts/_endpoint.php';
foreach ([['address' => $wallet, 'page' => '0'], ['address' => [$wallet]], ['host_public_key' => $key, 'sort' => 'DROP TABLE']] as $query) {
    $_GET = $query; ob_start(); contracts_endpoint('active', fn() => null, false); $response = json_decode(ob_get_clean(), true);
    check(http_response_code() === 400 && $response['data'] === null, 'API rejects invalid input');
}
$_GET = ['host_public_key' => $key]; ob_start(); contracts_endpoint('active', fn() => null, false); $response = json_decode(ob_get_clean(), true);
check($response['errors'] === [] && $response['data']['summary']['contracts'] === '11', 'API envelope');
$_SERVER['REQUEST_METHOD'] = 'POST'; ob_start(); contracts_endpoint('active', fn() => null, false); $response = json_decode(ob_get_clean(), true);
check(http_response_code() === 405, 'GET only');
$_SERVER['REQUEST_METHOD'] = 'GET';
$SETTINGS['database']['raw_database'] = 'missing_contract_test_database';
ob_start(); contracts_endpoint('active', fn() => null, false); $response = json_decode(ob_get_clean(), true);
check(http_response_code() === 503 && $response['errors'][0]['code'] === 'unavailable', 'infrastructure failure is unavailable, not zero');
echo "PASS isolated-schema service and API integration tests.\n";
