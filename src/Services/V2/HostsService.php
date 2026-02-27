<?php

namespace Siagraph\Services\V2;

class HostsService
{
    public static function list(array $query): array
    {
        return V1BridgeService::request('hosts', $query);
    }

    public static function meta(): array
    {
        return V1BridgeService::request('hosts', ['meta' => '1']);
    }

    public static function details(array $query): array
    {
        return V1BridgeService::request('host', $query);
    }

    public static function troubleshoot(array $query): array
    {
        return V1BridgeService::request('host_troubleshooter', $query);
    }

    public static function scan(array $payload): array
    {
        return V1BridgeService::request('scan_host', [], 'POST', $payload);
    }
}
