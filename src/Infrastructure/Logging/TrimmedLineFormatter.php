<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Logging;

use Monolog\Formatter\FormatterInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\LogRecord;
use Override;

final readonly class TrimmedLineFormatter implements FormatterInterface
{
    public function __construct(
        private LineFormatter $lines = new LineFormatter(ignoreEmptyContextAndExtra: true),
    ) {
    }

    #[Override]
    public function format(LogRecord $record): string
    {
        // Deliberate: the line formatter leaves the spaces around an empty context behind — see ADR-0011
        return rtrim($this->lines->format($record))."\n";
    }

    /**
     * @param array<LogRecord> $records
     */
    #[Override]
    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }
}
