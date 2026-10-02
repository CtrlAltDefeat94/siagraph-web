<?php
require __DIR__ . '/../../vendor/autoload.php';
use Siagraph\Services\HostContracts;

function check($condition, $message) {
    if (!$condition) throw new RuntimeException($message);
}
$offsets = [];
$contracts = HostContracts::fetchAll(function ($limit, $offset) use (&$offsets) {
    check($limit === 500, 'Use supported page limit');
    $offsets[] = $offset;
    // Emulate an upstream cap smaller than requested, with active data only on a later page.
    return match ($offset) {
        0 => [['id' => 'old-a', 'resolutionType' => 'renewal'], ['id' => 'old-b', 'resolutionType' => 'expiration']],
        2 => [['id' => 'active', 'resolutionType' => null]],
        3 => [],
    };
});
check($offsets === [0, 2, 3], 'Continue pagination until empty');
check(count($contracts) === 3 && $contracts[2]['id'] === 'active', 'Include later active contracts');
check(HostContracts::fetchAll(fn() => []) === [], 'Empty history');
foreach ([fn() => ['error' => 'Unavailable'], fn() => [['id' => 'repeated']]] as $fetch) {
    $failed = false;
    try { HostContracts::fetchAll($fetch); } catch (RuntimeException $e) { $failed = true; }
    check($failed, 'Reject errors and repeated pages');
}
echo "Host contract pagination checks passed.\n";
