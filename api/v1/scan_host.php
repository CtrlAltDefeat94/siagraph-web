<?php
include_once "../../bootstrap.php";

use Siagraph\Utils\Cache;

header('Content-Type: application/json');
$url = "http://localhost:8484/benchmark";
// Fetch host ID from URL
if (isset($_GET['public_key'])) {
    $public_key = $_GET['public_key'];
    $stmt = $mysqli->prepare("SELECT * FROM Hosts WHERE public_key = ?");
    if (!$stmt) {
        echo json_encode(["error" => "Prepare failed: " . $mysqli->error]);
        die;
    }
    $stmt->bind_param('s', $public_key);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
}
if (isset($result)) {


    $settings = mysqli_fetch_assoc($result);
    $public_key = $settings['public_key'];
    $net_address = $settings['net_address'];
} else {
    echo '{"error": "host key is missing."}';
    die;


}
// The data to be sent in the POST request, formatted as JSON
$data = array(
    "address" => $net_address,
    "hostKey" => $public_key,
    "sectors" => 1
);

// JSON encode the data
$jsonData = json_encode($data);
// Create an HTTP context with the POST method, headers, and content
$options = array(
    'http' => array(
        'header' => "Content-Type: application/json\r\n" .
            "Content-Length: " . strlen($jsonData) . "\r\n",
        'method' => 'POST',
        'content' => $jsonData,
        'ignore_errors' => true,
    ),
);

// Create a stream context
$context = stream_context_create($options);

// Make the POST request
$response = file_get_contents($url, false, $context);


// Initialize an array to hold the output
$output = array();

// Check if the response is false
if ($response === false) {
    // Handle the case where the request fails completely
    $output = array(
        "error" => "Unable to complete the request."
    );
} else {
    // Try to decode the JSON response
    $decodedResponse = json_decode($response, true, 512, JSON_BIGINT_AS_STRING);

    // Check if decoding was successful and the response is an array
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($decodedResponse)) {
        // If there was an error or the response is not an array, handle it as an error
        if (str_contains($response, 'insufficient balance')) { 
            $response = "Benchmarking wallet balance insufficient.";
        }
        $output = array(
            "error" => $response,
        );
    } else {
        // The response is valid JSON; prepare the output for success
        $output = $decodedResponse;
    }
}

// Set the content type to JSON and print the output
header('Content-Type: application/json');
echo json_encode($output);