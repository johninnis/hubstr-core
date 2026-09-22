<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Fake;

use Closure;
use Innis\Hubstr\Core\Application\Port\ServerInterface;
use Override;
use Throwable;

final class RecordingServer implements ServerInterface
{
    /** @var list<Closure> */
    private array $stopCallbacks = [];

    /**
     * @param array<string, Throwable> $failures
     */
    public function __construct(
        private readonly CallLog $log,
        private readonly array $failures = [],
    ) {
    }

    #[Override]
    public function start(): void
    {
        $this->log->record('server.start');
        $this->failWhen('start');
    }

    #[Override]
    public function stop(): void
    {
        $this->log->record('server.stop');

        foreach ($this->stopCallbacks as $callback) {
            $callback();
        }
        $this->failWhen('stop');
    }

    #[Override]
    public function onStop(Closure $callback): void
    {
        $this->stopCallbacks[] = $callback;
    }

    private function failWhen(string $moment): void
    {
        if (isset($this->failures[$moment])) {
            throw $this->failures[$moment];
        }
    }
}
