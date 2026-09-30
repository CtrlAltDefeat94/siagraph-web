<?php
namespace Siagraph\Utils {
    class Cache {
        public static function getCache(...$args) { return json_encode(['known'=>true,'host_id'=>1,'public_key'=>'ed25519:abc','net_address'=>'host.example:9982','settings'=>array_fill_keys(['storageprice','ingressprice','egressprice','contractprice','freesectorprice','collateral','maxcollateral','maxduration','windowsize','remainingstorage','totalstorage'], 1)]); }
    }
    class ApiClient {
        public static function fetchJson(...$args) { return ['hosts'=>[], 'benchmarks'=>[]]; }
    }
}
namespace {
    chdir(dirname(__DIR__, 2));
    require 'vendor/autoload.php';
    $SETTINGS=['network'=>'mainnet'];
    $_GET=['id'=>1]; $_COOKIE=['currency'=>'sc'];
    function versioned_asset_url($path) { return $path; }
    function render_header(...$args) { echo '<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width, initial-scale=1"></head><body>'; }
    function render_footer(...$args) { echo '</body></html>'; }
    $source=file_get_contents('host/host.php');
    // eval() resolves __DIR__ to this fixture's own folder, not host.php's new folder.
    $source = str_replace("dirname(__DIR__) . '/", "'", $source);
    $source=str_replace(["require_once 'bootstrap.php';", "require_once 'include/layout.php';", "include_once 'include/config.php';", "include_once 'include/graph.php';"], '', $source);
    eval('?>'.$source);
}
