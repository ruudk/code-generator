<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class FullyQualifiedTest extends TestCase
{
    public function testSingleClassName() : void
    {
        $fqcn = new FullyQualified('MyClass');

        self::assertSame('MyClass', $fqcn->className->name);
        self::assertNull($fqcn->namespace);
        self::assertSame('MyClass', (string) $fqcn);
    }

    public function testClassWithNamespace() : void
    {
        $fqcn = new FullyQualified('App', 'Models', 'User');

        self::assertSame('User', $fqcn->className->name);
        self::assertSame('App\\Models', $fqcn->namespace?->namespace);
        self::assertSame('App\\Models\\User', (string) $fqcn);
    }

    public function testClassWithBackslashInParts() : void
    {
        $fqcn = new FullyQualified('App\\Models', 'User');

        self::assertSame('User', $fqcn->className->name);
        self::assertSame('App\\Models', $fqcn->namespace?->namespace);
        self::assertSame('App\\Models\\User', (string) $fqcn);
    }

    public function testFullyQualifiedStringAsOnePart() : void
    {
        $fqcn = new FullyQualified('App\\Models\\User');

        self::assertSame('User', $fqcn->className->name);
        self::assertSame('App\\Models', $fqcn->namespace?->namespace);
        self::assertSame('App\\Models\\User', (string) $fqcn);
    }

    public function testMixedBackslashesAndSeparateParts() : void
    {
        $fqcn = new FullyQualified('App', 'Models\\Entity', 'User');

        self::assertSame('User', $fqcn->className->name);
        self::assertSame('App\\Models\\Entity', $fqcn->namespace?->namespace);
        self::assertSame('App\\Models\\Entity\\User', (string) $fqcn);
    }

    public function testEmptyPartsAreFiltered() : void
    {
        $fqcn = new FullyQualified('App\\\\Models\\', '\\User');

        self::assertSame('User', $fqcn->className->name);
        self::assertSame('App\\Models', $fqcn->namespace?->namespace);
        self::assertSame('App\\Models\\User', (string) $fqcn);
    }

    public function testThrowsExceptionForEmptyParts() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one non-empty part is required');

        new FullyQualified('', '\\\\', '');
    }

    public function testLeadingBackslash() : void
    {
        $fqcn = new FullyQualified('\\App\\Models\\User');

        self::assertSame('User', $fqcn->className->name);
        self::assertSame('App\\Models', $fqcn->namespace?->namespace);
        self::assertSame('App\\Models\\User', (string) $fqcn);
    }
}
