<?php
namespace Siagraph\Utils;

/** Nonnegative decimal strings only. Never delegates to the float-based BCMath fallback. */
final class TaxDecimal
{
    private static function parts(string $value): array
    {
        if (!preg_match('/^\d+(?:\.\d+)?$/D', $value)) throw new \UnexpectedValueException('Invalid tax amount.');
        $parts = explode('.', $value);
        return [ltrim(implode('', $parts), '0') ?: '0', strlen($parts[1] ?? '')];
    }

    public static function scale(string $integer, int $scale): string
    {
        $integer = str_pad($integer, $scale + 1, '0', STR_PAD_LEFT);
        return $scale ? substr($integer, 0, -$scale) . '.' . substr($integer, -$scale) : $integer;
    }

    public static function add(string $a, string $b): string
    {
        [$a, $sa] = self::parts($a); [$b, $sb] = self::parts($b);
        $scale = max($sa, $sb);
        return self::scale(BcmathPolyfill::add($a . str_repeat('0', $scale-$sa), $b . str_repeat('0', $scale-$sb)), $scale);
    }

    public static function multiply(string $a, string $b): string
    {
        [$a, $sa] = self::parts($a); [$b, $sb] = self::parts($b);
        $result = '0';
        for ($i = 0; $i < strlen($b); $i++) {
            $result .= '0';
            for ($j = 0; $j < (int) $b[$i]; $j++) $result = BcmathPolyfill::add($result, $a);
        }
        return self::scale(ltrim($result, '0') ?: '0', $sa + $sb);
    }

    /** Arithmetic mean rounded half up to 24 fractional digits. */
    public static function average(string $sum, int $count): string
    {
        [$digits, $scale] = self::parts($sum);
        if ($count < 1 || $scale > 24) throw new \UnexpectedValueException('Invalid rate aggregate.');
        $digits .= str_repeat('0', 24-$scale);
        $quotient = ''; $remainder = 0;
        foreach (str_split($digits) as $digit) {
            $remainder = $remainder * 10 + (int) $digit;
            $quotient .= (string) intdiv($remainder, $count);
            $remainder %= $count;
        }
        $quotient = ltrim($quotient, '0') ?: '0';
        if ($remainder * 2 >= $count) $quotient = BcmathPolyfill::add($quotient, '1');
        return self::scale($quotient, 24);
    }
}
