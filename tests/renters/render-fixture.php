<?php
// Render the real page markup without loading application config or opening a database.
function render_header(...$args) { echo '<!doctype html><html><head><meta name="color-scheme" content="dark"><meta name="viewport" content="width=device-width, initial-scale=1"></head><body class="sg-site-body">'; }
function render_footer($scripts) { foreach ($scripts as $s) echo '<script src="/'.$s.'"></script>'; echo '</body></html>'; }
function versioned_asset_url($path) { return $path; }
$root = dirname(__DIR__, 2);
$page = $argv[1] ?? 'renter.php';
if (!in_array($page, ['renter.php', 'renter_explorer.php', 'renter_distribution.php'], true)) exit(1);
$source = file_get_contents($root . '/renter/' . $page);
// eval() resolves __DIR__ to this fixture's own folder, not the page's new folder.
$source = str_replace("dirname(__DIR__) . '/", "'", $source);
$source = preg_replace('/^require_once .*;$/m', '', $source);
$source = str_replace("'include/components/renter_history.php'", var_export($root . '/include/components/renter_history.php', true), $source);
eval('?>' . $source);
