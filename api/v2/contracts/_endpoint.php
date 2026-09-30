<?php
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Siagraph\Services\ActiveContracts;
use Siagraph\Database\Database;
use Siagraph\Utils\Cache;

function contracts_endpoint(string $action, ?callable $initialize = null, bool $useCache = true): void
{
    global $SETTINGS;
    header('Cache-Control: no-store');
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET'); v2_json_error('method_not_allowed', 'Use GET.', 405); return;
        }
        $param = static function (string $key, string $default = ''): string {
            $value = $_GET[$key] ?? $default;
            if (!is_string($value)) throw new InvalidArgumentException('Invalid ' . $key . '.');
            return trim($value);
        };
        [$kind, $id] = ActiveContracts::identity($param('address'), $param('host_public_key'));
        $page = $param('page', '1'); $sort = $param('sort', $action === 'completed' ? 'newest' : 'ending'); $search = $param('search'); $after = $param('after');
        ActiveContracts::validateCursor($after);
        if ($action !== 'completed' && $after !== '') throw new InvalidArgumentException('after requires completed contracts.');
        if ($action === 'completed' && $page !== '1') throw new InvalidArgumentException('Use after to paginate completed contracts.');
        if (!ctype_digit($page) || strlen($page) > 5 || (int) $page < 1 || (int) $page > 10000
            || !in_array($sort, $action === 'completed' ? ['newest', 'oldest'] : ['ending', 'size', 'newest'], true)
            || ($search !== '' && !preg_match('/^[a-f0-9]{64}$/iD', $search))) throw new InvalidArgumentException('Invalid page, sort, or contract ID.');
        if ($initialize) $initialize();
        else {
            require __DIR__ . '/../../../include/config.php';
            $SETTINGS['database']['connect_timeout'] = 5;
            $SETTINGS['database']['read_timeout'] = 10;
            require_once __DIR__ . '/../../../bootstrap.php';
        }
        $cacheKey = 'active_contracts:3:' . hash('sha256', json_encode([$action, $kind, $id, $page, $sort, $search, $after]));
        if ($useCache && ($cached = Cache::getCache($cacheKey))) { header('Content-Type: application/json'); echo $cached; return; }
        $service = new ActiveContracts(Database::getConnection(), (string) ($SETTINGS['database']['raw_database'] ?? ''));
        $data = $action === 'completed' ? $service->completedPage($id, $after, $sort, $search, 25, $kind) : $service->active($kind, $id, (int) $page, $sort, $search);
        $meta = v2_meta_base($action === 'completed' ? ['Contracts'] : ['Contracts_Active', 'BlockTime'], true);
        $meta['snapshot_completeness'] = 'unverified';
        $meta['units'] = ['money' => 'hastings', 'bytes' => 'bytes'];
        $json = json_encode(['data' => $data, 'meta' => $meta, 'errors' => []], JSON_THROW_ON_ERROR);
        if ($useCache) Cache::setCacheSeconds($json, $cacheKey, 60);
        header('Content-Type: application/json'); echo $json;
    } catch (InvalidArgumentException $e) { v2_json_error('invalid_parameter', $e->getMessage(), 400); }
    catch (Throwable $e) {
        error_log('Contract details: ' . $e->getMessage());
        v2_json_error('unavailable', 'Contract information is temporarily unavailable. Please retry.', 503);
    }
}
