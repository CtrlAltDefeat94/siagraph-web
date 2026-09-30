<?php
namespace Siagraph\Services;

use mysqli;

class HostEstimatedEgress
{
    public static function query(mysqli $connection, string $database, string $publicKey): array
    {
        if ($database === '') {
            throw new \RuntimeException('Host statistics database is not configured.');
        }
        $schema = '`' . str_replace('`', '``', $database) . '`';
        $sql = <<<SQL
SELECT DATE_FORMAT(date, '%Y-%m') AS month,
       SUM(estimated_egress_gb) AS estimated_egress_gb,
       COUNT(estimated_egress_gb) AS days_with_estimates,
       SUM(estimated_egress_gb IS NULL) AS days_without_estimates,
       SUM(egress_unestimated_intervals) AS omitted_intervals
FROM {$schema}.HostsDailyStats
WHERE public_key = ? AND date >= '2025-07-01'
GROUP BY DATE_FORMAT(date, '%Y-%m')
ORDER BY month
SQL;
        $stmt = $connection->prepare($sql);
        try {
            $stmt->bind_param('s', $publicKey);
            $stmt->execute();
            return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        } finally {
            $stmt->close();
        }
    }
}
