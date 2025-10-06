<?php
use Siagraph\Utils\BcmathPolyfill;

if (!function_exists('bcadd')) {
    function bcadd(string $left, string $right, int $scale = 0): string
    {
        return BcmathPolyfill::add($left, $right, $scale);
    }
}

if (!function_exists('bcsub')) {
    function bcsub(string $left, string $right, int $scale = 0): string
    {
        return BcmathPolyfill::sub($left, $right, $scale);
    }
}

if (!function_exists('bcdiv')) {
    function bcdiv(string $left, string $right, int $scale = 0): string
    {
        return BcmathPolyfill::div($left, $right, $scale);
    }
}

if (!function_exists('bcmul')) {
    function bcmul(string $left, string $right, int $scale = 0): string
    {
        return BcmathPolyfill::mul($left, $right, $scale);
    }
}
