<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Fake;

use Innis\Hubstr\Core\Application\Port\LifecycleInterface;
use Override;
use Throwable;

final class RecordingLifecycle implements LifecycleInterface
{
    public function __construct(
        private readonly CallLog $log,
        private readonly ?Throwable $startFailure = null,
    ) {
    }

    #[Override]
    public function start(): void
    {
        $this->log->record('lifecycle.start');

        if (null !== $this->startFailure) {
            throw $this->startFailure;
        }
    }

    #[Override]
    public function drain(): void
    {
        $this->log->record('lifecycle.drain');
    }

    #[Override]
    public function stop(): void
    {
        $this->log->record('lifecycle.stop');
    }
}
