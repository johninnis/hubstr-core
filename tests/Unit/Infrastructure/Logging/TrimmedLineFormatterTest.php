<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Infrastructure\Logging;

use DateTimeImmutable;
use Innis\Hubstr\Core\Infrastructure\Logging\TrimmedLineFormatter;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

final class TrimmedLineFormatterTest extends TestCase
{
    public function testEndsALineAtItsMessageWhenTheRecordCarriesNoContext(): void
    {
        self::assertStringEndsWith("relay.INFO: Shutdown complete\n", new TrimmedLineFormatter()->format(self::record('Shutdown complete')));
    }

    public function testKeepsTheContextARecordCarries(): void
    {
        self::assertStringEndsWith("relay.INFO: started {\"port\":8080}\n", new TrimmedLineFormatter()->format(self::record('started', ['port' => 8080])));
    }

    public function testKeepsARecordOnOneLine(): void
    {
        self::assertSame(1, substr_count(new TrimmedLineFormatter()->format(self::record("first\nsecond")), "\n"));
    }

    public function testFormatsABatchAsOneTrimmedLinePerRecord(): void
    {
        $batch = new TrimmedLineFormatter()->formatBatch([self::record('first'), self::record('second')]);

        self::assertSame([2, 0], [substr_count($batch, "\n"), substr_count($batch, " \n")]);
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function record(string $message, array $context = []): LogRecord
    {
        return new LogRecord(new DateTimeImmutable('2026-01-01T00:00:00+00:00'), 'relay', Level::Info, $message, $context);
    }
}
