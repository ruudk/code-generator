<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

/**
 * @phpstan-import-type CodeLineIterable from CodeGenerator
 * @phpstan-import-type LazyCodeLineIterable from CodeGenerator
 */
final class Group
{
    public int $indention = 0;

    /**
     * @var array<string|Group>
     */
    public readonly array $lines;

    /**
     * @param LazyCodeLineIterable|CodeLineIterable|string $lines
     */
    public function __construct(
        callable | iterable | string $lines,
    ) {
        $this->lines = CodeGenerator::resolveIterable($lines);
    }

    /**
     * @param LazyCodeLineIterable|CodeLineIterable|string $lines
     */
    public static function indent(
        int $indention,
        callable | iterable | string $lines,
    ) : self {
        $group = new self($lines);
        $group->indention = $indention;

        return $group;
    }

    public function isEmpty() : bool
    {
        return $this->lines === [];
    }
}
