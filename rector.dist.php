<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php83\Rector\ClassConst\AddTypeToConstRector;
use Rector\Php84\Rector\MethodCall\NewMethodCallWithoutParenthesesRector;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/examples', __DIR__ . '/src', __DIR__ . '/tests'])
    ->withRules([
        AddTypeToConstRector::class,
        NewMethodCallWithoutParenthesesRector::class,
    ]);
