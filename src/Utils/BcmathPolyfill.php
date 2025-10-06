<?php
namespace Siagraph\Utils;

class BcmathPolyfill
{
    private static function normalize(string $num): string
    {
        $num = ltrim($num, '0');
        return $num === '' ? '0' : $num;
    }

    private static function compare(string $left, string $right): int
    {
        $left = self::normalize($left);
        $right = self::normalize($right);
        $len1 = strlen($left);
        $len2 = strlen($right);
        if ($len1 > $len2) {
            return 1;
        }
        if ($len1 < $len2) {
            return -1;
        }
        return strcmp($left, $right);
    }

    public static function add(string $left, string $right, int $scale = 0): string
    {
        if ($scale !== 0) {
            $factor = pow(10, $scale);
            $left = (string) round(((float)$left) * $factor);
            $right = (string) round(((float)$right) * $factor);
        }
        $carry = 0;
        $result = '';
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        while ($i >= 0 || $j >= 0 || $carry) {
            $a = $i >= 0 ? (int)$left[$i] : 0;
            $b = $j >= 0 ? (int)$right[$j] : 0;
            $sum = $a + $b + $carry;
            $carry = intdiv($sum, 10);
            $result = ($sum % 10) . $result;
            $i--; $j--;
        }
        if ($scale !== 0) {
            $result = str_pad($result, $scale + 1, '0', STR_PAD_LEFT);
            $int = substr($result, 0, -$scale);
            $frac = substr($result, -$scale);
            return self::normalize($int) . '.' . rtrim($frac, '0');
        }
        return self::normalize($result);
    }

    public static function sub(string $left, string $right, int $scale = 0): string
    {
        if ($scale !== 0) {
            $factor = pow(10, $scale);
            $left = (string) round(((float)$left) * $factor);
            $right = (string) round(((float)$right) * $factor);
        }
        $cmp = self::compare($left, $right);
        if ($cmp === 0) {
            return '0';
        }
        $neg = false;
        if ($cmp < 0) {
            $neg = true;
            [$left, $right] = [$right, $left];
        }
        $carry = 0;
        $result = '';
        $i = strlen($left) - 1;
        $j = strlen($right) - 1;
        while ($i >= 0) {
            $a = (int)$left[$i] - $carry;
            $b = $j >= 0 ? (int)$right[$j] : 0;
            if ($a < $b) {
                $a += 10;
                $carry = 1;
            } else {
                $carry = 0;
            }
            $diff = $a - $b;
            $result = $diff . $result;
            $i--; $j--;
        }
        $result = self::normalize($result);
        if ($scale !== 0) {
            $result = str_pad($result, $scale + 1, '0', STR_PAD_LEFT);
            $int = substr($result, 0, -$scale);
            $frac = substr($result, -$scale);
            $result = self::normalize($int) . '.' . rtrim($frac, '0');
        }
        return $neg ? '-' . $result : $result;
    }

    public static function div(string $left, string $right, int $scale = 0): string
    {
        if ($right === '0' || $right === '0.0') {
            return '0';
        }
        // optimized for powers of ten
        if (preg_match('/^1(0+)$/', $right, $m)) {
            $scale = $scale ?: strlen($m[1]);
            $left = self::normalize($left);
            if (strlen($left) <= $scale) {
                $left = str_pad($left, $scale + 1, '0', STR_PAD_LEFT);
            }
            $int = substr($left, 0, -$scale);
            $frac = substr($left, -$scale);
            return ($int === '' ? '0' : $int) . '.' . str_pad($frac, $scale, '0', STR_PAD_LEFT);
        }
        $val = (float)$left / (float)$right;
        return number_format($val, $scale, '.', '');
    }

    public static function mul(string $left, string $right, int $scale = 0): string
    {
        $val = (float)$left * (float)$right;
        return number_format($val, $scale, '.', '');
    }
}
