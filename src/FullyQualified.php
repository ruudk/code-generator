<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use Override;

final readonly class FullyQualified implements Importable
{
    public ClassName $className;
    public ?NamespaceName $namespace;

    public function __construct(
        Importable | string $part,
        Importable | string ...$parts,
    ) {
        $flattened = array_filter(
            explode(
                '\\',
                implode(
                    '\\',
                    array_map(strval(...), [$part, ...$parts]),
                ),
            ),
            fn($p) => $p !== '',
        );

        if ($flattened === []) {
            throw new InvalidArgumentException('At least one non-empty part is required');
        }

        $classNamePart = array_pop($flattened);
        $this->className = new ClassName($classNamePart);

        $this->namespace = $flattened !== [] ? new NamespaceName(implode('\\', $flattened)) : null;
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

    /**
     * Check if this class is in the given namespace
     */
    public function isInNamespace(?NamespaceName $namespace) : bool
    {
        if ($namespace === null || $this->namespace === null) {
            return $namespace === null && $this->namespace === null;
        }

        return $this->namespace->equals($namespace);
    }

    /**
     * Get the relative path from a parent namespace with the class name
     */
    public function getRelativePathFrom(?NamespaceName $parent) : string
    {
        // If no parent namespace given, return full path
        if ($parent === null) {
            return (string) $this;
        }

        // If this class has no namespace, just return the class name
        if ($this->namespace === null) {
            return (string) $this->className;
        }

        if ($this->namespace->equals($parent)) {
            return (string) $this->className;
        }

        if ($this->namespace->isSubNamespaceOf($parent)) {
            return $this->namespace->getRelativePathFrom($parent) . '\\' . $this->className;
        }

        // Not in a sub-namespace, return full path
        return (string) $this;
    }

    #[Override]
    public function __toString() : string
    {
        if ($this->namespace === null) {
            return (string) $this->className;
        }

        return $this->namespace . '\\' . $this->className;
    }

    #[Override]
    public function equals(object $other) : bool
    {
        return $other instanceof self && (string) $this === (string) $other;
    }

    #[Override]
    public function compare(object $other) : int
    {
        $thisStr = str_replace('\\', ' ', (string) $this);

        if ($other instanceof self || $other instanceof NamespaceName) {
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
