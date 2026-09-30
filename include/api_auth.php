<?php

function siagraph_get_authorization_header(): string
{
    if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        return trim((string) $_SERVER['HTTP_AUTHORIZATION']);
    }

    if (isset($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
        return trim((string) $_SERVER['REDIRECT_HTTP_AUTHORIZATION']);
    }

    if (function_exists('apache_request_headers')) {
        $headers = apache_request_headers();
        foreach ($headers as $name => $value) {
            if (strtolower((string) $name) === 'authorization') {
                return trim((string) $value);
            }
        }
    }

    return '';
}

function siagraph_get_api_password_from_request(?array $jsonBody = null): string
{
    $authorization = siagraph_get_authorization_header();
    if (stripos($authorization, 'Bearer ') === 0) {
        return trim(substr($authorization, 7));
    }

    if (isset($_SERVER['HTTP_X_API_PASSWORD'])) {
        return trim((string) $_SERVER['HTTP_X_API_PASSWORD']);
    }

    if (isset($_GET['api_password'])) {
        return trim((string) $_GET['api_password']);
    }

    if (isset($_POST['api_password'])) {
        return trim((string) $_POST['api_password']);
    }

    if (is_array($jsonBody) && isset($jsonBody['api_password'])) {
        return trim((string) $jsonBody['api_password']);
    }

    return '';
}

function siagraph_is_api_password_authenticated(?array $jsonBody = null): bool
{
    global $SETTINGS;

    $expected = isset($SETTINGS['api_password']) ? (string) $SETTINGS['api_password'] : '';
    if ($expected === '') {
        return false;
    }

    $provided = siagraph_get_api_password_from_request($jsonBody);
    return $provided !== '' && hash_equals($expected, $provided);
}

function siagraph_require_api_password_auth(?array $jsonBody = null): void
{
    if (siagraph_is_api_password_authenticated($jsonBody)) {
        return;
    }

    http_response_code(401);
    exit;
}
