<?php
require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/include/config.php';
require_once dirname(__DIR__) . '/include/layout.php';
\Siagraph\Utils\Locale::init();
$key = $_GET['public_key'] ?? '';
$valid = is_string($key) && preg_match('/^(?:ed25519:)?[a-f0-9]{64}$/iD', $key);
$key = $valid ? 'ed25519:' . preg_replace('/^ed25519:/', '', strtolower($key)) : '';
if (!$valid) http_response_code(400);
render_header('SiaGraph - Host Contracts', 'Browse active and completed host contracts.', [
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/pages/host.css'), ENT_QUOTES, 'UTF-8') . '">',
    '<link rel="stylesheet" href="' . htmlspecialchars(versioned_asset_url('/css/pages/active-contracts.css'), ENT_QUOTES, 'UTF-8') . '">',
]);
$profileKind = 'host'; $identityParam = 'public_key';
require dirname(__DIR__) . '/include/components/contract_browser.php';
render_footer(['js/renter-format.js', 'js/renter-currency.js', 'js/active-contracts.js', 'js/host-contracts.js']);
