<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Infrastructure\Persistence;

final readonly class Migration
{
    /**
     * @param positive-int     $version
     * @param non-empty-string $name
     * @param non-empty-string $sql
     */
    public function __construct(
        private int $version,
        private string $name,
        private string $sql,
    ) {
    }

    /**
     * @return positive-int
     */
    public function getVersion(): int
    {
        return $this->version;
    }

    /**
     * @return non-empty-string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return non-empty-string
     */
    public function getSql(): string
    {
        return $this->sql;
    }
}
