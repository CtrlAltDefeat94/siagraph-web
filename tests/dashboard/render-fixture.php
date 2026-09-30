<?php
// Render the actual page/component without a database or API connection.
chdir(dirname(__DIR__, 2));
require 'vendor/autoload.php';
function versioned_asset_url($path) { return $path; }
function render_header(...$args) { echo '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>'; }
function render_footer(...$args) { echo '</body></html>'; }
$page = $argv[1] ?? '';
$pageDirs = [
    'contract_activity.php' => 'contracts',
    'contracts_collateral.php' => 'contracts',
    'siacoin_price.php' => 'economics',
    'network_storage.php' => 'network',
    'host_explorer.php' => 'host',
    'host_alerts.php' => 'host',
];
if (!isset($pageDirs[$page])) exit(1);
if (isset($argv[2])) $_GET['public_key'] = $argv[2];
$source = file_get_contents($pageDirs[$page] . '/' . $page);
// eval() resolves __DIR__ to this fixture's own folder, not the page's new folder.
$source = str_replace("dirname(__DIR__) . '/", "'", $source);
$source = str_replace(["require_once 'bootstrap.php';", "require_once 'include/layout.php';"], '', $source);
eval('?>' . $source);
