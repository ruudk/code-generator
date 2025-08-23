<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use PHPUnit\Framework\TestCase;

final class ImportableTest extends TestCase
{
    public function testImportableInterfaceBasicUsage() : void
    {
        $className = new ClassName('MyClass');
        $fullyQualified = new FullyQualified('App\\Models\\User');
        $functionName = new FunctionName('sprintf');
        $namespaceName = new NamespaceName('App\\Models');
        $alias = new Alias('UserModel', $fullyQualified);

        // Test that all classes have the required interface methods and can be converted to strings
        $importables = [$className, $fullyQualified, $functionName, $namespaceName, $alias];

        foreach ($importables as $importable) {
            // Test basic Importable interface functionality
            self::assertTrue($importable->equals($importable));
            // Test that string conversion works
            $stringRepresentation = (string) $importable;
            self::assertNotEmpty($stringRepresentation);
        }
    }

    public function testFullyQualifiedMaybeFromStringWithImportable() : void
    {
        $className = new ClassName('MyClass');
        $functionName = new FunctionName('App\\Helpers\\format');
        $namespaceName = new NamespaceName('App\\Models');

        // Test with ClassName
        $result1 = FullyQualified::maybeFromString($className);
        self::assertSame('MyClass', (string) $result1);

        // Test with FunctionName - should convert its string representation
        $result2 = FullyQualified::maybeFromString($functionName);
        // FunctionName __toString includes "function " prefix, so this will create a class with that name
        self::assertSame('function App\\Helpers\\format', (string) $result2);

        // Test with NamespaceName
        $result3 = FullyQualified::maybeFromString($namespaceName);
        self::assertSame('App\\Models', (string) $result3);
    }

    public function testClassNameMaybeFromStringWithImportable() : void
    {
        $fullyQualified = new FullyQualified('User'); // No namespace, just class name
        $namespaceName = new NamespaceName('Models');

        // Test with FullyQualified (no namespace)
        $result1 = ClassName::maybeFromString($fullyQualified);
        self::assertSame('User', $result1->name);

        // Test with NamespaceName
        $result2 = ClassName::maybeFromString($namespaceName);
        self::assertSame('Models', $result2->name);
    }

    public function testFunctionNameMaybeFromStringWithImportable() : void
    {
        $fullyQualified = new FullyQualified('App\\Helpers\\format');
        $namespaceName = new NamespaceName('App\\Helpers');

        // Test with FullyQualified
        $result1 = FunctionName::maybeFromString($fullyQualified);
        self::assertSame('function App\\Helpers\\format', (string) $result1);

        // Test with NamespaceName
        $result2 = FunctionName::maybeFromString($namespaceName);
        self::assertSame('function App\\Helpers', (string) $result2);
    }

    public function testNamespaceNameMaybeFromStringWithImportable() : void
    {
        $fullyQualified = new FullyQualified('App\\Models\\User');
        $className = new ClassName('Helper');

        // Test with FullyQualified
        $result1 = NamespaceName::maybeFromString($fullyQualified);
        self::assertSame('App\\Models\\User', $result1->namespace);

        // Test with ClassName
        $result2 = NamespaceName::maybeFromString($className);
        self::assertSame('Helper', $result2->namespace);
    }

    public function testCodeGeneratorImportWithImportableTypes() : void
    {
        $generator = new CodeGenerator('App\\Controllers');

        $className = new ClassName('User');
        $fullyQualified = new FullyQualified('App\\Models\\Product');
        $functionName = new FunctionName('sprintf');
        $namespaceName = new NamespaceName('App\\Services');

        // Test importing different Importable types
        $alias1 = $generator->import($className);
        $alias2 = $generator->import($fullyQualified);
        $alias3 = $generator->import($functionName);
        $alias4 = $generator->import($namespaceName);

        self::assertSame('User', $alias1);
        self::assertSame('Product', $alias2);
        self::assertSame('sprintf', $alias3);
        self::assertSame('Services', $alias4);
    }

    public function testCodeGeneratorImportByParentWithImportableTypes() : void
    {
        $generator = new CodeGenerator('App\\Controllers');

        $fullyQualified = new FullyQualified('App\\Models\\User');
        $className = new ClassName('Helper');

        // Test importByParent with different Importable types
        $result1 = $generator->importByParent($fullyQualified);
        $result2 = $generator->importByParent($className);

        self::assertSame('Models\\User', $result1);
        self::assertSame('Helper', $result2);
    }

    public function testAliasConstructorWithImportableTypes() : void
    {
        $fullyQualified = new FullyQualified('App\\Models\\User');
        $functionName = new FunctionName('App\\Helpers\\format');
        $namespaceName = new NamespaceName('App\\Services');
        $className = new ClassName('Helper');

        // Test creating aliases with different Importable types
        $alias1 = new Alias('UserModel', $fullyQualified);
        $alias2 = new Alias('format', $functionName);
        $alias3 = new Alias('Services', $namespaceName);
        $alias4 = new Alias('HelperClass', $className);

        self::assertSame('UserModel', $alias1->alias);
        self::assertSame($fullyQualified, $alias1->target);

        self::assertSame('format', $alias2->alias);
        self::assertSame($functionName, $alias2->target);

        self::assertSame('Services', $alias3->alias);
        self::assertSame($namespaceName, $alias3->target);

        self::assertSame('HelperClass', $alias4->alias);
        self::assertSame($className, $alias4->target);
    }
}
