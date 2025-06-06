<?php
include_once "../../include/redis.php";
include_once "../../include/config.php";
header('Content-Type: application/json');
// Function to fetch data using cURL

function fetchData($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}
function compareDates($date1, $date2)
{
    $diff = $date1->diff($date2);
    return ($diff->d * 86400) + ($diff->h * 3600) + ($diff->i * 60) + $diff->s;
}

// Fetch data from the consensus API
$consensusData = fetchData($SETTINGS['explorer'] . '/api/consensus/state');
$blockHeight = $consensusData['index']['height'];
$data = [];

$explorerKey = 'explorer-' . $blockHeight;
$data = getCache($explorerKey);
// Fetch unconfirmed transactions
$txPoolData = fetchData($SETTINGS['explorer'] . '/api/txpool/transactions');
$count = 0;
if ($txPoolData && isset($txPoolData['transactions'])) {
    foreach ($txPoolData['transactions'] as $item) {
        if (isset($item['minerFees'])) {
            $count++;
        }
    }
}
if ($data) {
    $data = json_decode($data, true);
    $data['unconfirmedTransactions'] = $count;
    echo json_encode($data);
    die;
}

$data['blockHeight'] = $blockHeight;

// Convert timestamp to local time
$blockFoundTime = new DateTime($consensusData['prevTimestamps'][0]);
$blockCompareTime = new DateTime($consensusData['prevTimestamps'][9]);
$totalSeconds = compareDates($blockFoundTime, $blockCompareTime);

$data['blockFoundTime'] = $blockFoundTime->format('Y-m-d\TH:i:s\Z');
$data['averageFoundSeconds'] = round($totalSeconds / 10);


$blockData = fetchData($SETTINGS['explorer'] . '/api/consensus/tip/' . ($data['blockHeight'] - 144));
$blockData = fetchData($SETTINGS['explorer'] . '/api/blocks/' . $blockData['id']);

$blockCompareTime = new DateTime($blockData['timestamp']);
$totalSeconds = compareDates($blockFoundTime, $blockCompareTime);
#echo $totalSeconds/144;
$secondsuntilV2 = round((530000 - $blockHeight) * ($totalSeconds / 144));
$data['estimatedV2Time'] = $blockFoundTime->modify("+{$secondsuntilV2} seconds")->format('Y-m-d\TH:i:s\Z');




// Fetch connected peers
$peersData = [];
$peersData = fetchData($SETTINGS['explorer'] . '/api/syncer/peers');
$data['connectedPeers'] = count($peersData);

// Fetch previous block ID if a new block is detected
$previousBlockData = fetchData($SETTINGS['explorer'] . '/api/consensus/tip/' . ($data['blockHeight'] - 1));
if ($previousBlockData) {
    $previousBlockId = $previousBlockData['id'];
}


// Fetch block data for the previous block

$blockData = fetchData($SETTINGS['explorer'] . '/api/metrics/block');
$previousBlockData = fetchData($SETTINGS['explorer'] . '/api/metrics/block/' . $previousBlockId);

$newHosts = $blockData['totalHosts'] - $previousBlockData['totalHosts'];
$data['newHosts'] = $newHosts;

$completedContracts = ($blockData['failedContracts'] + $blockData['successfulContracts']) -
    ($previousBlockData['failedContracts'] + $previousBlockData['successfulContracts']);
$data['completedContracts'] = $completedContracts;

$newContracts = ($blockData['activeContracts'] + $blockData['failedContracts'] + $blockData['successfulContracts']) -
    ($previousBlockData['activeContracts'] + $previousBlockData['failedContracts'] + $previousBlockData['successfulContracts']);
$data['newContracts'] = $newContracts;

$data['unconfirmedTransactions'] = $count;


// Output the data
$jsonResult = json_encode($data, JSON_PRETTY_PRINT);
setCache($jsonResult, $explorerKey, 'hour');
// Echo the data as a JSON object
echo $jsonResult;
