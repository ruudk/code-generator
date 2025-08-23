<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use Stringable;

/**
 * Interface for classes that can be imported and used in code generation.
 * 
 * This interface allows objects to be passed directly to FullyQualified 
 * and other constructors without needing to cast them to strings first.
 */
interface ImportableInterface extends Stringable
{
    /**
     * Check if this object equals another object
     */
    public function equals(object $other): bool;

    /**
     * Compare this object with another object for sorting
     */
    public function compare(object $other): int;
}