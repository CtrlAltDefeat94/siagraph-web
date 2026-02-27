<?php

namespace Siagraph\Services\V2;

class ExplorerService
{
    public static function metrics(): array
    {
        return V1BridgeService::request('explorer_metrics');
    }

    public static function peers(): array
    {
        return V1BridgeService::request('peers');
    }

    public static function transactions(array $query, bool $expectJson = true): array
    {
        return V1BridgeService::request('transactions.php', $query, 'GET', null, $expectJson);
    }
}
