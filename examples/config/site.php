<?php

declare(strict_types=1);

$runtimeDirectory = dirname(__DIR__, 2).'/var/example';

return [
    'host' => '127.0.0.1',
    'port' => 8080,
    'trusted_proxies' => ['127.0.0.1'],

    'database_path' => $runtimeDirectory.'/site.sqlite',

    'log_level' => 'info',

    'site_name' => 'Hubstr Core Example',
    'owner_npub' => 'npub1example',
    'template_cache_path' => $runtimeDirectory.'/latte',
];
