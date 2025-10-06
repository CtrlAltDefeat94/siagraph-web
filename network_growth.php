<?php
require_once 'bootstrap.php';
include_once 'include/graph.php';
$graphConfigs = require 'include/graph_configs.php';

require_once "include/layout.php";
require_once 'include/components/range_controls.php';
use Siagraph\Utils\Cache;
$resolution = (isset($_GET['resolution']) && $_GET['resolution'] === 'monthly') ? 'monthly' : 'daily';
$growthEndpoint = '/api/v1/' . $resolution . '/growth';
$intervalDefault = $resolution === 'monthly' ? 'month' : 'week';
?>
<?php
// Redirect this legacy page to Network Storage after merging content
header('Location: /network_storage.php', true, 302);
exit;
?>
