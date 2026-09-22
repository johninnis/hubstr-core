<?php

declare(strict_types=1);

namespace Innis\Hubstr\Core\Domain\ValueObject;

use InvalidArgumentException;

final readonly class ConfigValues
{
    /**
     * @param array<array-key, mixed> $values
     */
    private function __construct(
        private array $values,
        private string $section,
    ) {
    }

    /**
     * @param array<array-key, mixed> $values
     */
    public static function fromArray(array $values): self
    {
        return new self($values, '');
    }

    /**
     * @return non-empty-string
     */
    public function string(string $key): string
    {
        return $this->optionalString($key) ?? throw $this->required($key);
    }

    /**
     * @return non-empty-string|null
     */
    public function optionalString(string $key): ?string
    {
        $value = $this->values[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!is_string($value) || '' === $value) {
            throw $this->mistyped($key, 'a non-empty string', $value);
        }

        return $value;
    }

    public function int(string $key): int
    {
        return $this->optionalInt($key) ?? throw $this->required($key);
    }

    public function optionalInt(string $key): ?int
    {
        $value = $this->values[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!is_int($value)) {
            throw $this->mistyped($key, 'an integer', $value);
        }

        return $value;
    }

    public function optionalBool(string $key): ?bool
    {
        $value = $this->values[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!is_bool($value)) {
            throw $this->mistyped($key, 'a boolean', $value);
        }

        return $value;
    }

    /**
     * @return list<non-empty-string>|null
     */
    public function optionalStringList(string $key): ?array
    {
        $value = $this->values[$key] ?? null;

        if (null === $value) {
            return null;
        }

        if (!is_array($value)) {
            throw $this->notAStringList($key);
        }

        return array_map(
            fn (mixed $entry): string => is_string($entry) && '' !== $entry ? $entry : throw $this->notAStringList($key),
            array_values($value),
        );
    }

    public function section(string $key): self
    {
        $value = $this->values[$key] ?? [];

        if (!is_array($value)) {
            throw $this->mistyped($key, 'an array', $value);
        }

        return new self($value, $this->name($key).'.');
    }

    public function rejectUnknownKeys(string ...$knownKeys): void
    {
        $unknown = array_map(
            fn (int|string $key): string => $this->name((string) $key),
            array_values(array_diff(array_keys($this->values), $knownKeys)),
        );

        if ([] !== $unknown) {
            throw new InvalidArgumentException(sprintf('Unknown config %s: %s', 1 === count($unknown) ? 'key' : 'keys', implode(', ', $unknown)));
        }
    }

    private function required(string $key): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('%s is required', $this->name($key)));
    }

    private function mistyped(string $key, string $expected, mixed $value): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('%s must be %s, got %s', $this->name($key), $expected, get_debug_type($value)));
    }

    private function notAStringList(string $key): InvalidArgumentException
    {
        return new InvalidArgumentException(sprintf('%s must be a list of non-empty strings', $this->name($key)));
    }

    private function name(string $key): string
    {
        return $this->section.$key;
    }
}
