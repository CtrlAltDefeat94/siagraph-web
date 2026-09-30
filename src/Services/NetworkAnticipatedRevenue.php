<?php
namespace Siagraph\Services;

use mysqli;

class NetworkAnticipatedRevenue
{
    public static function query(mysqli $connection, string $database): array
    {
        if ($database === '') {
            throw new \RuntimeException('Forecast database is not configured.');
        }
        $schema = '`' . str_replace('`', '``', $database) . '`';
        // Filter daily forecasts before grouping: earlier days in the current
        // month must not be counted as revenue still anticipated to unlock.
        $sql = <<<SQL
SELECT DATE_FORMAT(unlock_date, '%Y-%m-01') AS unlock_date,
       SUM(revenue_hastings) / 1000000000000000000000000 AS revenue_sc,
       SUM(contract_count) AS contract_count
FROM {$schema}.HostRevenueForecast
WHERE unlock_date >= ?
GROUP BY DATE_FORMAT(unlock_date, '%Y-%m-01')
ORDER BY unlock_date
SQL;
        $stmt = $connection->prepare($sql);
        try {
            $today = gmdate('Y-m-d');
            $stmt->bind_param('s', $today);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } finally {
            $stmt->close();
        }
    }
}
