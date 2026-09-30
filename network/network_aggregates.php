<?php
require_once dirname(__DIR__) . '/bootstrap.php';
include_once dirname(__DIR__) . '/include/graph.php';
$graphConfigs = require dirname(__DIR__) . '/include/graph_configs.php';
$currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur';
$resolution = (isset($_GET['resolution']) && $_GET['resolution'] === 'monthly') ? 'monthly' : 'daily';
require_once dirname(__DIR__) . "/include/layout.php";
require_once dirname(__DIR__) . '/include/components/range_controls.php';
$aggEndpoint = '/api/v1/' . $resolution . '/aggregates';
$intervalDefault = $resolution === 'monthly' ? 'month' : 'week';
?>
<?php
// Redirect this page into Financials Overview where fees are displayed
header('Location: /revenue', true, 302);
exit;
?>
