<?php
namespace Siagraph\Services;

use Siagraph\Utils\TaxDecimal as Decimal;

final class ContractTaxExport
{
    public const COMPLETION_START = '2025-07-01';
    public const CURRENCIES = ['btc', 'cad', 'cny', 'eth', 'eur', 'gbp', 'jpy', 'rub', 'usd'];
    public const AMOUNTS = ['revenue_rolled', 'revenue_unlocked', 'revenue_forfeited', 'collateral_lost', 'protocol_burned'];
    public const FIAT = ['revenue' => 'revenue_unlocked', 'collateral_lost' => 'collateral_lost'];

    public function __construct(private \mysqli $db, private string $rawDatabase)
    {
        if ($rawDatabase === '') throw new \RuntimeException('Raw database is not configured.');
    }

    public static function parameters(array $input, ?\DateTimeImmutable $now = null): array
    {
        $now = ($now ?? new \DateTimeImmutable('now'))->setTimezone(new \DateTimeZone('UTC'));
        $defaults = ['from'=>self::COMPLETION_START, 'to'=>$now->modify('-1 day')->format('Y-m-d'), 'format'=>'json', 'currency'=>'eur', 'host_public_key'=>''];
        $p = [];
        foreach ($defaults as $name => $default) {
            $value = $input[$name] ?? $default;
            if (!is_string($value)) throw new \InvalidArgumentException('Invalid ' . $name . '.');
            $p[$name] = trim($value);
        }
        foreach (['from', 'to'] as $name) {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $p[$name], new \DateTimeZone('UTC'));
            if (!$date || $date->format('Y-m-d') !== $p[$name] || $p[$name] < self::COMPLETION_START) throw new \InvalidArgumentException($name . ' must be a UTC date on or after ' . self::COMPLETION_START . '.');
        }
        if ($p['from'] > $p['to']) throw new \InvalidArgumentException('from must not follow to.');
        if (!in_array($p['format'], ['json','csv'], true)) throw new \InvalidArgumentException('format must be json or csv.');
        $p['currency'] = strtolower($p['currency']);
        if (!in_array($p['currency'], self::CURRENCIES, true)) throw new \InvalidArgumentException('Unsupported currency.');
        [, $p['host_public_key']] = ActiveContracts::identity('', $p['host_public_key']);
        $p['today'] = $now->format('Y-m-d');
        $p['until'] = (new \DateTimeImmutable($p['to'], new \DateTimeZone('UTC')))->modify('+1 day')->format('Y-m-d');
        $p['generated_at'] = $now->format('Y-m-d\TH:i:s\Z');
        return $p;
    }

    private function table(string $name): string
    {
        return '`' . str_replace('`', '``', $this->rawDatabase) . '`.`' . $name . '`';
    }

    public function chainHeight(): int
    {
        return (int) $this->db->query('SELECT COALESCE(MAX(block_height), 0) FROM ' . $this->table('BlockTime'))->fetch_row()[0];
    }

    public function sql(string $currency): string
    {
        // The only interpolated column is from this fixed whitelist, never raw request input.
        if (!in_array($currency, self::CURRENCIES, true)) throw new \InvalidArgumentException('Unsupported currency.');
        $contracts = $this->table('Contracts'); $blocks = $this->table('BlockTime');
        return "WITH resolved AS (
            SELECT c.*, ROW_NUMBER() OVER (PARTITION BY contract_id ORDER BY
                (block_height = resolution_height) DESC, block_height DESC, revisionnumber DESC) AS resolution_rank
            FROM $contracts c
            WHERE v2 = 1 AND host_public_key IN (?, ?) AND resolution_type IS NOT NULL
              AND resolution_height IS NOT NULL AND block_height <= ?
        ), successors AS (
            SELECT renewed_from_contract_id, contract_id,
                ROW_NUMBER() OVER (PARTITION BY renewed_from_contract_id
                    ORDER BY confirmation_height, block_height, revisionnumber) AS successor_rank
            FROM $contracts
            WHERE v2 = 1 AND host_public_key IN (?, ?)
              AND renewed_from_contract_id IS NOT NULL AND block_height <= ?
        ), daily_rates AS (
            SELECT DATE(timestamp) AS rate_date,
                SUM(CASE WHEN $currency IS NOT NULL AND $currency > 0 THEN $currency END) AS rate_sum,
                COUNT(CASE WHEN $currency IS NOT NULL AND $currency > 0 THEN 1 END) AS observations
            FROM ExchangeRates
            WHERE currency_code = ? AND timestamp >= ? AND timestamp < ?
            GROUP BY DATE(timestamp)
        )
        SELECT c.contract_id, successor.contract_id AS successor_contract_id,
            c.resolution_type, c.confirmation_height, c.resolution_height, c.resolution_height + 144 AS settlement_height,
            DATE(confirmed.timestamp) AS start_date_utc, DATE(resolved_at.timestamp) AS end_date_utc,
            DATE(maturity.timestamp) AS settlement_date_utc, c.resolution_transaction_id,
            GREATEST(c.revenue_unlocked, 0) AS revenue_unlocked_hastings,
            CASE WHEN c.resolution_type = 'renewal' THEN GREATEST(c.revenue_locked, 0) ELSE 0 END AS revenue_rolled_hastings,
            CASE WHEN c.resolution_type = 'expiration' THEN GREATEST(c.revenue_locked, 0) ELSE 0 END AS revenue_forfeited_hastings,
            CASE WHEN c.resolution_type = 'expiration' THEN GREATEST(c.burned_host_output, 0) ELSE 0 END AS protocol_burned_hastings,
            CASE WHEN c.resolution_type = 'expiration' THEN GREATEST(c.host_balance - c.missed_host_output, 0) ELSE 0 END AS collateral_lost_hastings,
            r.rate_sum, r.observations
        FROM resolved c
        JOIN $blocks maturity ON maturity.block_height = c.resolution_height + 144
        LEFT JOIN $blocks confirmed ON confirmed.block_height = c.confirmation_height
        JOIN $blocks resolved_at ON resolved_at.block_height = c.resolution_height
        LEFT JOIN successors successor ON successor.renewed_from_contract_id = c.contract_id
            AND successor.successor_rank = 1
        LEFT JOIN daily_rates r ON r.rate_date = DATE(maturity.timestamp)
        WHERE c.resolution_rank = 1 AND maturity.block_height <= ?
            AND resolved_at.timestamp >= '" . self::COMPLETION_START . " 00:00:00'
            AND maturity.timestamp >= ? AND maturity.timestamp < ?
        ORDER BY maturity.timestamp, c.contract_id";
    }

    /** Prepared, unbuffered fetch: CSV does not accumulate contract rows in PHP memory. */
    public function rows(array $p, int $height): \Generator
    {
        $stmt = $this->db->prepare($this->sql($p['currency']));
        try {
            $stmt->execute([$p['host_public_key'], 'ed25519:' . $p['host_public_key'], $height,
                $p['host_public_key'], 'ed25519:' . $p['host_public_key'], $height,
                'sc', $p['from'], $p['until'], $height, $p['from'], $p['until']]);
            $fields = $stmt->result_metadata()->fetch_fields();
            $values = array_fill(0, count($fields), null); $refs = [];
            foreach ($values as &$value) $refs[] = &$value;
            unset($value);
            $stmt->bind_result(...$refs);
            while ($stmt->fetch()) {
                $row = [];
                foreach ($fields as $i => $field) $row[$field->name] = $values[$i];
                yield self::formatRow($row, $p);
            }
        } finally { $stmt->close(); }
    }

    public static function formatRow(array $row, array $p): array
    {
        if (!in_array($row['resolution_type'], ['storage_proof', 'renewal', 'expiration'], true)) throw new \UnexpectedValueException('Unknown resolution type.');
        foreach (self::AMOUNTS as $amount) {
            $value = $row[$amount . '_hastings'];
            if (!is_string($value) || !preg_match('/^\d+$/D', $value)) throw new \UnexpectedValueException('Missing or invalid contract amount.');
            $row[$amount . '_sc'] = Decimal::scale($value, 24);
        }
        $currency = $p['currency'];
        $observations = (int) ($row['observations'] ?? 0);
        $price = $observations > 0 ? Decimal::average($row['rate_sum'], $observations) : null;
        $row['sc_price_' . $currency] = $price;
        $row['exchange_rate_status'] = $price === null ? 'missing' : 'available';
        foreach (self::FIAT as $field => $amount) $row[$field . '_' . $currency] = $price === null ? null : Decimal::multiply($row[$amount . '_sc'], $price);
        $row['burn_reconciliation_valid'] = $row['resolution_type'] === 'expiration'
            ? Decimal::add($row['revenue_forfeited_hastings'], $row['collateral_lost_hastings']) === $row['protocol_burned_hastings'] : null;
        unset($row['rate_sum'], $row['observations']);
        return $row;
    }

    /** Public row shape shared by JSON and CSV; detailed amounts remain available for the summary. */
    public static function exportRow(array $row, string $currency): array
    {
        $price = $row['sc_price_' . $currency];
        return [
            'contract_id' => $row['contract_id'],
            'successor_contract_id' => $row['successor_contract_id'],
            'start_date' => $row['start_date_utc'],
            'resolution_date' => $row['end_date_utc'],
            'payout_maturity_date' => $row['settlement_date_utc'],
            'resolution_type' => $row['resolution_type'],
            'revenue_rolled_sc' => $row['revenue_rolled_sc'],
            'revenue_unlocked_sc' => $row['revenue_unlocked_sc'],
            'revenue_forfeited_sc' => $row['revenue_forfeited_sc'],
            'collateral_lost_sc' => $row['collateral_lost_sc'],
            'sc_price_' . $currency => $price === null ? null : (rtrim(rtrim($price, '0'), '.') ?: '0'),
            'resolution_transaction_id' => $row['resolution_transaction_id'],
        ];
    }

    public static function columns(string $currency): array
    {
        return ['contract_id', 'successor_contract_id', 'start_date', 'resolution_date',
            'payout_maturity_date', 'resolution_type', 'revenue_rolled_sc', 'revenue_unlocked_sc',
            'revenue_forfeited_sc', 'collateral_lost_sc',
            'sc_price_' . $currency, 'resolution_transaction_id'];
    }

    public static function csvRow($stream, array $row, string $currency): void
    {
        $row = self::exportRow($row, $currency);
        $values = [];
        foreach (self::columns($currency) as $column) {
            $value = $row[$column];
            $values[] = is_bool($value) ? ($value ? 'true' : 'false') : $value;
        }
        fputcsv($stream, $values, ',', '"', '', "\r\n");
    }

    public static function summary(array $rows, array $p, int $height): array
    {
        $summary = ['contracts'=>count($rows), 'successful_contracts'=>0, 'renewed_contracts'=>0, 'failed_contracts'=>0,
            'rows_without_exchange_rate'=>0, 'requested_maturity_period'=>['from'=>$p['from'], 'to'=>$p['to']],
            'completion_cutoff'=>self::COMPLETION_START,
            'rate_method'=>'arithmetic mean of all valid SiaGraph price observations recorded for the UTC date', 'currency'=>strtoupper($p['currency']),
            'chain_height'=>$height, 'generated_at'=>$p['generated_at']];
        foreach (['revenue' => 'revenue_unlocked', 'collateral_lost' => 'collateral_lost'] as $field => $_) foreach (['sc', $p['currency']] as $unit) $summary['total_' . $field . '_' . $unit] = '0';
        foreach ($rows as $row) {
            $summary[match ($row['resolution_type']) {'storage_proof'=>'successful_contracts', 'renewal'=>'renewed_contracts', 'expiration'=>'failed_contracts'}]++;
            if ($row['exchange_rate_status'] === 'missing') $summary['rows_without_exchange_rate']++;
            foreach (['revenue' => 'revenue_unlocked', 'collateral_lost' => 'collateral_lost'] as $field => $amount) {
                foreach (['sc', $p['currency']] as $unit) {
                    $key = 'total_' . $field . '_' . $unit;
                    $value = $row[($unit === 'sc' ? $amount : $field) . '_' . $unit];
                    $summary[$key] = $value === null || $summary[$key] === null ? null : Decimal::add($summary[$key], $value);
                }
            }
        }
        return $summary;
    }
}
