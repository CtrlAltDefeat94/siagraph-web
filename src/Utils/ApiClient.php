<?php
namespace Siagraph\Utils;

use Exception;

class ApiClient
{
    /**
     * Fetch JSON data from the given endpoint.
     *
     * @param string $endpoint Relative or absolute URL endpoint
     * @param bool $useCache Whether to cache the response
     * @param string $cacheLifetimeOption Cache lifetime option ('hour' or 'day')
     * @return array|null Decoded JSON data or null on failure
     */
    public static function fetchJson(string $endpoint, bool $useCache = true, string $cacheLifetimeOption = 'hour'): ?array
    {
        global $SETTINGS;

        $baseUrl = $SETTINGS['siagraph_base_url'] ?? '';
        $url = preg_match('#^https?://#', $endpoint) ? $endpoint : $baseUrl . $endpoint;
        $cacheKey = 'api_' . md5($url);

        if ($useCache) {
            $cached = Cache::getCache($cacheKey);
            if ($cached !== null) {
                $decoded = json_decode($cached, true, 512, JSON_BIGINT_AS_STRING);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        $context = stream_context_create(['http' => ['timeout' => 10]]);
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            $response = curl_exec($ch);
            if ($response === false) {
                error_log('API request failed: ' . curl_error($ch));
                curl_close($ch);
                return null;
            }
            curl_close($ch);
        }

        $decoded = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);
        if (!is_array($decoded)) {
            return null;
        }

        if ($useCache) {
            try {
                Cache::setCache($response, $cacheKey, $cacheLifetimeOption);
            } catch (Exception $e) {
                // ignore cache errors
            }
        }

        return $decoded;
    }
}
