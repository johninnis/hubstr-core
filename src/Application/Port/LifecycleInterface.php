<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Application\Port;

interface LifecycleInterface
{
    public function start(): void;

    public function drain(): void;

    public function stop(): void;
}
