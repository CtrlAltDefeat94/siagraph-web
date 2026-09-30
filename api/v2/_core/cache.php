<?php

function v2_cache_key(string $namespace, array $params = []): string
{
    ksort($params);
    return 'v2:' . $namespace . ':' . md5(json_encode($params));
}
