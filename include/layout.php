<?php
function render_header(
    string $title = 'SiaGraph',
    string $description = 'SiaGraph provides network statistics, charts, and insights for the Sia storage platform.',
    array $extra_head = []
) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta name="color-scheme" content="dark">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title><?php echo htmlspecialchars($title); ?></title>
        <?php 
            $APP_LOCALE = class_exists('Siagraph\Utils\Locale') ? Siagraph\Utils\Locale::get() : 'en_US';
            $APP_LOCALE_BCP47 = str_replace('_', '-', $APP_LOCALE);
        ?>
        <meta name="app-locale" content="<?php echo htmlspecialchars($APP_LOCALE_BCP47, ENT_QUOTES, 'UTF-8'); ?>">
        <meta name="description" content="<?php echo htmlspecialchars($description); ?>">
        <meta property="og:title" content="<?php echo htmlspecialchars($title); ?>">
        <meta property="og:description" content="<?php echo htmlspecialchars($description); ?>">
        <meta property="og:image" content="/img/siagraph_banner_blue.png">
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" defer></script>
        
        <link href="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.0/nouislider.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
        <link rel="stylesheet" href="css/dark.css">
        <link rel="stylesheet" href="css/style.css">
        <link rel="stylesheet" href="css/theme.css">
        <link rel="stylesheet" href="css/overrides.css">
        <link rel="icon" href="img/favicon.ico" type="image/png">
        <script>
            window.APP_LOCALE = <?php echo json_encode($APP_LOCALE_BCP47); ?>;
            // Bump this to invalidate client-side cached API responses
            window.FETCH_CACHE_VERSION = '2024-01-cutoff-1';
        </script>
        <script src="script.js" defer></script>
        <?php foreach ($extra_head as $tag) { echo $tag; } ?>
    </head>
    <body class="d-flex flex-column min-vh-100">
    <span aria-hidden="true" style="position:fixed;top:0;left:0;right:0;bottom:auto;height:64rem;pointer-events:none;z-index:-10;background:radial-gradient(49.63% 57.02% at 58.99% -7.2%, rgba(255,0,0,0.15) 39.4%, rgba(0,0,0,0) 100%)"></span>
	<?php include __DIR__ . '/header.html'; ?>
	<main class="flex-grow-1">
<?php }

function render_footer(array $scripts = []) {
    ?>
        </main>
        <?php include __DIR__ . '/footer.php'; ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.1/moment.min.js" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/chart.js@3" defer></script>
        <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-moment@1" defer></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/noUiSlider/15.6.0/nouislider.min.js" defer></script>
        <script src="js/graph-renderer.js" defer></script>
        <?php foreach ($scripts as $script) { echo "<script src=\"{$script}\"></script>"; } ?>
    </body>
    </html>
<?php }
