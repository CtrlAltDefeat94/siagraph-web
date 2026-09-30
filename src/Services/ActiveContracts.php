<?php
namespace Siagraph\Services;

use InvalidArgumentException;
use mysqli;

/** Current contract state is owned by the producer; never reconstruct it from revisions. */
class ActiveContracts
{
    public function __construct(private mysqli $db, private string $database) {}

    private function table(string $name): string
    {
        if ($this->database === '') throw new \RuntimeException('Raw database is not configured.');
        return '`' . str_replace('`', '``', $this->database) . '`.`' . $name . '`';
    }

    private function rows(string $sql, array $params = []): array
    {
        $rows = $this->db->execute_query($sql, $params)->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) foreach ($row as &$value) {
            if (is_int($value) || is_float($value)) $value = (string) $value;
        }
        return $rows;
    }

    public static function identity(string $address, string $host): array
    {
        if (($address === '') === ($host === '')) throw new InvalidArgumentException('Supply either address or host_public_key.');
        if ($address !== '') {
            if (!preg_match('/^[a-f0-9]{76}$/iD', $address)) throw new InvalidArgumentException('Invalid wallet address.');
            return ['renter', strtolower($address)];
        }
        if (!preg_match('/^(?:ed25519:)?[a-f0-9]{64}$/iD', $host)) throw new InvalidArgumentException('Invalid host public key.');
        return ['host', preg_replace('/^ed25519:/', '', strtolower($host))];
    }

    private function filter(string $kind, string $id): array
    {
        return $kind === 'renter'
            ? ['renter_wallet_address = ?', [$id]]
            : ['host_public_key IN (?, ?)', [$id, 'ed25519:' . $id]];
    }

    // A partial sum must not masquerade as a complete amount. Empty sets are zero.
    private static function sum(string $field): string
    {
        return "CASE WHEN COUNT(*) = COUNT($field) THEN COALESCE(SUM($field), 0) ELSE NULL END";
    }

    public function active(string $kind, string $id, int $page, string $sort, string $search): array
    {
        $orders = ['ending' => 'windowend, contract_id', 'size' => 'filesize DESC, contract_id', 'newest' => 'confirmation_height DESC, contract_id'];
        if (!isset($orders[$sort]) || $page < 1 || $page > 10000) throw new InvalidArgumentException('Invalid pagination or sort.');
        if ($search !== '' && !preg_match('/^[a-f0-9]{64}$/iD', $search)) throw new InvalidArgumentException('Search requires a full contract ID.');
        [$where, $params] = $this->filter($kind, $id);
        $table = $this->table('Contracts_Active');
        $bytes = self::sum('filesize'); $revenue = self::sum('revenue_locked');
        $this->db->begin_transaction(MYSQLI_TRANS_START_READ_ONLY | MYSQLI_TRANS_START_WITH_CONSISTENT_SNAPSHOT);
        try {
            $tip = $this->rows('SELECT block_height, timestamp FROM ' . $this->table('BlockTime') . ' ORDER BY block_height DESC LIMIT 1')[0] ?? null;
            $summary = $this->rows("SELECT COUNT(*) AS contracts, COALESCE(SUM(filesize > 0),0) AS with_data,
                COALESCE(SUM(filesize = 0),0) AS empty_contracts, COALESCE(SUM(filesize IS NULL),0) AS unknown_size,
                COUNT(DISTINCT renter_wallet_address) AS renter_wallets,
                COALESCE(SUM(renter_wallet_address IS NULL),0) AS unknown_wallet_contracts,
                COUNT(DISTINCT REPLACE(host_public_key, 'ed25519:', '')) AS hosts,
                $bytes AS bytes, $revenue AS revenue_locked FROM $table WHERE $where", $params)[0];
            $group = $kind === 'renter' ? "REPLACE(host_public_key, 'ed25519:', '')" : 'renter_wallet_address';
            // Keep even the Others sum in MySQL DECIMAL arithmetic. The site's
            // optional BCMath fallback is not guaranteed to preserve large integers.
            $distribution = $this->rows("WITH grouped AS (
                SELECT $group AS identity, COUNT(*) AS contracts, $bytes AS bytes
                FROM $table WHERE $where GROUP BY $group
            ), ranked AS (
                SELECT *, ROW_NUMBER() OVER (ORDER BY bytes DESC, identity) AS position FROM grouped
            ), bucketed AS (
                SELECT CASE WHEN position > 8 THEN NULL ELSE identity END AS identity,
                    CASE WHEN position > 8 THEN 1 ELSE 0 END AS others, contracts, bytes FROM ranked
            ) SELECT identity, others, SUM(contracts) AS contracts,
                CASE WHEN COUNT(*) = COUNT(bytes) THEN SUM(bytes) ELSE NULL END AS bytes
                FROM bucketed GROUP BY identity, others ORDER BY others, bytes DESC, identity", $params);
            foreach ($distribution as &$entry) $entry['others'] = $entry['others'] === '1';
            unset($entry);
            $expirations = [];
            if ($tip !== null) {
                $height = (int) $tip['block_height'];
                // Mutually exclusive windows; 144 blocks/day is only an estimate.
                $bucket = 'CASE WHEN windowend IS NULL THEN \'unknown\' WHEN windowend <= ? THEN \'past\' WHEN windowend <= ? THEN \'day\' WHEN windowend <= ? THEN \'week\' WHEN windowend <= ? THEN \'month\' ELSE \'later\' END';
                $expirations = $this->rows("SELECT $bucket AS period, COUNT(*) AS contracts, $bytes AS bytes, $revenue AS revenue_locked
                    FROM $table WHERE $where GROUP BY period", [$height, $height + 144, $height + 1008, $height + 4320, ...$params]);
            }
            $listWhere = $where; $listParams = $params;
            if ($search !== '') { $listWhere .= ' AND contract_id = ?'; $listParams[] = strtolower($search); }
            $items = $this->rows("SELECT contract_id, v2, renter_wallet_address, renter_public_key, host_public_key,
                filesize, windowend, confirmation_height, renewed_from_contract_id, revenue_locked
                FROM $table WHERE $listWhere ORDER BY {$orders[$sort]} LIMIT 26 OFFSET ?", [...$listParams, ($page - 1) * 25]);
            $this->db->commit();
            return ['summary' => $summary, 'distribution' => $distribution, 'expirations' => $expirations,
                'reference' => $tip, 'items' => array_slice($items, 0, 25),
                'pagination' => ['page' => $page, 'has_more' => count($items) > 25]];
        } catch (\Throwable $e) { $this->db->rollback(); throw $e; }
    }

    public function completed(string $host): array
    {
        return $this->completedPage($host, '', 'newest', '', 12)['items'];
    }

    public static function validateCursor(string $after): void
    {
        if ($after !== '' && !preg_match('/^\d{1,10}:[a-f0-9]{64}:\d{1,10}:\d{1,21}:[01]$/D', $after)) {
            throw new InvalidArgumentException('Invalid completed-contract cursor.');
        }
    }

    public function completedPage(string $host, string $after = '', string $sort = 'newest', string $search = '', int $size = 25, string $kind = 'host'): array
    {
        self::validateCursor($after);
        if (!in_array($kind, ['host', 'renter'], true) || !in_array($sort, ['newest', 'oldest'], true) || $size < 1 || $size > 25
            || ($search !== '' && !preg_match('/^[a-f0-9]{64}$/iD', $search))) throw new InvalidArgumentException('Invalid completed-contract filters.');
        $table = $this->table('Contracts');
        $rows = [];
        $direction = $sort === 'newest' ? 'DESC' : 'ASC';
        $operator = $sort === 'newest' ? '<' : '>';
        $versionOperator = $sort === 'newest' ? '>' : '<';
        $where = ''; $params = [];
        if ($search !== '') { $where .= ' AND c.contract_id = ?'; $params[] = strtolower($search); }
        if ($after !== '') {
            // The full ordering tuple prevents omissions when heights or IDs tie.
            $parts = explode(':', $after); $tuple = array_slice($parts, 0, 4);
            $columns = '(c.resolution_height, c.contract_id, c.block_height, c.revisionnumber)';
            $values = '(CAST(? AS UNSIGNED), ?, CAST(? AS UNSIGNED), CAST(? AS DECIMAL(21,0)))';
            $where .= " AND ($columns $operator $values OR ($columns = $values AND c.v2 $versionOperator CAST(? AS UNSIGNED)))";
            $params = [...$params, ...$tuple, ...$tuple, $parts[4]];
        }
        // Separate equality queries let both key encodings use the chronological index.
        $identityColumn = $kind === 'renter' ? 'renter_wallet_address' : 'host_public_key';
        foreach ($kind === 'renter' ? [$host] : [$host, 'ed25519:' . $host] as $key) {
            $part = $this->rows("SELECT c.contract_id, c.v2, c.renter_wallet_address, c.renter_public_key, c.host_public_key,
                c.resolution_type, c.resolution_height, c.block_height, c.revisionnumber
                FROM $table c WHERE c.$identityColumn = ? AND c.resolution_height IS NOT NULL $where
                AND NOT EXISTS (SELECT 1 FROM $table n WHERE n.contract_id = c.contract_id AND n.v2 = c.v2
                    AND n.resolution_height IS NOT NULL AND (n.block_height > c.block_height
                        OR (n.block_height = c.block_height AND n.revisionnumber > c.revisionnumber)))
                ORDER BY c.resolution_height $direction, c.contract_id $direction, c.block_height $direction,
                    c.revisionnumber $direction LIMIT ?", [$key, ...$params, $size + 2]);
            $rows = array_merge($rows, $part);
        }
        // At most two versions can share the entire indexed ordering tuple.
        // Fetch an extra candidate so a LIMIT boundary cannot cut off a version
        // tie needed for the page. Merge ties here without forcing a SQL filesort.
        usort($rows, static function ($a, $b) use ($sort) {
            $cmp = ((int) $a['resolution_height'] <=> (int) $b['resolution_height'])
                ?: strcmp($a['contract_id'], $b['contract_id'])
                ?: ((int) $a['block_height'] <=> (int) $b['block_height'])
                ?: strcmp(str_pad($a['revisionnumber'], 21, '0', STR_PAD_LEFT), str_pad($b['revisionnumber'], 21, '0', STR_PAD_LEFT));
            if ($cmp) return $sort === 'newest' ? -$cmp : $cmp;
            $version = (int) $a['v2'] <=> (int) $b['v2'];
            return $sort === 'newest' ? $version : -$version;
        });
        $unique = [];
        foreach ($rows as $row) $unique[$row['contract_id'] . ':' . $row['v2']] ??= $row;
        $items = array_slice(array_values($unique), 0, $size);
        $more = count($unique) > $size;
        $last = $items ? $items[count($items) - 1] : null;
        return ['items' => $items, 'pagination' => ['has_more' => $more, 'next_after' => $more
            ? implode(':', [$last['resolution_height'], $last['contract_id'], $last['block_height'], $last['revisionnumber'], $last['v2']]) : null]];
    }
}
