<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Application\Service;

use Innis\Hubstr\Core\Application\Port\LifecycleInterface;
use Innis\Hubstr\Core\Application\Port\ServerInterface;
use Innis\Hubstr\Core\Application\Port\ShutdownSignalInterface;
use Psr\Log\LoggerInterface;

final readonly class Kernel
{
    public function __construct(
        private LoggerInterface $logger,
        private ShutdownSignalInterface $shutdownSignal,
        private ?LifecycleInterface $lifecycle = null,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function run(ServerInterface $server, string $banner, array $context = []): void
    {
        if (null !== $this->lifecycle) {
            // Deliberate: drain runs as a stop callback, not after stop() returns — see ADR-0007
            $server->onStop($this->lifecycle->drain(...));
        }

        $server->start();

        try {
            $this->lifecycle?->start();
            $this->logger->info($banner, $context);

            $signal = $this->shutdownSignal->await();
            $this->logger->info('Received shutdown signal', ['signal' => $signal]);
        } finally {
            $this->shutDown($server);
        }

        $this->logger->info('Shutdown complete');
    }

    private function shutDown(ServerInterface $server): void
    {
        try {
            $server->stop();
        } finally {
            $this->lifecycle?->stop();
        }
    }
}
