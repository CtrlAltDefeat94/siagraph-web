<?php
namespace Siagraph\Utils;

class CurrencyDisplay
{
    private static ?array $spotRates = null;

    public static function selectedCurrency(): string
    {
        $raw = isset($_COOKIE['currency']) ? strtolower((string) $_COOKIE['currency']) : 'eur';
        return in_array($raw, ['sc', 'usd', 'eur'], true) ? $raw : 'eur';
    }

    /**
     * @param array{
     *   scValue?: mixed,
     *   fiatValue?: mixed,
     *   currency?: string,
     *   date?: string|null,
     *   ratesByDate?: array<string,array<string,float>>,
     *   decimals?: int,
     *   scDecimals?: int,
     *   suffix?: string,
     *   nullText?: string
     * } $opts
     */
    public static function formatMonetary(array $opts): string
    {
        $currency = isset($opts['currency']) && is_string($opts['currency'])
            ? strtolower($opts['currency'])
            : self::selectedCurrency();
        if (!in_array($currency, ['sc', 'usd', 'eur'], true)) {
            $currency = 'eur';
        }

        $scValue = self::toFloatOrNull($opts['scValue'] ?? null);
        $fiatValue = self::toFloatOrNull($opts['fiatValue'] ?? null);
        $date = isset($opts['date']) && is_string($opts['date']) ? $opts['date'] : null;
        $ratesByDate = isset($opts['ratesByDate']) && is_array($opts['ratesByDate']) ? $opts['ratesByDate'] : [];
        $decimals = isset($opts['decimals']) ? (int) $opts['decimals'] : 2;
        $scDecimals = isset($opts['scDecimals']) ? (int) $opts['scDecimals'] : $decimals;
        $suffix = isset($opts['suffix']) ? (string) $opts['suffix'] : '';
        $nullText = isset($opts['nullText']) ? (string) $opts['nullText'] : 'N/A';

        if ($scValue === null && $fiatValue === null) {
            return $nullText;
        }

        if ($currency === 'sc') {
            if ($scValue === null) {
                return $nullText;
            }
            return self::formatSc($scValue, $scDecimals) . $suffix;
        }

        if ($fiatValue === null && $scValue !== null) {
            $rate = self::resolveRate($currency, $date, $ratesByDate);
            if ($rate !== null) {
                $fiatValue = $scValue * $rate;
            }
        }

        if ($fiatValue === null) {
            if ($scValue === null) {
                return $nullText;
            }
            return self::formatSc($scValue, $scDecimals) . $suffix;
        }

        $fiatText = strtoupper($currency) . ' ' . Locale::decimal($fiatValue, $decimals) . $suffix;
        if ($scValue === null) {
            return htmlspecialchars($fiatText, ENT_QUOTES, 'UTF-8');
        }

        $title = 'SC value: ' . self::formatSc($scValue, $scDecimals) . $suffix;
        return '<span title="' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '">'
            . htmlspecialchars($fiatText, ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    public static function loadDailyRates(string $startIso, string $endIso): array
    {
        $start = self::dateOnly($startIso);
        $end = self::dateOnly($endIso);
        if ($start === '' || $end === '') {
            return [];
        }

        $endpoint = '/api/v1/daily/exchange_rate?start=' . rawurlencode($start . 'T00:00:00Z')
            . '&end=' . rawurlencode($end . 'T23:59:59Z');
        $rows = ApiClient::fetchJson($endpoint, true, 'day');
        if (!is_array($rows)) {
            return [];
        }

        $rates = [];
        foreach ($rows as $row) {
            if (!is_array($row) || !isset($row['date'])) {
                continue;
            }
            $key = self::dateOnly((string) $row['date']);
            if ($key === '') {
                continue;
            }
            $usd = isset($row['usd']) && is_numeric($row['usd']) ? (float) $row['usd'] : null;
            $eur = isset($row['eur']) && is_numeric($row['eur']) ? (float) $row['eur'] : null;
            $rates[$key] = ['usd' => $usd, 'eur' => $eur];
        }

        return $rates;
    }

    private static function resolveRate(string $currency, ?string $date, array $ratesByDate): ?float
    {
        if ($date !== null) {
            $key = self::dateOnly($date);
            if ($key !== '' && isset($ratesByDate[$key][$currency]) && is_numeric($ratesByDate[$key][$currency])) {
                return (float) $ratesByDate[$key][$currency];
            }
        }

        $spot = self::spotRates();
        if (isset($spot[$currency]) && is_numeric($spot[$currency])) {
            $value = (float) $spot[$currency];
            return $value > 0 ? $value : null;
        }

        return null;
    }

    private static function spotRates(): array
    {
        if (self::$spotRates !== null) {
            return self::$spotRates;
        }

        $payload = ApiClient::fetchJson('/api/v1/daily/compare_metrics', true, 'hour');
        $rates = ['usd' => null, 'eur' => null];
        if (is_array($payload) && isset($payload['actual']['coin_price']) && is_array($payload['actual']['coin_price'])) {
            $coinPrice = $payload['actual']['coin_price'];
            foreach (['usd', 'eur'] as $code) {
                if (isset($coinPrice[$code]) && is_numeric($coinPrice[$code])) {
                    $rates[$code] = (float) $coinPrice[$code];
                }
            }
        }

        self::$spotRates = $rates;
        return $rates;
    }

    private static function toFloatOrNull($value): ?float
    {
        if ($value === null) {
            return null;
        }
        if (is_array($value)) {
            if (isset($value['sc']) && is_numeric($value['sc'])) {
                return (float) $value['sc'];
            }
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (float) $value;
    }

    private static function formatSc(float $value, int $decimals): string
    {
        return Locale::decimal($value, $decimals) . ' SC';
    }

    private static function dateOnly(string $value): string
    {
        $trimmed = trim($value);
        if ($trimmed === '') {
            return '';
        }
        return substr($trimmed, 0, 10);
    }
}
