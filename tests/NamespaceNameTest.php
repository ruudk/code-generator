<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class NamespaceNameTest extends TestCase
{
    public function testSingleNamespacePart() : void
    {
        $namespace = new NamespaceName('App');

        self::assertSame('App', $namespace->namespace);
        self::assertSame('App', (string) $namespace);
    }

    public function testMultipleNamespaceParts() : void
    {
        $namespace = new NamespaceName('App', 'Models', 'User');

        self::assertSame('App\\Models\\User', $namespace->namespace);
        self::assertSame('App\\Models\\User', (string) $namespace);
    }

    public function testNamespaceWithBackslashInParts() : void
    {
        $namespace = new NamespaceName('App\\Models', 'User');

        self::assertSame('App\\Models\\User', $namespace->namespace);
        self::assertSame('App\\Models\\User', (string) $namespace);
    }

    public function testFullNamespaceAsOnePart() : void
    {
        $namespace = new NamespaceName('App\\Models\\User');

        self::assertSame('App\\Models\\User', $namespace->namespace);
        self::assertSame('App\\Models\\User', (string) $namespace);
    }

    public function testMixedBackslashesAndSeparateParts() : void
    {
        $namespace = new NamespaceName('App', 'Models\\Entity', 'User');

        self::assertSame('App\\Models\\Entity\\User', $namespace->namespace);
        self::assertSame('App\\Models\\Entity\\User', (string) $namespace);
    }

    public function testEmptyPartsAreFiltered() : void
    {
        $namespace = new NamespaceName('App\\\\Models\\', '\\User');

        self::assertSame('App\\Models\\User', $namespace->namespace);
        self::assertSame('App\\Models\\User', (string) $namespace);
    }

    public function testLeadingBackslashIsFiltered() : void
    {
        $namespace = new NamespaceName('\\App\\Models\\User');

        self::assertSame('App\\Models\\User', $namespace->namespace);
        self::assertSame('App\\Models\\User', (string) $namespace);
    }

    public function testTrailingBackslashIsRemoved() : void
    {
        $namespace = new NamespaceName('App\\Models\\User\\');

        self::assertSame('App\\Models\\User', $namespace->namespace);
        self::assertSame('App\\Models\\User', (string) $namespace);
    }

    public function testThrowsExceptionForEmptyParts() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one non-empty part is required');

        new NamespaceName('', '\\\\', '');
    }

    public function testThrowsExceptionForOnlyBackslashes() : void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('At least one non-empty part is required');

        new NamespaceName('\\\\\\');
    }
}
