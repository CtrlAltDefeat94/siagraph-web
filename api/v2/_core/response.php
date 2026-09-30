<?php

function v2_meta_base(array $sources = [], bool $partialData = false): array
{
    return [
        'api_version' => 'v2',
        'generated_at' => gmdate('c'),
        'partial_data' => $partialData,
        'sources' => array_values(array_unique($sources)),
    ];
}

function v2_json_success($data, array $meta = [], int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode([
        'data' => $data,
        'meta' => $meta,
        'errors' => [],
    ]);
}

function v2_json_error(string $code, string $message, int $status = 400, ?string $field = null, array $details = [], array $meta = []): void
{
    http_response_code($status);
    header('Content-Type: application/json');

    $error = [
        'code' => $code,
        'message' => $message,
        'field' => $field,
        'details' => (object) $details,
    ];

    echo json_encode([
        'data' => null,
        'meta' => $meta,
        'errors' => [$error],
    ]);
}

function v2_json_from_legacy_payload($legacyPayload, array $sources = [], bool $partialData = false, ?array $pagination = null, array $extraMeta = []): void
{
    $meta = array_merge(v2_meta_base($sources, $partialData), $extraMeta);
    if ($pagination !== null) {
        $meta['pagination'] = $pagination;
    }

    v2_json_success($legacyPayload, $meta);
}
