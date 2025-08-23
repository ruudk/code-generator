<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use Override;
use Stringable;

final readonly class FullyQualified implements ImportableInterface
{
    public ClassName $className;
    public ?NamespaceName $namespace;

    public function __construct(
        string | ImportableInterface $part,
        string | ImportableInterface ...$parts,
    ) {
        // Convert ImportableInterface objects to strings
        $stringPart = $part instanceof ImportableInterface ? (string) $part : $part;
        $stringParts = array_map(
            fn($p) => $p instanceof ImportableInterface ? (string) $p : $p,
            $parts
        );
        
        $flattened = array_filter(
            explode('\\', implode('\\', [$stringPart, ...$stringParts])),
            fn($p) => $p !== '',
        );

        if ($flattened === []) {
            throw new InvalidArgumentException('At least one non-empty part is required');
        }

        $classNamePart = array_pop($flattened);
        $this->className = new ClassName($classNamePart);

        $this->namespace = $flattened !== []
            ? new NamespaceName(implode('\\', $flattened))
            : null;
    }

    /**
     * @phpstan-return ($input is null ? null : self)
     */
    public static function maybeFromString(null | self | string | ImportableInterface $input) : ?self
    {
        if ($input === null) {
            return null;
        }

        if ($input instanceof self) {
            return $input;
        }

        return new self($input);
    }

    #[Override]
    public function __toString() : string
    {
        if ($this->namespace === null) {
            return (string) $this->className;
        }

        return $this->namespace . '\\' . $this->className;
    }

    public function equals(object $other) : bool
    {
        return $other instanceof self && (string) $this === (string) $other;
    }

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
