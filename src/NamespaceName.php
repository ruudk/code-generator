<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use Override;

final class NamespaceName implements Importable
{
    /**
     * @var non-empty-string
     */
    public readonly string $namespace;

    /**
     * Get the last segment of the namespace (e.g., App\Models -> Models)
     */
    public string $lastPart {
        get {
            $parts = explode('\\', $this->namespace);

            return (string) array_pop($parts);
        }
    }

    public function __construct(
        string $part,
        string ...$parts,
    ) {
        $flattened = array_filter(
            explode('\\', implode('\\', [$part, ...$parts])),
            fn($p) => $p !== '',
        );

        if ($flattened === []) {
            throw new InvalidArgumentException('At least one non-empty part is required');
        }

        $this->namespace = implode('\\', $flattened);
    }

    /**
     * @phpstan-return ($input is null ? null : self)
     */
    public static function maybeFromString(null | Importable | self | string $input) : ?self
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
    public function __toString() : string
    {
        return $this->namespace;
    }

    /**
     * Append parts to the namespace
     */
    public function with(string $part, string ...$parts) : self
    {
        return new self($this->namespace, $part, ...$parts);
    }

    #[Override]
    public function equals(object $other) : bool
    {
        return $other instanceof self && $this->namespace === $other->namespace;
    }

    #[Override]
    public function compare(object $other) : int
    {
        $thisStr = str_replace('\\', ' ', $this->namespace);

        if ($other instanceof self || $other instanceof FullyQualified) {
            $otherStr = str_replace('\\', ' ', (string) $other);

            return strcasecmp($thisStr, $otherStr);
        }

        if ($other instanceof FunctionName) {
            $otherStr = str_replace('\\', ' ', $other->name);

            return strcasecmp($thisStr, $otherStr);
        }

        if ($other instanceof Alias) {
            return strcasecmp($thisStr, $other->alias);
        }

        return 0;
    }
}
