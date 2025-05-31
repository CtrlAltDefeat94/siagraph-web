<?php
header('Content-Type: application/json');
include_once "../../../include/database.php";

$token = $_GET['token'] ?? null;

if (!$token) {
    http_response_code(400);
    echo json_encode(["error" => "Missing unsubscribe token."]);
    exit;
}

$stmt = $mysqli->prepare("
    DELETE FROM HostSubscribers
    WHERE unsubscribe_token = ?
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Prepare failed: " . $mysqli->error]);
    exit;
}

$stmt->bind_param("s", $token);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(["message" => "You have been unsubscribed successfully."]);
} else {
    http_response_code(404);
    echo json_encode(["error" => "Invalid or already unsubscribed token."]);
}

$stmt->close();
