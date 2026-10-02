<?php

function alerts_v2_json_response(int $statusCode, array $payload): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode($payload);
}

function alerts_v2_wants_json(): bool
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    if (stripos($accept, 'application/json') !== false) {
        return true;
    }

    $format = isset($_GET['format']) ? strtolower((string) $_GET['format']) : '';
    return $format === 'json';
}

function alerts_v2_get_secret(array $settings): string
{
    if (!empty($settings['alerts_unsubscribe_secret']) && is_string($settings['alerts_unsubscribe_secret'])) {
        return $settings['alerts_unsubscribe_secret'];
    }

    $db = $settings['database'] ?? [];
    $seed = implode('|', [
        (string) ($settings['siagraph_base_url'] ?? ''),
        (string) ($db['username'] ?? ''),
        (string) ($db['password'] ?? ''),
        (string) ($db['database'] ?? ''),
    ]);

    return hash('sha256', $seed);
}

function alerts_v2_get_ttl_seconds(array $settings): int
{
    $ttl = (int) ($settings['alerts_unsubscribe_ttl_seconds'] ?? 31536000); // 365 days default
    return $ttl > 0 ? $ttl : 31536000;
}

function alerts_v2_generate_nonce(int $bytes = 16): string
{
    return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
}

function alerts_v2_create_signed_token(array $settings, ?int $now = null): string
{
    $ts = $now ?? time();
    $expiresAt = $ts + alerts_v2_get_ttl_seconds($settings);
    $nonce = alerts_v2_generate_nonce(16);
    $payload = 'v2.' . $expiresAt . '.' . $nonce;
    $sig = hash_hmac('sha256', $payload, alerts_v2_get_secret($settings));
    return $payload . '.' . $sig;
}

function alerts_v2_parse_token(string $token, array $settings, ?int $now = null): array
{
    $trimmed = trim($token);
    if ($trimmed === '') {
        return ['status' => 'missing'];
    }

    if (!preg_match('/^v2\.(\d{10})\.([A-Za-z0-9_-]{8,})\.([a-f0-9]{64})$/', $trimmed, $matches)) {
        return ['status' => 'invalid'];
    }

    $expiresAt = (int) $matches[1];
    $nonce = $matches[2];
    $sig = $matches[3];
    $payload = 'v2.' . $expiresAt . '.' . $nonce;
    $expectedSig = hash_hmac('sha256', $payload, alerts_v2_get_secret($settings));

    if (!hash_equals($expectedSig, $sig)) {
        return ['status' => 'invalid'];
    }

    $currentTs = $now ?? time();
    if ($expiresAt < $currentTs) {
        return ['status' => 'expired', 'expires_at' => gmdate('c', $expiresAt)];
    }

    return [
        'status' => 'valid',
        'expires_at' => gmdate('c', $expiresAt),
    ];
}

function alerts_v2_extract_token_from_request(): ?string
{
    if (isset($_GET['token']) && trim((string) $_GET['token']) !== '') {
        return trim((string) $_GET['token']);
    }

    $pathInfo = $_SERVER['PATH_INFO'] ?? '';
    if (is_string($pathInfo) && $pathInfo !== '') {
        $candidate = trim($pathInfo, '/');
        if ($candidate !== '') {
            return $candidate;
        }
    }

    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (is_string($requestUri) && preg_match('#/api/v2/alerts/subscriptions/([^/?]+)#', $requestUri, $m)) {
        return urldecode($m[1]);
    }

    return null;
}

function alerts_v2_render_html_page(string $title, string $message, string $tone = 'info'): void
{
    $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $border = '#64748b';
    if ($tone === 'success') {
        $border = '#16a34a';
    } elseif ($tone === 'warning') {
        $border = '#d97706';
    } elseif ($tone === 'danger') {
        $border = '#dc2626';
    }

    http_response_code(200);
    header('Content-Type: text/html; charset=UTF-8');
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $safeTitle . '</title>';
    echo '<style>body{font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#0f172a;color:#e2e8f0;margin:0;padding:2rem}';
    echo '.card{max-width:720px;margin:0 auto;background:#111827;border:1px solid ' . $border . ';border-radius:12px;padding:1.25rem 1rem}';
    echo 'h1{margin:0 0 .75rem;font-size:1.25rem}p{margin:0;line-height:1.5;color:#cbd5e1}</style></head><body>';
    echo '<div class="card"><h1>' . $safeTitle . '</h1><p>' . $safeMessage . '</p></div>';
    echo '</body></html>';
}

