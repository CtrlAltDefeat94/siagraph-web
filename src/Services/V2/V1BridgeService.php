<?php

namespace Siagraph\Services\V2;

class V1BridgeService
{
    private static function baseUrl(): string
    {
        global $SETTINGS;

        $configured = isset($SETTINGS['siagraph_base_url']) ? trim((string) $SETTINGS['siagraph_base_url']) : '';
        if ($configured !== '') {
            return rtrim($configured, '/');
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $scheme . '://' . $host;
    }

    public static function request(string $v1Path, array $query = [], string $method = 'GET', ?array $jsonBody = null, bool $expectJson = true): array
    {
        $url = self::baseUrl() . '/api/v1/' . ltrim($v1Path, '/');
        if (!empty($query)) {
            $url .= '?' . http_build_query($query);
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 20);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

        $headers = [];
        if ($jsonBody !== null) {
            $payload = json_encode($jsonBody);
            $headers[] = 'Content-Type: application/json';
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }

        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        if ($errno !== 0) {
            return [
                'ok' => false,
                'status' => 502,
                'error' => 'Bridge request failed: ' . $error,
                'raw' => null,
                'json' => null,
                'content_type' => $contentType,
            ];
        }

        if (!$expectJson) {
            return [
                'ok' => $status >= 200 && $status < 300,
                'status' => $status,
                'error' => null,
                'raw' => $raw,
                'json' => null,
                'content_type' => $contentType,
            ];
        }

        $decoded = null;
        if (is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true, 512, JSON_BIGINT_AS_STRING);
        }

        if (!is_array($decoded)) {
            return [
                'ok' => false,
                'status' => 502,
                'error' => 'Invalid JSON response from V1 endpoint.',
                'raw' => $raw,
                'json' => null,
                'content_type' => $contentType,
            ];
        }

        return [
            'ok' => $status >= 200 && $status < 300,
            'status' => $status,
            'error' => null,
            'raw' => $raw,
            'json' => $decoded,
            'content_type' => $contentType,
        ];
    }
}
