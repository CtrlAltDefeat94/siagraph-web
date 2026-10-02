<?php
require_once dirname(__DIR__) . '/bootstrap.php';

$id = isset($_GET['id']) ? trim((string) $_GET['id']) : '';
$publicKey = isset($_GET['public_key']) ? trim((string) $_GET['public_key']) : '';

$params = [];
if ($id !== '') {
    $params['id'] = $id;
}
if ($publicKey !== '') {
    $params['public_key'] = $publicKey;
}

$query = http_build_query($params);
$target = '/host' . ($query !== '' ? ('?' . $query) : '') . '#benchmarks';

header('Location: ' . $target, true, 302);
exit;

