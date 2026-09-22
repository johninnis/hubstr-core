<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Logging;

use Amp\ByteStream\WritableStream;
use Amp\Log\StreamHandler;
use Innis\Hubstr\Core\Domain\Enum\LogLevel;
use Monolog\Level;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

final readonly class LoggerFactory
{
    public function __construct(
        private WritableStream $sink,
        private LogLevel $logLevel,
    ) {
    }

    public function create(string $channel): LoggerInterface
    {
        // Deliberate: one non-blocking sink, no log file — see ADR-0011
        $handler = new StreamHandler($this->sink, Level::fromName($this->logLevel->value));
        $handler->setFormatter(new TrimmedLineFormatter());

        return new Logger($channel, [$handler]);
    }
}
