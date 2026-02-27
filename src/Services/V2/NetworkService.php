<?php

namespace Siagraph\Services\V2;

class NetworkService
{
    public static function metrics(string $interval, array $query): array
    {
        $path = $interval === 'month' ? 'monthly/metrics' : 'daily/metrics';
        return V1BridgeService::request($path, $query);
    }

    public static function aggregates(string $interval, array $query): array
    {
        $path = $interval === 'month' ? 'monthly/aggregates' : 'daily/aggregates';
        return V1BridgeService::request($path, $query);
    }

    public static function growth(string $interval, array $query): array
    {
        $path = $interval === 'month' ? 'monthly/growth' : 'daily/growth';
        return V1BridgeService::request($path, $query);
    }

    public static function compareMetrics(array $query): array
    {
        return V1BridgeService::request('daily/compare_metrics', $query);
    }

    public static function hostPrices(array $query): array
    {
        return V1BridgeService::request('daily/host_prices', $query);
    }

    public static function monthlyRevenue(array $query): array
    {
        return V1BridgeService::request('monthly/revenue', $query);
    }

    public static function storageAth(): array
    {
        return V1BridgeService::request('storage/ath');
    }

    public static function renterDistribution(array $query): array
    {
        return V1BridgeService::request('storage/renter_distribution', $query);
    }
}
