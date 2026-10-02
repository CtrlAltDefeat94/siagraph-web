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
        global $SETTINGS;

        $apiPassword = isset($SETTINGS['api_password']) ? (string) $SETTINGS['api_password'] : '';
        $headers = $apiPassword !== '' ? ['X-API-Password: ' . $apiPassword] : [];
        $query = [
            'public_key' => isset($payload['public_key']) ? (string) $payload['public_key'] : '',
        ];

        return V1BridgeService::request('scan_host', $query, 'POST', null, true, $headers);
    }
}
