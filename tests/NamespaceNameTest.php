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

    public function testLastPart() : void
    {
        $namespace1 = new NamespaceName('App');
        self::assertSame('App', $namespace1->lastPart);

        $namespace2 = new NamespaceName('App\\Models');
        self::assertSame('Models', $namespace2->lastPart);

        $namespace3 = new NamespaceName('App\\Models\\User');
        self::assertSame('User', $namespace3->lastPart);
    }

    public function testIsSubNamespaceOf() : void
    {
        $parent = new NamespaceName('App');
        $child = new NamespaceName('App\\Models');
        $grandchild = new NamespaceName('App\\Models\\User');
        $sibling = new NamespaceName('Core');
        $cousin = new NamespaceName('Core\\Models');

        self::assertTrue($child->isSubNamespaceOf($parent));
        self::assertTrue($grandchild->isSubNamespaceOf($parent));
        self::assertTrue($grandchild->isSubNamespaceOf($child));

        self::assertFalse($parent->isSubNamespaceOf($child));
        self::assertFalse($sibling->isSubNamespaceOf($parent));
        self::assertFalse($cousin->isSubNamespaceOf($parent));
        self::assertFalse($parent->isSubNamespaceOf($parent)); // Not a sub of itself
    }

    public function testIsDirectChildOf() : void
    {
        $parent = new NamespaceName('App');
        $directChild = new NamespaceName('App\\Models');
        $grandchild = new NamespaceName('App\\Models\\User');
        $greatGrandchild = new NamespaceName('App\\Models\\User\\Profile');

        self::assertTrue($directChild->isDirectChildOf($parent));
        self::assertFalse($grandchild->isDirectChildOf($parent)); // Not direct
        self::assertFalse($greatGrandchild->isDirectChildOf($parent)); // Not direct

        self::assertTrue($grandchild->isDirectChildOf($directChild));
        self::assertTrue($greatGrandchild->isDirectChildOf($grandchild));

        self::assertFalse($parent->isDirectChildOf($parent)); // Not a child of itself
    }

    public function testGetRelativePathFrom() : void
    {
        $parent = new NamespaceName('App');
        $child = new NamespaceName('App\\Models');
        $grandchild = new NamespaceName('App\\Models\\User');
        $sibling = new NamespaceName('Core');

        self::assertSame('Models', $child->getRelativePathFrom($parent));
        self::assertSame('Models\\User', $grandchild->getRelativePathFrom($parent));
        self::assertSame('User', $grandchild->getRelativePathFrom($child));

        // When not a sub-namespace, return full namespace
        self::assertSame('Core', $sibling->getRelativePathFrom($parent));
        self::assertSame('App', $parent->getRelativePathFrom($child));
    }

    public function testWith() : void
    {
        $namespace = new NamespaceName('App');
        $extended = $namespace->with('Models');
        $further = $extended->with('User', 'Profile');

        self::assertSame('App', $namespace->namespace);
        self::assertSame('App\\Models', $extended->namespace);
        self::assertSame('App\\Models\\User\\Profile', $further->namespace);
    }

    public function testEquals() : void
    {
        $namespace1 = new NamespaceName('App\\Models');
        $namespace2 = new NamespaceName('App', 'Models');
        $namespace3 = new NamespaceName('App\\Models\\User');

        self::assertTrue($namespace1->equals($namespace2));
        self::assertFalse($namespace1->equals($namespace3));
    }
}
