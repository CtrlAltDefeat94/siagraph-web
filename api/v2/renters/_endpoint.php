<?php
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../_core/cache.php';

use Siagraph\Services\V2\RentersService;
use Siagraph\Utils\Cache;

function renter_param(string $name, string $default = ''): string {
    if (!isset($_GET[$name])) return $default;
    if (!is_string($_GET[$name])) throw new InvalidArgumentException('Invalid ' . $name . '.');
    return trim($_GET[$name]);
}
function renter_integer(string $name, int $default, int $max): int {
    $raw = renter_param($name, (string) $default);
    if (!ctype_digit($raw) || strlen($raw) > 7 || (int) $raw < 1 || (int) $raw > $max) throw new InvalidArgumentException('Invalid ' . $name . '.');
    return (int) $raw;
}
// Optional initializer/cache switch allow isolated transport tests without production connections.
function renter_endpoint(string $action, ?callable $initialize = null, bool $useCache = true): void {
    global $SETTINGS;
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET'); v2_json_error('method_not_allowed', 'Use GET.', 405); return;
        }
        if ($initialize !== null) {
            $initialize();
        } else {
            require_once __DIR__ . '/../../../include/config.php';
            $SETTINGS['database']['connect_timeout'] = 5;
            $SETTINGS['database']['read_timeout'] = 10;
            require_once __DIR__ . '/../../../bootstrap.php';
        }
        $page = renter_integer('page', 1, 100000);
        $size = renter_integer('per_page', 25, 100);
        $meta = v2_meta_base(['Renters']);
        $meta['units'] = ['bytes' => 'bytes', 'money' => $GLOBALS['SETTINGS']['renters']['money_unit'] ?? 'hastings', 'average_contract_duration' => $GLOBALS['SETTINGS']['renters']['duration_unit'] ?? 'blocks'];
        $meta['snapshot_completeness'] = 'unverified';
        $cacheKey = v2_cache_key('renters:' . $action . ':3', $_GET);
        if ($useCache && ($cached = Cache::getCache($cacheKey))) { header('Content-Type: application/json'); header('Cache-Control: no-store'); echo $cached; return; }
        $address = renter_param('address');
        if ($address !== '') $address = RentersService::identifier($address);
        $publicKey = renter_param('public_key');
        if ($publicKey !== '' && in_array($action, ['details', 'daily', 'public-keys'], true)) {
            $publicKey = RentersService::identifier($publicKey, true);
            $resolved = RentersService::resolve($publicKey, $address ?: null, $page, $size);
            if ($resolved['status'] !== 'resolved') {
                v2_json_error($resolved['status'] === 'ambiguous' ? 'ambiguous_key' : 'not_found',
                    $resolved['status'] === 'ambiguous' ? 'This key is associated with multiple wallets. Select the relevant wallet.' : 'No renter wallet association is available for this public key.',
                    $resolved['status'] === 'ambiguous' ? 409 : 404, 'public_key', $resolved, $meta);
                return;
            }
            $address = $resolved['renter_wallet_address'];
            $meta['renter_public_key'] = $publicKey;
        }
        if (in_array($action, ['details', 'public-keys'], true) || ($action === 'daily' && $address !== '')) {
            if ($address === '') throw new InvalidArgumentException('A renter wallet address is required.');
            $renter = RentersService::details($address);
            if (!$renter) { v2_json_error('not_found', 'Renter wallet not found.', 404, 'address', [], $meta); return; }
            $address = $renter['renter_wallet_address'];
            $meta['renter_wallet_address'] = $address;
            $meta['updated_at'] = $renter['updated_at']; $meta['updated_height'] = $renter['updated_height'];
        }
        switch ($action) {
            case 'index':
                $active = renter_param('active', '1');
                if (!in_array($active, ['0', '1'], true)) throw new InvalidArgumentException('active must be 0 or 1.');
                $data = RentersService::directory(renter_param('search'), $active === '1', renter_param('sort', 'contracted_filesize'), renter_param('direction', 'desc'), $page, $size);
                break;
            case 'details': $data = $renter; break;
            case 'public-keys': $data = RentersService::keys($address, $page, $size); $meta['sources'] = ['RenterPublicKeys']; break;
            case 'resolve':
                $key = RentersService::identifier(renter_param('public_key'), true);
                $data = RentersService::resolve($key, $address ?: null, $page, $size);
                $meta['sources'] = ['RenterPublicKeys']; break;
            case 'daily':
                $all = renter_param('all', '0');
                if (!in_array($all, ['0', '1'], true)) throw new InvalidArgumentException('all must be 0 or 1.');
                if ($all === '1') {
                    if (renter_param('start') !== '' || renter_param('end') !== '') throw new InvalidArgumentException('All history cannot be combined with start or end.');
                    $after = renter_param('after');
                    if ($after !== '') RentersService::dateRange($after, $after);
                    $result = RentersService::allHistory($address ?: null, $after ?: null);
                    $data = $result['items']; $meta['pagination'] = $result['pagination'];
                } else {
                    if (renter_param('after') !== '') throw new InvalidArgumentException('after requires all=1.');
                    [$start, $end] = RentersService::dateRange(renter_param('start', gmdate('Y-m-d', strtotime('-29 days'))), renter_param('end', gmdate('Y-m-d')));
                    $data = $address !== '' ? RentersService::daily($address, $start, $end) : RentersService::summary('daily', $start, $end);
                }
                $meta['sources'] = [$address !== '' ? 'RentersDailyStats' : 'RenterPublishedSummaries'];
                if ($address === '') { $meta['availability'] = $data ? 'available' : 'awaiting_summary'; $meta['snapshot_completeness'] = $data ? 'published' : 'unavailable'; }
                break;
            case 'overview': case 'distribution':
                if ($action === 'distribution' && renter_integer('limit', 10, 10) !== 10) throw new InvalidArgumentException('Only the published top 10 distribution is supported.');
                $summary = RentersService::summary($action)[0] ?? null;
                $data = $summary['payload'] ?? null;
                $meta['sources'] = ['RenterPublishedSummaries'];
                $meta['availability'] = $summary ? 'available' : 'awaiting_summary';
                $meta['snapshot_completeness'] = $summary ? 'published' : 'unavailable';
                if ($summary) { unset($summary['payload']); $meta = array_merge($meta, $summary); }
                break;
            default: throw new InvalidArgumentException('Unknown endpoint.');
        }
        $meta['partial_data'] = $meta['snapshot_completeness'] !== 'published';
        $payload = json_encode(['data' => $data, 'meta' => $meta, 'errors' => []], JSON_THROW_ON_ERROR);
        if ($useCache) Cache::setCacheSeconds($payload, $cacheKey, 60);
        header('Content-Type: application/json'); header('Cache-Control: no-store'); echo $payload;
    } catch (InvalidArgumentException $e) {
        v2_json_error('invalid_request', $e->getMessage(), 400);
    } catch (Throwable $e) {
        error_log('Renter API failure: ' . get_class($e));
        v2_json_error('data_unavailable', 'Renter information is temporarily unavailable.', 503);
    }
}
