<?php

function v2_query_string(string $key, ?string $default = null): ?string
{
    if (!isset($_GET[$key])) {
        return $default;
    }

    $value = trim((string) $_GET[$key]);
    if ($value === '') {
        return $default;
    }
    return $value;
}

function v2_query_int(string $key, ?int $default = null): ?int
{
    if (!isset($_GET[$key])) {
        return $default;
    }

    $value = filter_var($_GET[$key], FILTER_VALIDATE_INT);
    return $value === false ? $default : (int) $value;
}

function v2_query_bool(string $key, ?bool $default = null): ?bool
{
    if (!isset($_GET[$key])) {
        return $default;
    }

    $value = filter_var($_GET[$key], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
    return $value === null ? $default : $value;
}

function v2_normalize_date_input(?string $input): ?string
{
    if ($input === null || trim($input) === '') {
        return null;
    }

    $value = trim($input);
    $formats = ['Y-m-d', 'Y-m-d\\TH:i:s\\Z', DATE_ATOM];

    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value, new DateTimeZone('UTC'));
        if ($dt instanceof DateTime) {
            return $dt->format('Y-m-d\\TH:i:s\\Z');
        }
    }

    try {
        $dt = new DateTime($value, new DateTimeZone('UTC'));
        return $dt->format('Y-m-d\\TH:i:s\\Z');
    } catch (Exception $e) {
        return null;
    }
}

function v2_pagination_from_legacy(array $legacy): ?array
{
    if (!isset($legacy['pagination']) || !is_array($legacy['pagination'])) {
        return null;
    }

    $p = $legacy['pagination'];
    return [
        'page' => (int) ($p['current_page'] ?? 1),
        'per_page' => (int) ($p['per_page'] ?? 0),
        'total_items' => (int) ($p['total_rows'] ?? 0),
        'total_pages' => (int) ($p['total_pages'] ?? 0),
    ];
}
