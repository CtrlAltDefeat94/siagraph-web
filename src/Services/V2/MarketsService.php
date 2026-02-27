<?php

namespace Siagraph\Services\V2;

class MarketsService
{
    public static function exchangeRates(array $query): array
    {
        return V1BridgeService::request('daily/exchange_rate', $query);
    }
}
