<?php

namespace Siagraph\Services;

use Siagraph\Utils\Formatter;

class HostOverviewService
{
    /**
     * Build all derived views needed by host_overview.php
     *
     * @param array $hosts
     * @return array
     */
    public static function summarize(array $hosts): array
    {
        $locations = [];
        $versions = [];
        $countries = [];
        $storagePrices = [];
        $uploadPrices = [];
        $downloadPrices = [];
        $fullHosts = 0;

        foreach ($hosts as $host) {
            // Locations (lat,lng string)
            if (!empty($host['location'])) {
                $coords = explode(',', (string)$host['location']);
                if (count($coords) === 2) {
                    $locations[] = [
                        'lat' => (float) $coords[0],
                        'lng' => (float) $coords[1],
                        'country' => $host['country_name'] ?? null,
                    ];
                }
            }

            // Versions
            if (!empty($host['software_version'])) {
                $versions[$host['software_version']] = ($versions[$host['software_version']] ?? 0) + 1;
            }

            // Countries aggregate
            $countryName = (string) ($host['country_name'] ?? 'Unknown');
            if (!isset($countries[$countryName])) {
                $countries[$countryName] = [
                    'host_count'    => 0,
                    'used_storage'  => 0,
                    'total_storage' => 0,
                ];
            }
            $countries[$countryName]['host_count']++;
            $countries[$countryName]['used_storage'] += (int) ($host['used_storage'] ?? 0);
            $countries[$countryName]['total_storage'] += (int) ($host['total_storage'] ?? 0);

            // Full hosts (no remaining capacity)
            $total = (int) ($host['total_storage'] ?? 0);
            $used = (int) ($host['used_storage'] ?? 0);
            if ($total - $used <= 0 && $total > 0) {
                $fullHosts++;
            }

            // Pricing arrays (filter invalid or zero values)
            // Storage price: convert to SC/month per TB equivalent (existing logic kept: /1e12 * 4320)
            if (isset($host['storage_price']) && is_numeric($host['storage_price']) && (float)$host['storage_price'] > 0) {
                $storagePrices[] = ((float)$host['storage_price']) / pow(10, 12) * 4320;
            }
            if (isset($host['upload_price']) && is_numeric($host['upload_price']) && (float)$host['upload_price'] > 0) {
                $uploadPrices[] = ((float)$host['upload_price']) / pow(10, 12);
            }
            if (isset($host['download_price']) && is_numeric($host['download_price']) && (float)$host['download_price'] > 0) {
                $downloadPrices[] = ((float)$host['download_price']) / pow(10, 12);
            }
        }

        // Sort countries by used_storage desc for table consumption
        uasort($countries, function ($a, $b) {
            return ($b['used_storage'] <=> $a['used_storage']);
        });

        // Compute headline stats (exclude outliers)
        $stats = [
            'avg_storage_price'  => round(Formatter::calculateAverageExcludingOutliers($storagePrices), 1),
            'avg_upload_price'   => round(Formatter::calculateAverageExcludingOutliers($uploadPrices), 1),
            'avg_download_price' => round(Formatter::calculateAverageExcludingOutliers($downloadPrices), 1),
            'host_count'         => count($hosts),
            'full_hosts'         => $fullHosts,
        ];

        return [
            'locations'      => $locations,
            'versions'       => $versions,
            'countries'      => $countries,
            'storage_prices' => $storagePrices,
            'upload_prices'  => $uploadPrices,
            'download_prices'=> $downloadPrices,
            'stats'          => $stats,
        ];
    }
}

