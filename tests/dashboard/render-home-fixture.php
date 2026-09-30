<?php
namespace Siagraph\Utils {
    class Cache {
        public static function getCache(...$args) { return null; }
        public static function setCacheSeconds(...$args) {}
    }
    class ApiClient {
        public static function fetchJson(...$args) {
            return ['actual' => ['utilized_storage' => 1000000000000, 'total_storage' => 2000000000000, 'online_hosts' => 20, 'active_contracts' => 100, '30_day_revenue' => ['sc' => 1e27, 'eur' => 10, 'usd' => 12]], 'change' => []];
        }
    }
}
namespace {
    chdir(dirname(__DIR__, 2));
    require 'vendor/autoload.php';
    $SETTINGS = ['explorer' => ''];
    function versioned_asset_url($path) { return $path; }
    function render_header(...$args) { echo '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body class="sg-site-body">'; }
    function render_footer(...$args) { echo '</body></html>'; }
    $source = file_get_contents('index.php');
    $source = str_replace(["require_once __DIR__ . '/bootstrap.php';", "require_once __DIR__ . '/include/layout.php';"], '', $source);
    $source = str_replace('__DIR__', var_export(getcwd(), true), $source);
    eval('?>' . $source);
}
