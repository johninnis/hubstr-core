<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Tests\Fake;

final class CallLog
{
    /** @var list<string> */
    public array $calls = [];

    public function record(string $call): void
    {
        $this->calls[] = $call;
    }
}
