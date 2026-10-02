<?php
ob_start();
require __DIR__ . '/../../vendor/autoload.php';
use Siagraph\Services\V2\RentersService as R;
use Siagraph\Database\Database;

function check(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS $message\n";
}
function invalid(callable $fn, string $message): void {
    try { $fn(); } catch (InvalidArgumentException $e) { check(true, $message); return; }
    throw new RuntimeException($message);
}
$a = str_repeat('a', 76); $b = str_repeat('b', 76); $c = str_repeat('c', 76);
$k = str_repeat('1', 64); $k2 = str_repeat('2', 64); $unknown = str_repeat('f', 64);
check(R::identifier(strtoupper($a)) === $a, 'canonical wallet input');
check(R::identifier('ed25519:' . $k, true) === 'ed25519:' . $k, 'prefixed key input');
invalid(fn() => R::identifier($k), 'a public key cannot identify a wallet');
invalid(fn() => R::identifier("' OR 1=1"), 'SQL injection input rejected');
invalid(fn() => R::dateRange('2026-02-30', '2026-03-01'), 'impossible dates rejected');
invalid(fn() => R::dateRange('2026-03-02', '2026-03-01'), 'reversed dates rejected');
invalid(fn() => R::dateRange('2020-01-01', '2026-03-01'), 'oversized history rejected');
check(R::dateRange('2024-02-29', '2024-03-01')[0] === '2024-02-29', 'leap day accepted');
$SETTINGS = [];
check(R::summary('overview') === [], 'missing publication does not query or aggregate');

$published = ['largest_sizes' => ['1000'], 'total_filesize' => '1200', 'total_renters' => '2', 'renters' => [['rank' => '1', 'renter_wallet_address' => $a, 'contracted_filesize' => '1000', 'share_percent' => '83.333333']], 'others' => ['renter_wallet_address' => null, 'contracted_filesize' => '200', 'share_percent' => '16.666667', 'renter_count' => '1']];
check(R::publishedPayload(json_encode($published), 'distribution') === $published, 'stored shares and Others passed through exactly');
try { R::publishedPayload('{"active_contracts":9007199254740992}', 'overview'); throw new RuntimeException('Expected string validation'); }
catch (UnexpectedValueException $e) { check(true, 'JSON numeric metric rejected instead of losing precision'); }
if (!in_array('--database', $argv, true)) exit(0);

require __DIR__ . '/../../include/config.php';
$config = in_array('--local', $argv, true) ? ['servername' => '127.0.0.1:13376', 'username' => 'root', 'password' => 'renter-fixture-only', 'database' => 'renter_fixtures'] : $SETTINGS['database'];
Database::initialize($config + ['connect_timeout' => 5, 'read_timeout' => 10]);
$db = Database::getConnection();
// Temporary fixture tables shadow persistent names on this connection only.
foreach (explode(';', file_get_contents(__DIR__ . '/fixtures.sql')) as $ddl) {
    if (trim($ddl) !== '') $db->query($ddl);
}
check(R::directory('', false, 'contracted_filesize', 'desc', 1, 25)['items'] === [], 'empty directory');
check(R::details($a) === null, 'missing wallet');
$money = str_repeat('9', 50);
foreach ([$a, $b, $c] as $i => $wallet) {
    $db->execute_query('INSERT INTO Renters (renter_wallet_address, first_seen_height, first_seen, last_active_height, last_active, updated_height, updated_at, active_contracts, contracted_filesize, refundable_allowance) VALUES (?, 1, "2026-01-01", 20, "2026-01-20", 20, "2026-01-20", ?, ?, ?)', [$wallet, $i === 2 ? '0' : '3', $i === 2 ? '0' : '18446744073709551615', $money]);
}
foreach ([[$a, $k], [$a, 'ed25519:' . $k], [$a, $k2], [$b, $k2]] as [$wallet, $key]) {
    $db->execute_query('INSERT INTO RenterPublicKeys VALUES (?, ?, 1, "2026-01-01", 20, "2026-01-20")', [$wallet, $key]);
}
$detail = R::details($a);
check($detail['refundable_allowance'] === $money && $detail['contracted_filesize'] === '18446744073709551615', '50-digit money and unsigned BIGINT preserved');
check($detail['active_contracts'] === '3', 'small counts have stable string types');
$page = R::directory('', true, 'contracted_filesize', 'desc', 1, 1);
check($page['items'][0]['renter_wallet_address'] === $a && $page['pagination']['has_more'], 'stable tie ordering and lookahead');
check(R::directory('', true, 'contracted_filesize', 'desc', 2, 1)['items'][0]['renter_wallet_address'] === $b, 'second page is distinct');
check(count(R::directory($k, false, 'contracted_filesize', 'desc', 1, 25)['items']) === 1, 'key encodings do not duplicate wallet or metrics');
check(R::resolve($k, null, 1, 25)['renter_wallet_address'] === $a, 'bare and prefixed keys resolve to same wallet');
check(R::resolve($k2, null, 1, 25)['status'] === 'ambiguous', 'shared key never selects arbitrary wallet');
check(R::resolve($k2, $b, 1, 25)['renter_wallet_address'] === $b, 'recorded wallet context disambiguates');
check(R::resolve($k, $b, 1, 25)['status'] === 'unmapped', 'wrong wallet context rejected');
check(R::resolve($unknown, null, 1, 25)['status'] === 'unmapped', 'unmapped key');
check(R::keys($a, 1, 2)['pagination']['has_more'], 'public keys paginated');
check(R::daily($a, '2026-01-01', '2026-01-03') === [], 'known wallet with empty history');
$db->execute_query('INSERT INTO RentersDailyStats (renter_wallet_address, date, snapshot_height, spending, renewal_funds_rolled) VALUES (?, "2026-01-01", 10, ?, NULL), (?, "2026-01-03", 20, "0", "0")', [$a, $money, $a]);
$daily = R::daily($a, '2026-01-01', '2026-01-03');
check(count($daily) === 2 && $daily[1]['date'] === '2026-01-03', 'missing day not synthesized');
check($daily[0]['spending'] === $money && $daily[0]['renewal_funds_rolled'] === null && $daily[1]['renewal_funds_rolled'] === '0', 'daily precision and null distinct from zero');
invalid(fn() => R::directory('', true, '1; DROP TABLE Renters', 'desc', 1, 25), 'sort injection rejected');
$ddl = file_get_contents(__DIR__ . '/../../docs/sql/renter-published-summaries.sql');
$db->query(str_replace('CREATE TABLE IF NOT EXISTS', 'CREATE TEMPORARY TABLE', $ddl));
$SETTINGS['renters']['published_summaries'] = true;
$db->execute_query('INSERT INTO RenterPublishedSummaries VALUES ("overview", "2026-01-01", 10, ?, "2026-01-02"), ("overview", "2026-01-02", 20, ?, NULL)', [json_encode(['contracted_filesize' => $money]), json_encode(['contracted_filesize' => '2'])]);
$summary = R::summary('overview');
check(count($summary) === 1 && $summary[0]['payload']['contracted_filesize'] === $money, 'only completed stored summaries served without arithmetic');
require __DIR__ . '/../../api/v2/renters/_endpoint.php';
function endpoint(string $action, array $params = [], string $method = 'GET'): array {
    $_GET = $params; $_SERVER['REQUEST_METHOD'] = $method; http_response_code(200);
    ob_start(); renter_endpoint($action, static function () {}, false); $json = ob_get_clean();
    return [http_response_code(), json_decode($json, true, 512, JSON_THROW_ON_ERROR)];
}
[$code, $body] = endpoint('details', ['address' => $a]);
check($code === 200 && $body['data']['refundable_allowance'] === $money && $body['meta']['partial_data'], 'API preserves stored money and reports unverified snapshot');
[$code, $body] = endpoint('details', ['address' => str_repeat('d', 76)]);
check($code === 404 && $body['data'] === null, 'API unknown wallet is 404');
[$code, $body] = endpoint('daily', ['address' => $c, 'start' => '2026-01-01', 'end' => '2026-01-03']);
check($code === 200 && $body['data'] === [], 'API known wallet empty history is successful');
check(endpoint('details', ['address' => [$a]])[0] === 400, 'API array input rejected');
check(endpoint('daily', ['address' => $a, 'start' => '2026-02-30', 'end' => '2026-03-01'])[0] === 400, 'API strict date validation');
check(endpoint('index', ['per_page' => '101'])[0] === 400, 'API page limit enforced');
check(endpoint('index', [], 'POST')[0] === 405, 'API GET only');
[$code, $body] = endpoint('resolve', ['public_key' => $k2, 'address' => $b]);
check($code === 200 && $body['data']['renter_wallet_address'] === $b, 'API resolver uses recorded context');
$SETTINGS['renters']['published_summaries'] = false;
[$code, $body] = endpoint('distribution');
check($code === 200 && $body['data'] === null && $body['meta']['availability'] === 'awaiting_summary', 'API unavailable summary never falls back to aggregation');
$SETTINGS['renters']['published_summaries'] = true;
$db->execute_query('INSERT INTO RenterPublishedSummaries VALUES ("distribution", "2026-01-01", 10, ?, "2026-01-02")', [json_encode($published)]);
[$code, $body] = endpoint('distribution');
check($code === 200 && $body['data'] == $published && $body['data']['others']['share_percent'] === '16.666667' && $body['meta']['snapshot_completeness'] === 'published', 'API distribution reads exact stored snapshot');
check(endpoint('distribution', ['limit' => '5'])[0] === 400, 'API unsupported top-N rejected');
[$code, $body] = endpoint('details', ['public_key' => $k]);
check($code === 200 && $body['data']['renter_wallet_address'] === $a, 'details resolves public key on the server');
check(endpoint('details', ['public_key' => $k2])[0] === 409, 'ambiguous public key requires wallet context');
check(endpoint('details', ['public_key' => $unknown])[0] === 404, 'unknown public key returns 404');
[$code, $body] = endpoint('details', ['public_key' => $k2, 'address' => $b]);
check($code === 200 && $body['data']['renter_wallet_address'] === $b, 'public key context selects the recorded wallet');
check(endpoint('daily', ['address' => $a, 'all' => '1', 'start' => '2020-01-01'])[0] === 400, 'All range rejects mixed date filters');
check(endpoint('daily', ['address' => $a, 'all' => '1', 'after' => '2020-02-30'])[0] === 400, 'All cursor validates calendar dates');
$values = []; $params = [];
for ($i = 0; $i < 1001; $i++) {
    $values[] = '(?, ?, 1)'; $params[] = $a;
    $params[] = (new DateTimeImmutable('2020-01-01'))->modify("+$i days")->format('Y-m-d');
}
$db->execute_query('INSERT INTO RentersDailyStats (renter_wallet_address, date, snapshot_height) VALUES ' . implode(',', $values), $params);
[$code, $body] = endpoint('daily', ['public_key' => $k, 'all' => '1']);
check($code === 200 && count($body['data']) === 1000 && $body['data'][0]['date'] === '2020-01-01' && $body['meta']['pagination']['has_more'], 'All reads historical data beyond one year in bounded pages');
$cursor = $body['meta']['pagination']['next_after'];
[$code, $body] = endpoint('daily', ['address' => $a, 'all' => '1', 'after' => $cursor]);
check($code === 200 && count($body['data']) === 3 && $body['data'][0]['date'] > $cursor && !$body['meta']['pagination']['has_more'], 'All cursor completes without duplicates or truncation');
$db->execute_query('INSERT INTO RenterPublishedSummaries VALUES ("daily", "2020-01-01", 1, ?, "2020-01-02"), ("daily", "2020-01-02", 2, ?, NULL)', [json_encode(['spending' => '1']), json_encode(['spending' => '2'])]);
[$code, $body] = endpoint('daily', ['all' => '1']);
check($code === 200 && count($body['data']) === 1 && $body['data'][0]['payload']['spending'] === '1', 'All network history uses only published summaries');
if (in_array('--volume', $argv, true)) {
    $values = []; $params = [];
    for ($i = 1; $i <= 5000; $i++) {
        $values[] = '(?, 1, "2026-01-01", 20, "2026-01-20", 20, "2026-01-20", 1, ?)';
        $params[] = str_pad(dechex($i), 76, '0', STR_PAD_LEFT); $params[] = (string) $i;
    }
    $db->execute_query('INSERT INTO Renters (renter_wallet_address, first_seen_height, first_seen, last_active_height, last_active, updated_height, updated_at, active_contracts, contracted_filesize) VALUES ' . implode(',', $values), $params);
    $query = 'EXPLAIN SELECT r.* FROM Renters r WHERE r.active_contracts > 0 ORDER BY r.contracted_filesize DESC, r.renter_wallet_address ASC LIMIT 26';
    $before = $db->query($query)->fetch_assoc();
    $db->query('CREATE INDEX idx_renters_storage_rank ON Renters (contracted_filesize DESC, renter_wallet_address ASC)');
    $after = $db->query($query)->fetch_assoc();
    check($after['key'] === 'idx_renters_storage_rank' && !str_contains($after['Extra'], 'filesort'), '5003-wallet ranking uses proposed index without filesort');
    echo 'Query plan before: ' . $before['Extra'] . '; after: ' . $after['Extra'] . "\n";
}
// No persistent data is touched; all fixture tables disappear on disconnect.
$db->close();
echo "All database fixture checks passed.\n";
