<?php
header('Content-Type: application/json');
include_once "../../../bootstrap.php";

$token = $_GET['token'] ?? null;
$public_key = $_GET['public_key'] ?? null;

if (!$token) {
    http_response_code(400);
    echo json_encode(["error" => "Missing unsubscribe token."]);
    exit;
}
if (!$public_key) {
    http_response_code(400);
    echo json_encode(["error" => "Missing public_key token."]);
    exit;
}


$stmt = $mysqli->prepare("
    DELETE FROM HostSubscribers
    WHERE unsubscribe_token = ?
    AND public_key = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Prepare failed: " . $mysqli->error]);
    exit;
}

$stmt->bind_param("ss", $token, $public_key);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["message" => "You have been unsubscribed successfully."]);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Invalid or already unsubscribed token."]);
}

$stmt->close();
