<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use Override;

final readonly class Alias implements Importable
{
    public string $alias;
    public Importable $target;

    public function __construct(
        string $alias,
        Importable $target,
    ) {
        $alias = trim($alias);

        if ($alias === '') {
            throw new InvalidArgumentException('Alias cannot be empty');
        }

        if (str_contains($alias, '\\')) {
            throw new InvalidArgumentException('Alias cannot contain namespace separator');
        }

        $this->alias = $alias;
        $this->target = $target;
    }

    #[Override]
    public function __toString() : string
    {
        return sprintf('%s as %s', $this->target, $this->alias);
    }

    #[Override]
    public function equals(object $other) : bool
    {
        return $other instanceof self
            && $this->alias === $other->alias
            && $this->target->equals($other->target);
    }

    #[Override]
    public function compare(object $other) : int
    {
        // Aliases should sort by their target, not their alias name
        return $this->target->compare($other);
    }

    /**
     * Generate the use statement for this alias
     */
    public function toUseStatement() : string
    {
        if ($this->target instanceof FunctionName) {
            return sprintf('use %s as %s;', $this->target, $this->alias);
        }

        return sprintf('use %s as %s;', $this->target, $this->alias);
    }
}
