<?php
require_once dirname(__DIR__) . '/bootstrap.php';
include_once dirname(__DIR__) . '/include/graph.php';
$graphConfigs = require dirname(__DIR__) . '/include/graph_configs.php';

require_once dirname(__DIR__) . "/include/layout.php";
require_once dirname(__DIR__) . '/include/components/range_controls.php';
use Siagraph\Utils\Cache;
$resolution = (isset($_GET['resolution']) && $_GET['resolution'] === 'monthly') ? 'monthly' : 'daily';
$growthEndpoint = '/api/v1/' . $resolution . '/growth';
$intervalDefault = $resolution === 'monthly' ? 'month' : 'week';
?>
<?php
// Redirect this legacy page to Network Storage after merging content
header('Location: /network_storage', true, 302);
exit;
?>
