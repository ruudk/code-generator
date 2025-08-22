<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FunctionNameTest extends TestCase
{
    public function testSimpleFunctionName() : void
    {
        $function = new FunctionName('array_map');

        self::assertSame('array_map', $function->name);
        self::assertSame('array_map', $function->shortName);
        self::assertSame('function array_map', (string) $function);
    }

    public function testNamespacedFunctionName() : void
    {
        $function = new FunctionName('Symfony\\Component\\String\\u');

        self::assertSame('Symfony\\Component\\String\\u', $function->name);
        self::assertSame('u', $function->shortName);
        self::assertSame('function Symfony\\Component\\String\\u', (string) $function);
    }

    public function testThrowsExceptionForEmptyName() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Function name cannot be empty');

        new FunctionName('');
    }

    public function testEquals() : void
    {
        $function1 = new FunctionName('array_map');
        $function2 = new FunctionName('array_map');
        $function3 = new FunctionName('array_filter');

        self::assertTrue($function1->equals($function2));
        self::assertFalse($function1->equals($function3));
    }

    public function testEqualsWithNamespace() : void
    {
        $function1 = new FunctionName('Symfony\\Component\\String\\u');
        $function2 = new FunctionName('Symfony\\Component\\String\\u');
        $function3 = new FunctionName('Symfony\\Component\\String\\b');

        self::assertTrue($function1->equals($function2));
        self::assertFalse($function1->equals($function3));
    }
}
