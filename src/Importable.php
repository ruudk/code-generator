<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use Stringable;

/**
 * Interface for classes that can be imported in code generation
 */
interface Importable extends Stringable
{
    /**
     * Check if this importable is equal to another object
     */
    public function equals(object $other) : bool;

    /**
     * Compare this importable with another object for sorting
     */
    public function compare(object $other) : int;
}
