<?php
$query = $_SERVER['QUERY_STRING'] ?? '';
$location = '/token_transfer_volume' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $location, true, 301);
exit;
