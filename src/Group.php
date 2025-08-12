<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use Closure;
use Generator;

/**
 * @phpstan-import-type CodeLines from CodeGenerator
 * @phpstan-import-type CodeLine from CodeGenerator
 */
final class Group
{
    public int $indention = 0;

    /**
     * @var array<CodeLine>
     */
    public readonly array $lines;

    /**
     * @param CodeLines $lines
     */
    public function __construct(
        array | Closure | Generator | string $lines,
    ) {
        $this->lines = CodeGenerator::resolveIterable($lines);
    }

    /**
     * @param CodeLines $lines
     */
    public static function indent(
        int $indention,
        array | Closure | Generator | string $lines,
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
