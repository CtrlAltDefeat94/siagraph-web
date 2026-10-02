<?php
namespace Siagraph\Services\V2;

use Siagraph\Database\Database;
use InvalidArgumentException;

/** Read-only access to producer-calculated renter metrics. */
class RentersService
{
    private const SORTS = ['contracted_filesize', 'active_contracts', 'active_hosts', 'last_active', 'renter_wallet_address'];

    private static function rows(string $sql, array $params = []): array
    {
        $rows = Database::getConnection()->execute_query($sql, $params)->fetch_all(MYSQLI_ASSOC);
        // mysqlnd can return small BIGINTs as integers. Keep a stable, lossless API type.
        foreach ($rows as &$row) {
            foreach ($row as &$value) {
                if (is_int($value) || is_float($value)) $value = (string) $value;
            }
        }
        return $rows;
    }

    public static function identifier(string $value, bool $key = false): string
    {
        $pattern = $key ? '/^(?:ed25519:)?[a-fA-F0-9]{64}$/D' : '/^[a-fA-F0-9]{76}$/D';
        if (!preg_match($pattern, $value) || strlen($value) > ($key ? 72 : 78)) {
            throw new InvalidArgumentException($key ? 'Invalid renter public key.' : 'Invalid renter wallet address.');
        }
        return strtolower($value);
    }

    public static function dateRange(string $start, string $end): array
    {
        foreach ([$start, $end] as $date) {
            $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date, new \DateTimeZone('UTC'));
            if (!$parsed || $parsed->format('Y-m-d') !== $date) throw new InvalidArgumentException('Dates must use YYYY-MM-DD.');
        }
        $a = new \DateTimeImmutable($start, new \DateTimeZone('UTC'));
        $b = new \DateTimeImmutable($end, new \DateTimeZone('UTC'));
        if ($a > $b || $a->diff($b)->days > 365) throw new InvalidArgumentException('Choose an ordered date range of at most 366 days.');
        return [$start, $end];
    }

    public static function details(string $address): ?array
    {
        return self::rows('SELECT * FROM Renters WHERE renter_wallet_address = ?', [$address])[0] ?? null;
    }

    public static function directory(string $search, bool $active, string $sort, string $direction, int $page, int $size): array
    {
        if (!in_array($sort, self::SORTS, true) || !in_array($direction, ['asc', 'desc'], true)) throw new InvalidArgumentException('Invalid sort.');
        $where = $active ? 'r.active_contracts > 0' : '1=1';
        $params = [];
        if ($search !== '') {
            $isKey = (bool) preg_match('/^(?:ed25519:)?[a-fA-F0-9]{64}$/D', $search);
            $search = self::identifier($search, $isKey);
            $where .= $isKey ? ' AND EXISTS (SELECT 1 FROM RenterPublicKeys k WHERE k.renter_wallet_address = r.renter_wallet_address AND k.renter_public_key IN (?, ?))' : ' AND r.renter_wallet_address = ?';
            $params[] = $isKey ? preg_replace('/^ed25519:/', '', $search) : $search;
            if ($isKey) $params[] = 'ed25519:' . preg_replace('/^ed25519:/', '', $search);
        }
        $params[] = $size + 1;
        $params[] = ($page - 1) * $size;
        $rows = self::rows("SELECT r.* FROM Renters r WHERE $where ORDER BY r.$sort $direction, r.renter_wallet_address ASC LIMIT ? OFFSET ?", $params);
        return self::page($rows, $page, $size);
    }

    private static function page(array $rows, int $page, int $size): array
    {
        $more = count($rows) > $size;
        return ['items' => array_slice($rows, 0, $size), 'pagination' => ['page' => $page, 'per_page' => $size, 'has_more' => $more]];
    }

    // Cheap aggregate over the already-summarized Renters table (not raw contracts).
    public static function directoryTotals(bool $active): array
    {
        $where = $active ? 'active_contracts > 0' : '1=1';
        return self::rows("SELECT COUNT(*) AS renter_count, COALESCE(SUM(contracted_filesize), 0) AS total_filesize FROM Renters WHERE $where")[0]
            ?? ['renter_count' => '0', 'total_filesize' => '0'];
    }

    public static function keys(string $address, int $page, int $size): array
    {
        return self::page(self::rows('SELECT * FROM RenterPublicKeys WHERE renter_wallet_address = ? ORDER BY renter_public_key LIMIT ? OFFSET ?', [$address, $size + 1, ($page - 1) * $size]), $page, $size);
    }

    public static function daily(string $address, string $start, string $end): array
    {
        return self::rows('SELECT * FROM RentersDailyStats WHERE renter_wallet_address = ? AND date BETWEEN ? AND ? ORDER BY date', [$address, $start, $end]);
    }

    public static function allHistory(?string $address, ?string $after, int $limit = 1000): array
    {
        global $SETTINGS;
        if ($address === null && empty($SETTINGS['renters']['published_summaries'])) return ['items' => [], 'pagination' => ['has_more' => false, 'next_after' => null]];
        $params = [];
        if ($address !== null) {
            $sql = 'SELECT * FROM RentersDailyStats WHERE renter_wallet_address = ?';
            $params[] = $address; $date = 'date';
        } else {
            $sql = "SELECT snapshot_date, snapshot_height, published_at, payload FROM RenterPublishedSummaries WHERE kind = 'daily' AND published_at IS NOT NULL";
            $date = 'snapshot_date';
        }
        if ($after !== null) { $sql .= " AND `$date` > ?"; $params[] = $after; }
        $params[] = $limit + 1;
        $rows = self::rows($sql . " ORDER BY `$date` LIMIT ?", $params);
        $more = count($rows) > $limit; $rows = array_slice($rows, 0, $limit);
        $next = $more ? $rows[count($rows) - 1][$date] : null;
        if ($address === null) foreach ($rows as &$row) $row['payload'] = self::publishedPayload($row['payload'], 'daily');
        return ['items' => $rows, 'pagination' => ['has_more' => $more, 'next_after' => $next]];
    }

    public static function resolve(string $key, ?string $address, int $page, int $size): array
    {
        // Context is only accepted if the exact association exists; never pick an arbitrary row.
        $where = 'renter_public_key IN (?, ?)';
        $bare = preg_replace('/^ed25519:/', '', $key);
        $params = [$bare, 'ed25519:' . $bare];
        if ($address !== null) { $where .= ' AND renter_wallet_address = ?'; $params[] = $address; }
        $candidates = self::rows("SELECT DISTINCT renter_wallet_address FROM RenterPublicKeys WHERE $where ORDER BY renter_wallet_address LIMIT 2", $params);
        if (!$candidates) return ['status' => 'unmapped', 'renter_wallet_address' => null, 'items' => []];
        if (count($candidates) === 1) return ['status' => 'resolved', 'renter_wallet_address' => $candidates[0]['renter_wallet_address'], 'items' => $candidates];
        $params[] = $size + 1; $params[] = ($page - 1) * $size;
        return ['status' => 'ambiguous', 'renter_wallet_address' => null] + self::page(self::rows("SELECT DISTINCT renter_wallet_address FROM RenterPublicKeys WHERE $where ORDER BY renter_wallet_address LIMIT ? OFFSET ?", $params), $page, $size);
    }

    public static function summary(string $kind, ?string $start = null, ?string $end = null): array
    {
        // Optional producer contract: no SQL SUM, averages, shares, or raw contract fallback.
        global $SETTINGS;
        if (empty($SETTINGS['renters']['published_summaries'])) return [];
        if ($kind === 'daily') {
            $rows = self::rows("SELECT snapshot_date, snapshot_height, published_at, payload FROM RenterPublishedSummaries WHERE kind = 'daily' AND published_at IS NOT NULL AND snapshot_date BETWEEN ? AND ? ORDER BY snapshot_date", [$start, $end]);
        } else {
            $rows = self::rows('SELECT snapshot_date, snapshot_height, published_at, payload FROM RenterPublishedSummaries WHERE kind = ? AND published_at IS NOT NULL ORDER BY snapshot_date DESC LIMIT 1', [$kind]);
        }
        foreach ($rows as &$row) $row['payload'] = self::publishedPayload($row['payload'], $kind);
        return $rows;
    }
    public static function publishedPayload(string $json, string $kind): array
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        if (!is_array($data) || array_is_list($data)) throw new \UnexpectedValueException('Published summary must be an object.');
        $validate = function ($value) use (&$validate): void {
            if (is_array($value)) { foreach ($value as $item) $validate($item); }
            elseif ($value !== null && !is_string($value)) throw new \UnexpectedValueException('Published metrics must be strings.');
        };
        $validate($data);
        if ($kind === 'distribution') {
            foreach (['total_filesize', 'total_renters'] as $field) {
                if (!isset($data[$field]) || !preg_match('/^\d+$/D', $data[$field])) throw new \UnexpectedValueException('Missing distribution total.');
            }
            if (!isset($data['renters'], $data['largest_sizes']) || !is_array($data['renters']) || !array_is_list($data['renters']) || count($data['renters']) > 10 || !is_array($data['largest_sizes'])) throw new \UnexpectedValueException('Invalid published distribution.');
            if ($data['largest_sizes'] !== array_column($data['renters'], 'contracted_filesize')) throw new \UnexpectedValueException('Distribution projections differ.');
            $entries = $data['renters'];
            foreach ($entries as $entry) {
                if (!is_array($entry) || !isset($entry['renter_wallet_address'], $entry['rank']) || !preg_match('/^[a-f0-9]{76}$/D', $entry['renter_wallet_address']) || !preg_match('/^(?:[1-9]|10)$/D', $entry['rank'])) throw new \UnexpectedValueException('Invalid published renter.');
            }
            if (isset($data['others'])) {
                if (!is_array($data['others']) || ($data['others']['renter_wallet_address'] ?? null) !== null) throw new \UnexpectedValueException('Invalid Others entry.');
                $entries[] = $data['others'];
            }
            foreach ($entries as $entry) {
                if (!isset($entry['contracted_filesize'], $entry['share_percent']) || !preg_match('/^\d+$/D', $entry['contracted_filesize']) || !preg_match('/^(?:100(?:\.0+)?|[0-9]{1,2}(?:\.[0-9]+)?)$/D', $entry['share_percent'])) throw new \UnexpectedValueException('Invalid stored size or share.');
            }
        }
        return $data;
    }

}
