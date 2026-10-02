<?php
namespace Siagraph\Services;

class HostContracts
{
    /** Fetch every page; never return a partial history as a complete distribution. */
    public static function fetchAll(callable $fetchPage): array
    {
        $contracts = [];
        $seen = [];
        $limit = 500;
        for ($offset = 0; ; ) {
            $page = $fetchPage($limit, $offset);
            if (!is_array($page) || !array_is_list($page)) {
                throw new \RuntimeException('Invalid host contracts response.');
            }
            if (!$page) return $contracts;
            foreach ($page as $contract) {
                $id = $contract['id'] ?? null;
                if (!is_string($id) || isset($seen[$id])) {
                    throw new \RuntimeException('Invalid or repeated host contract page.');
                }
                $seen[$id] = true;
                $contracts[] = $contract;
            }
            // Use the actual count: upstream may impose a smaller page size.
            $offset += count($page);
        }
    }
}
