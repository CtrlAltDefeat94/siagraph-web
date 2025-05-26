<?php
// Include configuration
include_once "../../include/config.php";
include_once "../../include/redis.php";

if (isset($_GET['scan'])) {
    $scan = $_GET['scan'];
} else {
    $scan = true;
}

$cacheKey = 'host_troubleshooter:' . $_GET['net_address'];
$cacheresult = getCache($cacheKey);
if (!$scan && $cacheresult) {
    echo $cacheresult;
    die;
}
// Initialize response array
$response = array(
    'public_key' => '',
    'net_address' => '',
    'v2' => false,
    'online' => false,
    'uptime' => 0,
    'last_announcement' => null,
    'last_scan' => 0,
    'next_scan' => 0,
    'ipv4_enabled' => false,
    'ipv6_enabled' => false,
    'software_version' => null,
    'protocol_version' => null,
    'used_storage' => 0,
    'total_storage' => 0,
    'remaining_capacity_percentage' => 0,
    'port_status' => array(
        'ipv4_rhp2' => null,
        'ipv4_rhp3' => null,
        'ipv4_rhp4' => null,
        'ipv6_rhp2' => null,
        'ipv6_rhp3' => null,
        'ipv6_rhp4' => null,
    ),
    "settings" => [
        "acceptingcontracts" => null,
        "storageprice" => null,
        "collateral" => null,
        "contractprice" => null,
        "egressprice" => null,
        "ingressprice" => null,
        "maxcollateral" => null,
        "maxduration" => null,
        "freesectorprice" => null,
        "ephemeralaccountexpiry" => null,
        "maxdownloadbatchsize" => null,
        "maxephemeralaccountbalance" => null,
        "maxrevisebatchsize" => null,
        "baserpcprice" => null,
        "sectorsize" => null,
        "siamuxport" => null,
        "windowsize" => null,
    ],
    'warnings' => [],
    'errors' => []
);

//////////////////////////////
// Utility Functions
//////////////////////////////

// Fetch JSON data using POST request
function fetchJsonPost($url, $postData = [])
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ($httpCode >= 200 && $httpCode < 300) ? json_decode($response, true) : null;
}

// Check if a given port is open
function isPortOpen($host, $port, $timeout = 2)
{
    $connection = @fsockopen($host, $port, $errno, $errstr, $timeout);
    if ($connection) {
        fclose($connection);
        return true;
    }
    return false;
}

// Fetch JSON from a URL using GET
function fetchJson($url)
{
    $response = @file_get_contents($url);
    return $response === FALSE ? null : json_decode($response, true);
}

// Determine if the host has IPv4/IPv6 capability
function checkIPVersion($host)
{
    $is_ipv4 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false;
    $is_ipv6 = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false;

    // If not a direct IP, resolve DNS records
    if (!$is_ipv4 && !$is_ipv6) {
        $is_ipv4 = !empty(dns_get_record($host, DNS_A));
        $is_ipv6 = !empty(dns_get_record($host, DNS_AAAA));
    }

    return ['ipv4' => $is_ipv4, 'ipv6' => $is_ipv6];
}

//////////////////////////////
// Main Logic
//////////////////////////////

// Set response content type
header('Content-Type: application/json');

// Validate presence of net_address param
if (!isset($_GET['net_address'])) {
    echo json_encode(['error' => 'Missing net_address parameter']);
    exit;
}

$net_address = $_GET['net_address'];

// Validate net_address format (host:port)
if (!preg_match('/^([\w\.-]+):(\d+)$/', $net_address, $matches)) {
    echo json_encode(['error' => 'Invalid net_address format']);
    exit;
}

$host = $matches[1];
$main_port = (int) $matches[2];

// Fetch host info from explorer API
$public_key_url = $SETTINGS['explorer'] . "/api/hosts";
$postData = ["netAddresses" => [$net_address]];

$hostsdata = fetchJsonPost($public_key_url, $postData);


if (!empty($hostsdata) && is_array($hostsdata)) {
    $ip_versions = checkIPVersion($host);
    $response['ipv4_enabled'] = $ip_versions['ipv4'];
    $response['ipv6_enabled'] = $ip_versions['ipv6'];

    $host_info_data = end($hostsdata);

    // Populate response from host info
    if (isset($host_info_data['publicKey'])) {
        $response['public_key'] = $host_info_data['publicKey'];
        $response['net_address'] = $net_address;
        $response['v2'] = $host_info_data['v2'];
        $response['last_announcement'] = $host_info_data['lastAnnouncement'];

        $response['last_scan'] = $host_info_data['lastScan'];
        $response['next_scan'] = $host_info_data['nextScan'];


        $troubleshootdURL = $SETTINGS['troubleshootd_base_url'] . "/troubleshoot";
        if (!$response['v2']) {
            $postTroubleshootdData = ["netAddresses" => [$net_address]];
            $postTroubleshootdData = [
                "publicKey" => $response['public_key'],
                "rhp2NetAddress" => $net_address
            ];
        } else {
            $postTroubleshootdData = [
                "publicKey" => $response['public_key'],
                "rhp4NetAddresses" => [
                    [
                        "address" => $net_address,
                        "protocol" => "siamux"
                    ]
                ]
            ];
        }
        $troubleshootdData = fetchJsonPost($troubleshootdURL, $postTroubleshootdData);

        if ($host_info_data['successfulInteractions'] > 0 && $host_info_data['totalScans'] > 0) {
            $response['uptime'] = round($host_info_data['successfulInteractions'] / $host_info_data['totalScans'], 2);
        }
        if (!$response['v2']) {
            ### V1 host
            $response['total_storage'] = $host_info_data['settings']['totalstorage'];
            $response['used_storage'] = $response['total_storage'] - $host_info_data['settings']['remainingstorage'];
            $response['software_version'] = $host_info_data['settings']['release'];
            $response['protocol_version'] = $host_info_data['settings']['version'];
            $response['settings']["acceptingcontracts"] = $host_info_data['settings']['acceptingcontracts'];
            $response['settings']["baserpcprice"] = $host_info_data['settings']['baserpcprice'];
            $response['settings']["collateral"] = $host_info_data['settings']['collateral'];
            $response['settings']["contractprice"] = $host_info_data['settings']['contractprice'];
            $response['settings']["egressprice"] = $host_info_data['settings']['downloadbandwidthprice'];
            $response['settings']["ingressprice"] = $host_info_data['settings']['uploadbandwidthprice'];
            $response['settings']["ephemeralaccountexpiry"] = $host_info_data['settings']['ephemeralaccountexpiry'];
            $response['settings']["maxcollateral"] = $host_info_data['settings']['maxcollateral'];
            $response['settings']["maxdownloadbatchsize"] = $host_info_data['settings']['maxdownloadbatchsize'];
            $response['settings']["maxephemeralaccountbalance"] = $host_info_data['settings']['maxephemeralaccountbalance'];
            $response['settings']["maxrevisebatchsize"] = $host_info_data['settings']['maxrevisebatchsize'];
            $response['settings']["maxduration"] = $host_info_data['settings']['maxduration'];
            $response['settings']["freesectorprice"] = $host_info_data['settings']['sectoraccessprice'];
            $response['settings']["sectorsize"] = $host_info_data['settings']['sectorsize'];
            $response['settings']["siamuxport"] = $host_info_data['settings']['siamuxport'];
            $response['settings']["storageprice"] = $host_info_data['settings']['storageprice'];
            $response['settings']["windowsize"] = $host_info_data['settings']['windowsize'];

        } else {
            ### V2 host
            $response['total_storage'] = $host_info_data['v2Settings']['totalStorage'] * 4096 * 1024;
            $response['used_storage'] = $response['total_storage'] - ($host_info_data['v2Settings']['remainingStorage'] * 4096 * 1024);

            $response['software_version'] = $host_info_data['v2Settings']['release'];
            $response['protocol_version'] = implode('.', $host_info_data['v2Settings']['protocolVersion']);

            $response['settings']["acceptingcontracts"] = $host_info_data['v2Settings']['acceptingContracts'];
            $response['settings']["collateral"] = $host_info_data['v2Settings']['prices']['collateral'];
            $response['settings']["contractprice"] = $host_info_data['v2Settings']['prices']['contractPrice'];
            $response['settings']["egressprice"] = $host_info_data['v2Settings']['prices']['egressPrice'];
            $response['settings']["ingressprice"] = $host_info_data['v2Settings']['prices']['ingressPrice'];
            $response['settings']["maxcollateral"] = $host_info_data['v2Settings']['maxCollateral'];
            $response['settings']["maxduration"] = $host_info_data['v2Settings']['maxContractDuration'];
            $response['settings']["storageprice"] = $host_info_data['v2Settings']['prices']['storagePrice'];
            $response['settings']["freesectorprice"] = $host_info_data['v2Settings']['prices']['freeSectorPrice'];
        }

        // Determine relevant ports depending on version
        if ($scan) {
            if ($response['v2']) {
                $ports = ['rhp4' => $main_port];
            } else {
                $siamux_port = isset($host_info_data['settings']['siamuxport']) ? (int) $host_info_data['settings']['siamuxport'] : null;
                $rhp4_port = $siamux_port ? $siamux_port + 1 : null;

                $ports = [
                    'rhp2' => $main_port,
                    'rhp3' => $siamux_port,
                    'rhp4' => $rhp4_port
                ];
            }
        }
        // Check if ports are open for IPv4 and IPv6
        foreach ($ports as $rhp => $port) {
            if ($port !== null) {
                if ($response['ipv4_enabled']) {
                    $response['port_status']['ipv4_' . $rhp] = isPortOpen($host, $port);
                }
                if ($response['ipv6_enabled']) {
                    $response['port_status']['ipv6_' . $rhp] = isPortOpen($host, $port);
                }
            }
        }
        if ($host_info_data['lastScanSuccessful']) {
            $response['online'] = true;
        }
        if ($response['used_storage'] && $response['total_storage']) {
            $response['remaining_capacity_percentage'] = 100 - round($response['used_storage'] / $response['total_storage'] * 100, 2);
        } else {
            $response['remaining_capacity_percentage'] = 0;
        }
        // RHP2
        if (!empty($troubleshootdData['rhp2']['warnings'])) {
            foreach ($troubleshootdData['rhp2']['warnings'] as $warning) {
                $response['warnings'][] = 'RHP2: ' . $warning;
            }
        }
        if (!empty($troubleshootdData['rhp2']['errors'])) {
            foreach ($troubleshootdData['rhp2']['errors'] as $error) {
                $response['errors'][] = 'RHP2: ' . $error;
            }
        }

        // RHP3
        if (!empty($troubleshootdData['rhp3']['warnings'])) {
            foreach ($troubleshootdData['rhp3']['warnings'] as $warning) {
                $response['warnings'][] = 'RHP3: ' . $warning;
            }
        }
        if (!empty($troubleshootdData['rhp3']['errors'])) {
            foreach ($troubleshootdData['rhp3']['errors'] as $error) {
                $response['errors'][] = 'RHP3: ' . $error;
            }
        }

        // RHP4 (array of entries)
        if (!empty($troubleshootdData['rhp4'])) {
            foreach ($troubleshootdData['rhp4'] as $index => $rhp4) {
                if (!empty($rhp4['warnings'])) {
                    foreach ($rhp4['warnings'] as $warning) {
                        $response['warnings'][] = 'RHP4: ' . $warning;
                    }
                }
                if (!empty($rhp4['errors'])) {
                    foreach ($rhp4['errors'] as $error) {
                        $response['errors'][] = 'RHP4: ' . $error;
                    }
                }
            }
        }
        if ($response['online']) {
            if ($block_height < 526000 && !$response['port_status']['ipv4_rhp4']) {
                $response['warnings'][] = "RHP4 port not open.";
            } elseif ($block_height >= 526000 && $block_height <= 530000 && !$response['port_status']['ipv4_rhp4']) {
                $response['errors'][] = "RHP4 port not open. Host function may be limited";
            } elseif ($block_height >= 530000 && !$response['port_status']['ipv4_rhp4']) {
                $response['errors'][] = "RHP4 port not open. Host is unusable.";
            }
        }
        if (new DateTime($response['last_announcement']) < (new DateTime())->sub(new DateInterval('P6M'))) {
            $response['errors'][] = "Last announcement is longer than 6 months ago.";
        }
        if ($response['online'] && !$response['settings']['acceptingcontracts']) {
            $response['errors'][] = "Not accepting contracts.";
        }
        if (!empty($response['remaining_capacity_percentage'])) {
            if ($response['remaining_capacity_percentage'] == 0) {
                $response['errors'][] = "Host is full.";
            } elseif ($response['remaining_capacity_percentage'] <= 5) {
                $response['warnings'][] = "Host is almost full.";
            }
        }
        if ($response['settings']['contractprice'] / 1e24 > 0.2) {
            $response['warnings'][] = "Expensive contract price. Hosts should use the default of 0.15SC";
        }

    }

} else {
    $response['errors'][] = "Netaddress not found. Verify the net address, or try (re)announcing the host.";
}

//////////////////////////////
// Output
//////////////////////////////
setCache(json_encode($response), $cacheKey, 'hour');
echo json_encode($response);
