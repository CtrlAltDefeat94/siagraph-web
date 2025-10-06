<?php
namespace Siagraph\Utils;

use Locale as PhpLocale;
use NumberFormatter;

class Locale
{
    private static string $locale = 'en_US';
    private static array $formatters = [];

    public static function init(): void
    {
        // 1) Explicit override via query param
        $param = $_GET['locale'] ?? null;
        if (is_string($param) && $param !== '') {
            self::setLocale(self::normalize($param));
            return;
        }

        // 2) Cached cookie
        if (!empty($_COOKIE['locale'])) {
            self::setLocale(self::normalize($_COOKIE['locale']));
            return;
        }

        // 3) Accept-Language header
        $detect = self::detectFromAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') ?? 'en_US';
        self::setLocale($detect);
    }

    private static function detectFromAcceptLanguage(string $header): ?string
    {
        $header = trim($header);
        if ($header === '') return null;

        // Prefer intl Locale processing when available
        if (class_exists(PhpLocale::class)) {
            $acc = PhpLocale::acceptFromHttp($header);
            if ($acc) return self::normalize($acc);
        }

        // Fallback: take first language tag
        $parts = explode(',', $header);
        if (count($parts) === 0) return null;
        return self::normalize(trim(explode(';', $parts[0])[0]));
    }

    private static function normalize(string $locale): string
    {
        $locale = str_replace('-', '_', trim($locale));
        if ($locale === '') return 'en_US';
        // Ensure region if only language is provided
        if (strpos($locale, '_') === false) {
            // Map common language-only locales to sensible defaults
            $map = [
                'en' => 'en_US',
                'de' => 'de_DE',
                'fr' => 'fr_FR',
                'es' => 'es_ES',
                'it' => 'it_IT',
                'pt' => 'pt_PT',
                'zh' => 'zh_CN',
                'ja' => 'ja_JP',
                'ko' => 'ko_KR',
                'ru' => 'ru_RU',
            ];
            return $map[strtolower($locale)] ?? 'en_US';
        }
        return $locale;
    }

    private static function setLocale(string $locale): void
    {
        self::$locale = $locale;
        // Persist for ~1 year
        // Suppress cookie warnings if headers already sent; best-effort caching.
        if (!headers_sent()) {
            @setcookie('locale', $locale, [
                'expires' => time() + 31536000,
                'path' => '/',
                'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        // Also apply to PHP locale for fallbacks
        @setlocale(LC_ALL, $locale . '.UTF-8', $locale, 'C');
        self::$formatters = []; // reset cache
    }

    public static function get(): string
    {
        return self::$locale;
    }   

    private static function getFormatter(int $style = NumberFormatter::DECIMAL, ?int $fractionDigits = null): ?NumberFormatter
    {
        if (!class_exists(NumberFormatter::class)) {
            return null;
        }
        $key = $style . '|' . ($fractionDigits === null ? 'null' : $fractionDigits);
        if (!isset(self::$formatters[$key])) {
            $fmt = new NumberFormatter(self::$locale, $style);
            if ($fractionDigits !== null) {
                $fmt->setAttribute(NumberFormatter::FRACTION_DIGITS, $fractionDigits);
            }
            self::$formatters[$key] = $fmt;
        }
        return self::$formatters[$key];
    }

    public static function integer($value): string
    {
        return self::decimal($value, 0);
    }

    public static function decimal($value, int $fractionDigits = 2): string
    {
        $value = is_numeric($value) ? (float)$value : 0.0;
        $fmt = self::getFormatter(NumberFormatter::DECIMAL, $fractionDigits);
        if ($fmt) {
            $out = $fmt->format($value);
            if ($out !== false) return $out;
        }
        // Fallback to localeconv + number_format
        $conv = localeconv();
        $dec = $conv['decimal_point'] ?: '.';
        $th = $conv['thousands_sep'] ?: ',';
        return number_format($value, $fractionDigits, $dec, $th);
    }

    public static function currency($value, string $currencyCode = 'USD', ?int $fractionDigits = null): string
    {
        $value = is_numeric($value) ? (float)$value : 0.0;
        $digits = $fractionDigits ?? 2;
        $fmt = self::getFormatter(NumberFormatter::CURRENCY, $digits);
        if ($fmt) {
            $out = $fmt->formatCurrency($value, strtoupper($currencyCode));
            if ($out !== false) return $out;
        }
        // Fallback: keep existing UI pattern 'USD 1,234.56'
        return strtoupper($currencyCode) . ' ' . self::decimal($value, $digits);
    }

    public static function signedDecimal($value, int $fractionDigits = 0): string
    {
        $num = is_numeric($value) ? (float)$value : 0.0;
        $formatted = self::decimal(abs($num), $fractionDigits);
        if ($num < 0) return '-' . $formatted;
        return '+' . $formatted;
    }

    public static function date(string $isoDate): string
    {
        $ts = strtotime($isoDate);
        if ($ts === false) return $isoDate;
        if (class_exists(NumberFormatter::class)) {
            // Use IntlDateFormatter if intl present
            $fmt = new \IntlDateFormatter(self::$locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
            $out = $fmt->format($ts);
            if ($out !== false) return $out;
        }
        // Fallback to YYYY-MM-DD
        return date('Y-m-d', $ts);
    }
}
