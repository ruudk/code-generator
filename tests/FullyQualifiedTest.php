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

    public function testIsInNamespace() : void
    {
        $fqcn1 = new FullyQualified('App\\Models\\User');
        $fqcn2 = new FullyQualified('User');

        $appModels = new NamespaceName('App\\Models');
        $app = new NamespaceName('App');
        $core = new NamespaceName('Core');

        self::assertTrue($fqcn1->isInNamespace($appModels));
        self::assertFalse($fqcn1->isInNamespace($app));
        self::assertFalse($fqcn1->isInNamespace($core));
        self::assertFalse($fqcn1->isInNamespace(null));

        self::assertFalse($fqcn2->isInNamespace($appModels));
        self::assertFalse($fqcn2->isInNamespace($app));
        self::assertTrue($fqcn2->isInNamespace(null)); // Class without namespace
    }

    public function testGetRelativePathFrom() : void
    {
        $fqcn = new FullyQualified('App\\Models\\User\\Profile');

        $app = new NamespaceName('App');
        $appModels = new NamespaceName('App\\Models');
        $appModelsUser = new NamespaceName('App\\Models\\User');
        $core = new NamespaceName('Core');

        // Direct children and sub-namespaces
        self::assertSame('Models\\User\\Profile', $fqcn->getRelativePathFrom($app));
        self::assertSame('User\\Profile', $fqcn->getRelativePathFrom($appModels));
        self::assertSame('Profile', $fqcn->getRelativePathFrom($appModelsUser));

        // Not in a sub-namespace - returns full path
        self::assertSame('App\\Models\\User\\Profile', $fqcn->getRelativePathFrom($core));

        // Null namespace
        self::assertSame('App\\Models\\User\\Profile', $fqcn->getRelativePathFrom(null));
    }

    public function testGetRelativePathFromForClassWithoutNamespace() : void
    {
        $fqcn = new FullyQualified('SimpleClass');

        $app = new NamespaceName('App');

        self::assertSame('SimpleClass', $fqcn->getRelativePathFrom($app));
        self::assertSame('SimpleClass', $fqcn->getRelativePathFrom(null));
    }

    public function testMaybeFromString() : void
    {
        // Test with string input
        $result1 = FullyQualified::maybeFromString('App\\Models\\User');
        // @phpstan-ignore-next-line
        self::assertNotNull($result1);
        self::assertSame('App\\Models\\User', (string) $result1);

        // Test with null input
        $result2 = FullyQualified::maybeFromString(null);
        // @phpstan-ignore-next-line
        self::assertNull($result2);

        // Test with existing FullyQualified instance
        $existing = new FullyQualified('App\\Services\\UserService');
        $result3 = FullyQualified::maybeFromString($existing);
        self::assertSame($existing, $result3);

        // Test with NamespaceName instance
        $namespaceName = new NamespaceName('App\\Models');
        $result4 = FullyQualified::maybeFromString($namespaceName);
        // @phpstan-ignore-next-line
        self::assertNotNull($result4);
        self::assertSame('App\\Models', (string) $result4);
    }

    public function testEquals() : void
    {
        $fqcn1 = new FullyQualified('App\\Models\\User');
        $fqcn2 = new FullyQualified('App', 'Models', 'User');
        $fqcn3 = new FullyQualified('App\\Models\\Post');
        $fqcn4 = new FullyQualified('User');

        self::assertTrue($fqcn1->equals($fqcn2));
        self::assertFalse($fqcn1->equals($fqcn3));
        self::assertFalse($fqcn1->equals($fqcn4));
    }
}
