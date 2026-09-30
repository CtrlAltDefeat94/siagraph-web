<?php
include_once "../../../bootstrap.php";
require_once __DIR__ . "/_common.php";
require_once __DIR__ . "/../_core/response.php";

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true, 512, JSON_BIGINT_AS_STRING);

    $publicKey = isset($input['public_key']) ? trim((string) $input['public_key']) : '';
    $service = isset($input['service']) ? trim((string) $input['service']) : '';
    $recipient = isset($input['recipient']) ? trim((string) $input['recipient']) : '';

    if ($publicKey === '' || $service === '' || $recipient === '') {
        v2_json_error('invalid_body', 'Missing required fields.', 400, null, [], v2_meta_base(['alerts_subscriptions_v2']));
        exit;
    }

    $validServices = ['email', 'pushover', 'telegram'];
    if (!in_array($service, $validServices, true)) {
        v2_json_error('invalid_service', 'Invalid service type.', 400, 'service', [], v2_meta_base(['alerts_subscriptions_v2']));
        exit;
    }

    if ($service === 'email' && !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
        v2_json_error('invalid_recipient', 'Invalid email address.', 400, 'recipient', [], v2_meta_base(['alerts_subscriptions_v2']));
        exit;
    }

    if ($service === 'telegram' && !preg_match('/^\d{5,}$/', $recipient)) {
        v2_json_error('invalid_recipient', 'Invalid Telegram ID.', 400, 'recipient', [], v2_meta_base(['alerts_subscriptions_v2']));
        exit;
    }

    $checkStmt = $mysqli->prepare("
        SELECT COUNT(*)
        FROM HostSubscribers
        WHERE public_key = ? AND service = ? AND recipient = ?
    ");
    if (!$checkStmt) {
        v2_json_error('db_prepare_failed', 'Prepare failed: ' . $mysqli->error, 500, null, [], v2_meta_base(['alerts_subscriptions_v2'], true));
        exit;
    }

    $checkStmt->bind_param("sss", $publicKey, $service, $recipient);
    $checkStmt->execute();
    $checkStmt->bind_result($count);
    $checkStmt->fetch();
    $checkStmt->close();

    if ((int) $count > 0) {
        v2_json_error('already_subscribed', 'You are already subscribed to this host.', 409, null, [], v2_meta_base(['alerts_subscriptions_v2']));
        exit;
    }

    $unsubscribeToken = alerts_v2_create_signed_token($SETTINGS);
    $insertStmt = $mysqli->prepare("
        INSERT INTO HostSubscribers (public_key, service, recipient, unsubscribe_token)
        VALUES (?, ?, ?, ?)
    ");
    if (!$insertStmt) {
        v2_json_error('db_prepare_failed', 'Prepare failed: ' . $mysqli->error, 500, null, [], v2_meta_base(['alerts_subscriptions_v2'], true));
        exit;
    }

    $insertStmt->bind_param("ssss", $publicKey, $service, $recipient, $unsubscribeToken);
    if (!$insertStmt->execute()) {
        v2_json_error('db_execute_failed', 'Execute failed: ' . $insertStmt->error, 500, null, [], v2_meta_base(['alerts_subscriptions_v2'], true));
        $insertStmt->close();
        exit;
    }
    $insertStmt->close();

    $unsubscribeUrl = rtrim((string) ($SETTINGS['siagraph_base_url'] ?? ''), '/') . '/api/v2/alerts/unsubscribe?token=' . rawurlencode($unsubscribeToken);
    v2_json_success([
        'message' => 'Subscribed successfully.',
        'unsubscribe_url' => $unsubscribeUrl,
        'expires_in_seconds' => alerts_v2_get_ttl_seconds($SETTINGS),
    ], v2_meta_base(['alerts_subscriptions_v2']));
    exit;
}

if ($method === 'DELETE') {
    $token = alerts_v2_extract_token_from_request();
    if ($token === null || $token === '') {
        v2_json_error('missing_token', 'Missing token.', 400, 'token', [], v2_meta_base(['alerts_subscriptions_v2']));
        exit;
    }

    $tokenCheck = alerts_v2_parse_token($token, $SETTINGS);
    if ($tokenCheck['status'] === 'expired') {
        v2_json_error('expired_token', 'Token expired.', 410, 'token', [
            'status' => 'expired',
            'expires_at' => $tokenCheck['expires_at'] ?? null,
        ], v2_meta_base(['alerts_subscriptions_v2']));
        exit;
    }

    if ($tokenCheck['status'] !== 'valid') {
        v2_json_error('invalid_token', 'Invalid token.', 404, 'token', [
            'status' => 'invalid',
        ], v2_meta_base(['alerts_subscriptions_v2']));
        exit;
    }

    $deleteStmt = $mysqli->prepare("
        DELETE FROM HostSubscribers
        WHERE unsubscribe_token = ?
    ");
    if (!$deleteStmt) {
        v2_json_error('db_prepare_failed', 'Prepare failed: ' . $mysqli->error, 500, null, [], v2_meta_base(['alerts_subscriptions_v2'], true));
        exit;
    }

    $deleteStmt->bind_param("s", $token);
    $deleteStmt->execute();
    $deleted = $deleteStmt->affected_rows;
    $deleteStmt->close();

    if ($deleted > 0) {
        v2_json_success([
            'message' => 'Unsubscribed successfully.',
            'status' => 'success',
        ], v2_meta_base(['alerts_subscriptions_v2']));
        exit;
    }

    v2_json_success([
        'message' => 'Already unsubscribed.',
        'status' => 'already_unsubscribed',
    ], v2_meta_base(['alerts_subscriptions_v2']));
    exit;
}

v2_json_error('method_not_allowed', 'Method not allowed.', 405, null, [], v2_meta_base(['alerts_subscriptions_v2']));
