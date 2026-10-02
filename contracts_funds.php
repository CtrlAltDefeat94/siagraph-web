<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$location = '/contracts_collateral' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $location, true, 301);
exit;
