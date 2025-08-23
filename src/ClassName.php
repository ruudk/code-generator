<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use Override;
use Stringable;

final readonly class ClassName implements ImportableInterface
{
    /**
     * @var non-empty-string
     */
    public string $name;

    public function __construct(
        string $name,
    ) {
        $name = trim($name);

        if ($name === '') {
            throw new InvalidArgumentException('Class name cannot be empty');
        }

        if (str_contains($name, '\\')) {
            throw new InvalidArgumentException('Class name cannot contain namespace separator');
        }

        $this->name = $name;
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

        return new self((string) $input);
    }

    #[Override]
    public function __toString() : string
    {
        return $this->name;
    }

    public function equals(object $other) : bool
    {
        return $other instanceof self && $this->name === $other->name;
    }

    public function compare(object $other) : int
    {
        if ($other instanceof self) {
            return strcasecmp($this->name, $other->name);
        }

        if ($other instanceof Alias) {
            return strcasecmp($this->name, $other->alias);
        }

        if ($other instanceof FunctionName) {
            return strcasecmp($this->name, $other->shortName);
        }

        if ($other instanceof FullyQualified || $other instanceof NamespaceName) {
            return strcasecmp($this->name, (string) $other);
        }

        return 0;
    }
}
