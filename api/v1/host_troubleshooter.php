<?php

function isPortOpen($host, $port, $timeout = 2) {
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

function fetchJson($url) {
    $response = @file_get_contents($url);
    if ($response === FALSE) {
        return null;
    }
    return json_decode($response, true);
}

function checkIPVersion($host) {
    $is_ipv4 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    $is_ipv6 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

    // If it's not an IP address, perform a DNS lookup
    if (!$is_ipv4 && !$is_ipv6) {
        $ipv4_records = dns_get_record($host, DNS_A);
        $ipv6_records = dns_get_record($host, DNS_AAAA);

        $is_ipv4 = !empty($ipv4_records);
        $is_ipv6 = !empty($ipv6_records);
    }

    return ['ipv4' => $is_ipv4, 'ipv6' => $is_ipv6];
}

header('Content-Type: application/json');

if (!isset($_GET['net_address'])) {
    echo json_encode(['error' => 'Missing net_address parameter']);
    exit;
}

$net_address = $_GET['net_address'];

// Validate and extract hostname/IP and port
if (!preg_match('/^([\w\.-]+):(\d+)$/', $net_address, $matches)) {
    echo json_encode(['error' => 'Invalid net_address format']);
    exit;
}

$host = $matches[1];
$main_port = (int) $matches[2];

// Check if the host is IPv4 or IPv6 accessible
$ip_versions = checkIPVersion($host);

// Fetch public key from siagraph.info
$public_key_url = "https://siagraph.info/api/v1/hosts?query=" . urlencode($net_address);
$public_key_data = fetchJson($public_key_url);

if (!$public_key_data || empty($public_key_data['hosts'][0]['public_key'])) {
    echo json_encode([
        'error' => 'Failed to retrieve public key',
        'net_address' => $net_address
    ]);
    exit;
}

$public_key = $public_key_data['hosts'][0]['public_key'];

// Fetch additional host info from explorer.siagraph.info
$host_info_url = "https://explorer.siagraph.info/api/pubkey/" . urlencode($public_key) . "/host";
$host_info_data = fetchJson($host_info_url);

if (!$host_info_data || (isset($host_info_data['lastScanSuccessful']) && !$host_info_data['lastScanSuccessful'])) {
    echo json_encode([
        'error' => 'Last scan was unsuccessful',
        'net_address' => $net_address,
        'public_key' => $public_key
    ]);
    exit;
}

// Check if siamuxport is available
$siamux_port = isset($host_info_data['settings']['siamuxport']) ? (int) $host_info_data['settings']['siamuxport'] : null;

// Ports to scan: main port + siamuxport (if available)
$ports_to_scan = [$main_port];
if ($siamux_port) {
    $ports_to_scan[] = $siamux_port;
}

// Scan ports only if the host is accessible via at least one IP version
$results = [];
if ($ip_versions['ipv4'] || $ip_versions['ipv6']) {
    foreach ($ports_to_scan as $port) {
        $results[$port] = isPortOpen($host, $port) ? 'open' : 'closed';
    }
}

echo json_encode([
    #'net_address' => $net_address,
    #'host' => $host,
    'ipv4_accessible' => $ip_versions['ipv4'],
    'ipv6_accessible' => $ip_versions['ipv6'],
    'scanned_ports' => $results,
    'host_info' => $host_info_data
]);

?>
