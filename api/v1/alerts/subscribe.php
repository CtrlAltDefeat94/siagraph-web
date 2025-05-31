<?php
header('Content-Type: application/json');

include_once "../../../include/config.php";
include_once "../../../include/database.php";

function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Ensure POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST allowed"]);
    exit;
}

// Decode JSON body
$input = json_decode(file_get_contents('php://input'), true);

$public_key = $input['public_key'] ?? null;
$service = $input['service'] ?? null; // 'email' or 'pushover'
$destination = $input['destination'] ?? null;

// Validate required fields
if (!$public_key || !$service || !$destination) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required fields."]);
    exit;
}

// Validate service type
$valid_services = ['email', 'pushover'];
if (!in_array($service, $valid_services)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid service type."]);
    exit;
}

// Generate unsubscribe token
$unsubscribe_token = generateToken();

// Prepare insert statement
$stmt = $mysqli->prepare("
    INSERT INTO HostSubscribers (public_key, service, destination, unsubscribe_token)
    VALUES (?, ?, ?, ?)
");

if (!$stmt) {
    http_response_code(500);
    echo json_encode(["error" => "Prepare failed: " . $mysqli->error]);
    exit;
}

$stmt->bind_param("ssss", $public_key, $service, $destination, $unsubscribe_token);

if ($stmt->execute()) {
    echo json_encode([
        "message" => "Subscribed successfully.",
        "unsubscribe_url" => $SETTINGS['siagraph_base_url'] . "/api/v1/alerts/unsubscribe?token=$unsubscribe_token"
    ]);
} else {
    http_response_code(500);
    echo json_encode(["error" => "Execute failed: " . $stmt->error]);
}

$stmt->close();
?>
