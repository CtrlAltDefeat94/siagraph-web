<?php
require_once 'bootstrap.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';
$currencyCookie = isset($_COOKIE['currency']) ? $_COOKIE['currency'] : 'eur';
$resolution = (isset($_GET['resolution']) && $_GET['resolution'] === 'monthly') ? 'monthly' : 'daily';
require_once "include/layout.php";
require_once 'include/components/range_controls.php';
$aggEndpoint = '/api/v1/' . $resolution . '/aggregates';
$intervalDefault = $resolution === 'monthly' ? 'month' : 'week';
?>
<?php
// Redirect this page into Financials Overview where fees are displayed
header('Location: /revenue.php', true, 302);
exit;
?>
