<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use Override;

final class FunctionName implements Importable
{
    public readonly string $name;

    /**
     * Get the short name of the function (last part after \)
     */
    public string $shortName {
        get {
            $parts = explode('\\', $this->name);

            return array_pop($parts);
        }
    }

    public function __construct(string $name)
    {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Function name cannot be empty');
        }

        $this->name = $name;
    }

    /**
     * @phpstan-return ($input is null ? null : self)
     */
    public static function maybeFromString(Importable|self|string|null $input): ?self
    {
        if ($input === null) {
            return null;
        }

        if ($input instanceof self) {
            return $input;
        }

        return new self((string) $input);
    }

    #[Override]
    public function __toString(): string
    {
        return 'function ' . $this->name;
    }

    #[Override]
    public function equals(object $other): bool
    {
        return $other instanceof self && (string) $this === (string) $other;
    }

    #[Override]
    public function compare(object $other): int
    {
        $thisStr = str_replace(search: '\\', replace: ' ', subject: $this->name);

        if ($other instanceof self) {
            $otherStr = str_replace(search: '\\', replace: ' ', subject: $other->name);

            return strcasecmp($thisStr, $otherStr);
        }

        if ($other instanceof FullyQualified || $other instanceof NamespaceName) {
            $otherStr = str_replace(search: '\\', replace: ' ', subject: (string) $other);

            return strcasecmp($thisStr, $otherStr);
        }

        if ($other instanceof Alias) {
            return strcasecmp($this->shortName, $other->alias);
        }

        return 0;
    }
}
