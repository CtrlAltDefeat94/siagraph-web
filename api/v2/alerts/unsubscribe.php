<?php
include_once "../../../bootstrap.php";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../_core/response.php";

$token = alerts_v2_extract_token_from_request();
if ($token === null || $token === '') {
    if (alerts_v2_wants_json()) {
        v2_json_error('missing_token', 'Missing token.', 400, 'token', [], v2_meta_base(['alerts_unsubscribe_v2']));
        exit;
    }
    alerts_v2_render_html_page(
        'Unsubscribe failed',
        'Missing unsubscribe token in the request.',
        'danger'
    );
    exit;
}

$tokenCheck = alerts_v2_parse_token($token, $SETTINGS);
if ($tokenCheck['status'] === 'expired') {
    if (alerts_v2_wants_json()) {
        v2_json_error('expired_token', 'Token expired.', 410, 'token', [
            'status' => 'expired',
            'expires_at' => $tokenCheck['expires_at'] ?? null,
        ], v2_meta_base(['alerts_unsubscribe_v2']));
        exit;
    }
    alerts_v2_render_html_page(
        'Unsubscribe link expired',
        'This unsubscribe link has expired. Please create a new subscription to receive a fresh link.',
        'warning'
    );
    exit;
}

if ($tokenCheck['status'] !== 'valid') {
    if (alerts_v2_wants_json()) {
        v2_json_error('invalid_token', 'Invalid token.', 404, 'token', [
            'status' => 'invalid',
        ], v2_meta_base(['alerts_unsubscribe_v2']));
        exit;
    }
    alerts_v2_render_html_page(
        'Invalid unsubscribe link',
        'This unsubscribe link is invalid.',
        'danger'
    );
    exit;
}

$deleteStmt = $mysqli->prepare("
    DELETE FROM HostSubscribers
    WHERE unsubscribe_token = ?
");
if (!$deleteStmt) {
    if (alerts_v2_wants_json()) {
        v2_json_error('db_prepare_failed', 'Prepare failed: ' . $mysqli->error, 500, null, [], v2_meta_base(['alerts_unsubscribe_v2'], true));
        exit;
    }
    alerts_v2_render_html_page(
        'Unsubscribe failed',
        'A server error occurred while processing your request.',
        'danger'
    );
    exit;
}

$deleteStmt->bind_param("s", $token);
$deleteStmt->execute();
$deleted = $deleteStmt->affected_rows;
$deleteStmt->close();

if ($deleted > 0) {
    if (alerts_v2_wants_json()) {
        v2_json_success([
            'message' => 'You have been unsubscribed successfully.',
            'status' => 'success',
        ], v2_meta_base(['alerts_unsubscribe_v2']));
        exit;
    }
    alerts_v2_render_html_page(
        'Unsubscribed',
        'You have been unsubscribed successfully. You will no longer receive alerts for this subscription.',
        'success'
    );
    exit;
}

if (alerts_v2_wants_json()) {
    v2_json_success([
        'message' => 'Already unsubscribed.',
        'status' => 'already_unsubscribed',
    ], v2_meta_base(['alerts_unsubscribe_v2']));
    exit;
}
alerts_v2_render_html_page(
    'Already unsubscribed',
    'This unsubscribe link was already used. No further action is required.',
    'info'
);
