<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use PHPUnit\Framework\TestCase;

final class ImportableInterfaceTest extends TestCase
{
    private CodeGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new CodeGenerator('App\\Test');
    }

    public function testClassNameImplementsImportableInterface(): void
    {
        $className = new ClassName('User');
        
        self::assertInstanceOf(ImportableInterface::class, $className);
        self::assertSame('User', (string) $className);
    }

    public function testFullyQualifiedImplementsImportableInterface(): void
    {
        $fqcn = new FullyQualified('App\\Models\\User');
        
        self::assertInstanceOf(ImportableInterface::class, $fqcn);
        self::assertSame('App\\Models\\User', (string) $fqcn);
    }

    public function testFunctionNameImplementsImportableInterface(): void
    {
        $function = new FunctionName('array_map');
        
        self::assertInstanceOf(ImportableInterface::class, $function);
        self::assertSame('function array_map', (string) $function);
    }

    public function testNamespaceNameImplementsImportableInterface(): void
    {
        $namespace = new NamespaceName('App\\Models');
        
        self::assertInstanceOf(ImportableInterface::class, $namespace);
        self::assertSame('App\\Models', (string) $namespace);
    }

    public function testAliasImplementsImportableInterface(): void
    {
        $target = new FullyQualified('App\\Models\\User');
        $alias = new Alias('UserModel', $target);
        
        self::assertInstanceOf(ImportableInterface::class, $alias);
        self::assertSame('App\\Models\\User as UserModel', (string) $alias);
    }

    public function testFullyQualifiedCanAcceptImportableInterface(): void
    {
        $className = new ClassName('User');
        $namespace = new NamespaceName('App\\Models');
        
        // This should work without needing to cast to string
        $fqcn = new FullyQualified($namespace, $className);
        
        self::assertSame('App\\Models\\User', (string) $fqcn);
    }

    public function testFullyQualifiedMaybeFromStringAcceptsImportableInterface(): void
    {
        $className = new ClassName('User');
        
        $fqcn = FullyQualified::maybeFromString($className);
        
        self::assertInstanceOf(FullyQualified::class, $fqcn);
        self::assertSame('User', (string) $fqcn);
    }

    public function testImportAcceptsImportableInterface(): void
    {
        $className = new ClassName('User');
        
        $alias = $this->generator->import($className);
        
        self::assertSame('User', $alias);
    }

    public function testImportAcceptsFullyQualifiedObject(): void
    {
        $fqcn = new FullyQualified('App\\Models\\User');
        
        $alias = $this->generator->import($fqcn);
        
        self::assertSame('User', $alias);
    }

    public function testImportAcceptsFunctionNameObject(): void
    {
        $function = new FunctionName('array_map');
        
        $alias = $this->generator->import($function);
        
        self::assertSame('array_map', $alias);
    }

    public function testImportAcceptsNamespaceNameObject(): void
    {
        $namespace = new NamespaceName('App\\Models');
        
        $alias = $this->generator->import($namespace);
        
        self::assertSame('Models', $alias);
    }

    public function testAliasCanAcceptImportableInterface(): void
    {
        $className = new ClassName('User');
        
        $alias = new Alias('UserModel', $className);
        
        self::assertInstanceOf(ImportableInterface::class, $alias->target);
        self::assertSame('User as UserModel', (string) $alias);
    }

    public function testCodeGeneratorConstructorAcceptsImportableInterface(): void
    {
        $namespace = new NamespaceName('App\\Services');
        
        $generator = new CodeGenerator($namespace);
        
        // This test passes if no exception is thrown
        self::assertInstanceOf(CodeGenerator::class, $generator);
    }

    public function testDumpAttributeAcceptsImportableInterface(): void
    {
        $fqcn = new FullyQualified('App\\Attributes\\Route');
        
        $attribute = $this->generator->dumpAttribute($fqcn);
        
        self::assertSame('#[Route]', $attribute);
    }

    public function testDumpClassReferenceAcceptsImportableInterface(): void
    {
        $fqcn = new FullyQualified('App\\Models\\User');
        
        $classRef = $this->generator->dumpClassReference($fqcn);
        
        self::assertSame('User::class', $classRef);
    }

    public function testImportByParentAcceptsImportableInterface(): void
    {
        $fqcn = new FullyQualified('App\\Models\\User');
        
        $result = $this->generator->importByParent($fqcn);
        
        self::assertSame('Models\\User', $result);
    }
}