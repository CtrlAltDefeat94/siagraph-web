<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../include/layout.php';

$pageTitle = $pageTitle ?? 'Blockchain Explorer';
$pageKey = $pageKey ?? 'home';

render_header('SiaGraph - ' . $pageTitle, 'SiaGraph blockchain explorer', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/components/data-page.css'), ENT_QUOTES, 'UTF-8') . '">',
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/pages/explorer.css'), ENT_QUOTES, 'UTF-8') . '">',
    '<script type="module" src="' . htmlspecialchars(versioned_asset_url('/js/explorer-v2/app.js'), ENT_QUOTES, 'UTF-8') . '&rv=explorer-routes-2"></script>',
]);
?>
<section id="main-content" class="sg-container sg-data-page explorer-shell explorer-density--compact">
    <div id="explorer-content" data-page="<?php echo htmlspecialchars($pageKey, ENT_QUOTES, 'UTF-8'); ?>"></div>
</section>
<script>
window.EXPLORER_API_BASE = <?php echo json_encode(rtrim((string) ($SETTINGS['explorer'] ?? ''), '/'), JSON_UNESCAPED_SLASHES); ?>;
</script>
<?php render_footer([]); ?>
