<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Unit\Presentation\Http;

use Amp\Http\HttpStatus;
use Innis\Hubstr\Core\Domain\ValueObject\SiteInfo;
use Innis\Hubstr\Core\Presentation\Http\ErrorPageResponder;
use Innis\Hubstr\Core\Presentation\Http\TemplatedErrorHandler;
use Innis\Hubstr\Core\Tests\Fake\FakeTemplateRenderer;
use PHPUnit\Framework\TestCase;

final class TemplatedErrorHandlerTest extends TestCase
{
    public function testPassesAnExplicitReasonThroughToTheErrorPage(): void
    {
        $renderer = new FakeTemplateRenderer();

        $this->handler($renderer)->handleError(HttpStatus::FORBIDDEN, 'Denied');

        self::assertSame('Denied', $renderer->lastParameters['message']);
    }

    public function testFallsBackToTheStandardReasonWhenNoneIsProvided(): void
    {
        $renderer = new FakeTemplateRenderer();

        $this->handler($renderer)->handleError(HttpStatus::NOT_FOUND);

        self::assertSame(HttpStatus::getReason(HttpStatus::NOT_FOUND), $renderer->lastParameters['message']);
    }

    private function handler(FakeTemplateRenderer $renderer): TemplatedErrorHandler
    {
        return new TemplatedErrorHandler(
            new ErrorPageResponder('error.latte', $renderer, new SiteInfo('Hubstr Service', '1.2.3')),
        );
    }
}
