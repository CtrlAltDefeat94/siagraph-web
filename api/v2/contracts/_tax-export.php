<?php
require_once __DIR__ . '/../_core/response.php';
require_once __DIR__ . '/../../../vendor/autoload.php';

use Siagraph\Services\ContractTaxExport;
use Siagraph\Database\Database;

function contract_tax_export_endpoint(?callable $initialize = null): void
{
    global $SETTINGS;
    $csvStarted = false;
    header('Cache-Control: no-store');
    try {
        if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
            header('Allow: GET');
            v2_json_error('method_not_allowed', 'Use GET.', 405); return;
        }
        $p = ContractTaxExport::parameters($_GET);
        if ($initialize) $initialize();
        else {
            require __DIR__ . '/../../../include/config.php';
            Database::initialize($SETTINGS['database'] + ['connect_timeout'=>5, 'read_timeout'=>30]);
        }
        $service = new ContractTaxExport(Database::getConnection(), (string) ($SETTINGS['database']['raw_database'] ?? ''));
        $height = $service->chainHeight();
        $rows = $service->rows($p, $height);
        // Start the SELECT before sending CSV headers, so query failures retain the JSON error envelope.
        $rows->rewind();
        if ($p['format'] === 'csv') {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="host-revenue-' . $p['host_public_key'] . '-' . $p['from'] . '-' . $p['to'] . '-' . $p['currency'] . '.csv"');
            $stream = fopen('php://output', 'wb');
            $csvStarted = true;
            fputcsv($stream, ContractTaxExport::columns($p['currency']), ',', '"', '', "\r\n");
            foreach ($rows as $row) ContractTaxExport::csvRow($stream, $row, $p['currency']);
            fclose($stream);
            return;
        }
        $items = iterator_to_array($rows, false);
        $summary = ContractTaxExport::summary($items, $p, $height);
        $meta = v2_meta_base(['Contracts', 'BlockTime', 'ExchangeRates'], $summary['rows_without_exchange_rate'] > 0);
        v2_json_success(['items'=>array_map(static fn(array $row): array => ContractTaxExport::exportRow($row, $p['currency']), $items), 'summary'=>$summary], $meta);
    } catch (InvalidArgumentException $e) {
        v2_json_error('invalid_parameter', $e->getMessage(), 400);
    } catch (Throwable $e) {
        error_log('Contract tax export: ' . $e->getMessage());
        if (!$csvStarted) v2_json_error('unavailable', 'Contract tax export is temporarily unavailable. Please retry.', 503);
        // Never append a JSON error to a partially transmitted CSV.
    }
}
