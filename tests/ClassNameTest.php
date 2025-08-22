<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class ClassNameTest extends TestCase
{
    public function testValidClassName() : void
    {
        $className = new ClassName('MyClass');

        self::assertSame('MyClass', $className->name);
        self::assertSame('MyClass', (string) $className);
    }

    public function testClassNameWithWhitespace() : void
    {
        $className = new ClassName('  MyClass  ');

        self::assertSame('MyClass', $className->name);
        self::assertSame('MyClass', (string) $className);
    }

    public function testThrowsExceptionForEmptyClassName() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class name cannot be empty');

        new ClassName('');
    }

    public function testThrowsExceptionForWhitespaceOnlyClassName() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class name cannot be empty');

        new ClassName('   ');
    }

    public function testThrowsExceptionForClassNameWithNamespaceSeparator() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class name cannot contain namespace separator');

        new ClassName('My\\Class');
    }

    public function testThrowsExceptionForClassNameWithLeadingBackslash() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class name cannot contain namespace separator');

        new ClassName('\\MyClass');
    }

    public function testThrowsExceptionForClassNameWithTrailingBackslash() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Class name cannot contain namespace separator');

        new ClassName('MyClass\\');
    }
}
