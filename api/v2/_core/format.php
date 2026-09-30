<?php

function v2_snake_case_key(string $key): string
{
    $key = preg_replace('/([a-z])([A-Z])/', '$1_$2', $key);
    $key = str_replace(['-', ' '], '_', $key);
    return strtolower((string) $key);
}

function v2_normalize_keys_snake_case($value)
{
    if (is_array($value)) {
        $isList = array_keys($value) === range(0, count($value) - 1);
        $out = [];
        foreach ($value as $k => $v) {
            if ($isList) {
                $out[] = v2_normalize_keys_snake_case($v);
            } else {
                $out[v2_snake_case_key((string) $k)] = v2_normalize_keys_snake_case($v);
            }
        }
        return $out;
    }

    return $value;
}
