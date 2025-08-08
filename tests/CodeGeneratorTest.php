<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruudk\CodeGenerator\Fixtures\TestEnum;

#[CoversClass(CodeGenerator::class)]
final class CodeGeneratorTest extends TestCase
{
    public function testConstructorWithNamespace() : void
    {
        $generator = new CodeGenerator('App\\Models');

        $result = $generator->dump([]);

        self::assertStringContainsString('namespace App\\Models;', $result);
    }

    public function testConstructorWithoutNamespace() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->dump([]);

        self::assertStringNotContainsString('namespace', $result);
    }

    public function testDumpWithSimpleContent() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->dump(['class Foo', '{', '}']);

        self::assertStringContainsString('<?php', $result);
        self::assertStringContainsString('declare(strict_types=1);', $result);
        self::assertStringContainsString('class Foo', $result);
    }

    public function testDumpWithCallable() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->dump(function () {
            yield 'class Bar';
            yield '{';
            yield '}';
        });

        self::assertStringContainsString('class Bar', $result);
    }

    public function testDumpWithIndentation() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->dump([
            'class Test {',
            Group::indent(1, [
                'public function method()',
                '{',
                Group::indent(1, ['return true;']),
                '}',
            ]),
            '}',
        ]);

        self::assertStringContainsString('    public function method()', $result);
        self::assertStringContainsString('        return true;', $result);
    }

    public function testImportClass() : void
    {
        $generator = new CodeGenerator('App\\Services');

        $alias = $generator->import('App\\Models\\User');

        self::assertSame('User', $alias);

        $result = $generator->dump([]);
        self::assertStringContainsString('use App\\Models\\User;', $result);
    }

    public function testImportClassWithConflict() : void
    {
        $generator = new CodeGenerator();

        $alias1 = $generator->import('App\\Models\\User');
        $alias2 = $generator->import('App\\Entities\\User');

        self::assertSame('User', $alias1);
        self::assertSame('User2', $alias2);

        $result = $generator->dump([]);
        self::assertStringContainsString('use App\\Models\\User;', $result);
        self::assertStringContainsString('use App\\Entities\\User as User2;', $result);
    }

    public function testImportFunction() : void
    {
        $generator = new CodeGenerator();

        $alias = $generator->import('function array_map');

        self::assertSame('function array_map', $alias);

        $result = $generator->dump([]);
        self::assertStringContainsString('use function array_map;', $result);
    }

    public function testImportEnum() : void
    {
        $generator = new CodeGenerator();

        $reference = $generator->import(TestEnum::OPTION_ONE);

        self::assertStringContainsString('TestEnum::OPTION_ONE', $reference);

        $result = $generator->dump([]);
        self::assertStringContainsString('use Ruudk\\CodeGenerator\\Fixtures\\TestEnum;', $result);
    }

    public function testImportByParent() : void
    {
        $generator = new CodeGenerator();

        $reference = $generator->import('App\\Models\\User', true);

        self::assertSame('Models\\User', $reference);

        $result = $generator->dump([]);
        self::assertStringContainsString('use App\\Models;', $result);
    }

    public function testImportSameNamespace() : void
    {
        $generator = new CodeGenerator('App\\Models');

        $generator->import('App\\Models\\User');

        $result = $generator->dump([]);
        self::assertStringNotContainsString('use App\\Models\\User;', $result);
    }

    public function testSplitFqcn() : void
    {
        $generator = new CodeGenerator();

        [$namespace, $class] = $generator->splitFqcn('App\\Models\\User');

        self::assertSame('App\\Models', $namespace);
        self::assertSame('User', $class);
    }

    public function testSplitFqcnWithoutNamespace() : void
    {
        $generator = new CodeGenerator();

        [$namespace, $class] = $generator->splitFqcn('SimpleClass');

        self::assertSame('', $namespace);
        self::assertSame('SimpleClass', $class);
    }

    public function testDumpAttribute() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->dumpAttribute('App\\Attributes\\Required');

        self::assertSame('#[Required]', $result);
    }

    public function testDumpClassReference() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->dumpClassReference('App\\Models\\User');

        self::assertSame('User::class', $result);
    }

    public function testDumpClassReferenceWithoutImport() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->dumpClassReference('App\\Models\\User', false);

        self::assertSame('\\App\\Models\\User::class', $result);
    }

    public function testDumpClassReferenceByParent() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->dumpClassReference('App\\Models\\User', true, true);

        self::assertSame('Models\\User::class', $result);
    }

    public function testMaybeNowDocWithSingleLine() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->maybeNowDoc('single line');

        self::assertSame("'single line'", $result);
    }

    public function testMaybeNowDocWithMultipleLines() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->maybeNowDoc("line 1\nline 2\nline 3");

        self::assertStringContainsString("<<<'EOD'", $result);
        self::assertStringContainsString('    line 1', $result);
        self::assertStringContainsString('    line 2', $result);
        self::assertStringContainsString('    line 3', $result);
        self::assertStringContainsString('    EOD', $result);
    }

    public function testMaybeNowDocWithCustomTag() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->maybeNowDoc("multi\nline", 'SQL');

        self::assertStringContainsString("<<<'SQL'", $result);
        self::assertStringContainsString('    SQL', $result);
    }

    public function testStatement() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->statement(['$x = 5']));

        self::assertSame(['$x = 5;'], $result);
    }

    public function testStatementWithGroup() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->statement([
            'if ($condition)',
            Group::indent(1, ['return true']),
        ]));

        self::assertCount(2, $result);
        self::assertSame('if ($condition)', $result[0]);
        self::assertInstanceOf(Group::class, $result[1]);
    }

    public function testSuffixLast() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->suffixLast(',', ['item1', 'item2', 'item3']));

        self::assertSame(['item1', 'item2', 'item3,'], $result);
    }

    public function testSuffixLastWithEmptyIterable() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->suffixLast(',', []));

        self::assertSame([], $result);
    }

    public function testWrap() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->wrap('[', ['content'], ']'));

        self::assertSame(['[content]'], $result);
    }

    public function testWrapWithMultipleLines() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->wrap('(', ['line1', 'line2'], ')'));

        self::assertSame(['(line1', 'line2)'], $result);
    }

    public function testMaybeWrapTrue() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->maybeWrap(true, '(', ['content'], ')'));

        self::assertSame(['(content)'], $result);
    }

    public function testMaybeWrapFalse() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->maybeWrap(false, '(', ['content'], ')'));

        self::assertSame(['content'], $result);
    }

    public function testPrefixFirst() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->prefixFirst('> ', ['line1', 'line2', 'line3']));

        self::assertSame(['> line1', 'line2', 'line3'], $result);
    }

    public function testPrefixFirstWithGroup() : void
    {
        $generator = new CodeGenerator();

        $group = Group::indent(1, ['content']);
        $result = iterator_to_array($generator->prefixFirst('prefix: ', [$group]));

        self::assertCount(1, $result);
        self::assertInstanceOf(Group::class, $result[0]);
    }

    public function testSuffixFirst() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->suffixFirst(':', ['key', 'value1', 'value2']));

        self::assertSame(['key:', 'value1', 'value2'], $result);
    }

    public function testJoin() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->join(', ', ['item1', 'item2', 'item3']);

        self::assertSame('item1, item2, item3', $result);
    }

    public function testJoinWithGroups() : void
    {
        $generator = new CodeGenerator();

        $result = $generator->join(', ', ['item1', new Group('nested'), 'item3']);

        self::assertSame('item1, , item3', $result);
    }

    public function testJoinFirstPairSimple() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->joinFirstPair(['first', 'second', 'third']));

        self::assertSame(['firstsecond', 'third'], $result);
    }

    public function testJoinFirstPairSingleElement() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->joinFirstPair(['only']));

        self::assertSame(['only'], $result);
    }

    public function testJoinFirstPairWithGroup() : void
    {
        $generator = new CodeGenerator();

        $group = Group::indent(1, ['content']);
        $result = iterator_to_array($generator->joinFirstPair(['prefix', $group, 'third']));

        self::assertCount(2, $result);
        self::assertInstanceOf(Group::class, $result[0]);
        self::assertSame('third', $result[1]);
    }

    public function testAllSuffix() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->allSuffix(',', ['item1', 'item2', 'item3']));

        self::assertSame(['item1,', 'item2,', 'item3,'], $result);
    }

    public function testAllSuffixSkipsComments() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->allSuffix(',', ['item1', '// comment', 'item2']));

        self::assertSame(['item1,', '// comment', 'item2,'], $result);
    }

    public function testAllSuffixWithGroup() : void
    {
        $generator = new CodeGenerator();

        $group = new Group(['item']);
        $result = iterator_to_array($generator->allSuffix(',', [$group]));

        self::assertCount(1, $result);
        self::assertInstanceOf(Group::class, $result[0]);
    }

    public function testDumpCallStaticMethod() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->dumpCall('App\\Utils\\Helper', 'process', ['$data'], true));

        self::assertSame(['Helper::process($data)'], $result);
    }

    public function testDumpCallConstructor() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->dumpCall('App\\Models\\User', '__construct', []));

        self::assertSame(['new User()'], $result);
    }

    public function testDumpCallInstanceMethod() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->dumpCall('$user', 'getName', []));

        self::assertSame(['$user->getName()'], $result);
    }

    public function testDumpCallWithMultipleArguments() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->dumpCall('$object', 'method', ['$arg1', '$arg2', '$arg3']));

        self::assertCount(3, $result);
        self::assertSame('$object->method(', $result[0]);
        self::assertInstanceOf(Group::class, $result[1]);
        self::assertSame(')', $result[2]);
    }

    public function testDumpCallWithIterableObject() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->dumpCall(['$object'], 'method', ['$arg']), false);

        self::assertCount(2, $result);
        self::assertSame('$object', $result[0]);
        self::assertInstanceOf(Group::class, $result[1]);
    }

    public function testDumpFunctionCall() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->dumpFunctionCall('array_map', ['$callback', '$array']));

        self::assertCount(3, $result);
        self::assertSame('array_map(', $result[0]);
        self::assertInstanceOf(Group::class, $result[1]);
        self::assertSame(')', $result[2]);
    }

    public function testDumpFunctionCallNoArgs() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->dumpFunctionCall('time', []));

        self::assertSame([], $result);
    }

    public function testDumpFunctionCallSingleArg() : void
    {
        $generator = new CodeGenerator();

        $result = iterator_to_array($generator->dumpFunctionCall('count', ['$array']));

        self::assertSame(['count($array)'], $result);
    }

    public function testResolveIterableWithString() : void
    {
        $result = CodeGenerator::resolveIterable('test string');

        self::assertSame(['test string'], $result);
    }

    public function testResolveIterableWithArray() : void
    {
        $result = CodeGenerator::resolveIterable(['line1', 'line2', 'line3']);

        self::assertSame(['line1', 'line2', 'line3'], $result);
    }

    public function testResolveIterableWithCallable() : void
    {
        $callable = function () {
            yield 'generated1';
            yield 'generated2';
        };

        $result = CodeGenerator::resolveIterable($callable);

        self::assertSame(['generated1', 'generated2'], $result);
    }

    public function testResolveIterableWithGenerator() : void
    {
        $generator = function () {
            yield 'item1';
            yield 'item2';
        };

        $result = CodeGenerator::resolveIterable($generator());

        self::assertSame(['item1', 'item2'], $result);
    }

    public function testCompleteCodeGeneration() : void
    {
        $generator = new CodeGenerator('App\\Services');

        $generator->import('App\\Models\\User');
        $generator->import('App\\Repositories\\UserRepository');

        $code = $generator->dump(function () {
            yield 'class UserService';
            yield '{';
            yield Group::indent(1, function () {
                yield 'public function __construct(';
                yield Group::indent(1, [
                    'private UserRepository $repository,',
                ]);
                yield ') {}';
                yield '';
                yield 'public function findUser(int $id): ?User';
                yield '{';
                yield Group::indent(1, [
                    'return $this->repository->find($id);',
                ]);
                yield '}';
            });
            yield '}';
        });

        self::assertStringContainsString('namespace App\\Services;', $code);
        self::assertStringContainsString('use App\\Models\\User;', $code);
        self::assertStringContainsString('use App\\Repositories\\UserRepository;', $code);
        self::assertStringContainsString('class UserService', $code);
        self::assertStringContainsString('    public function __construct(', $code);
        self::assertStringContainsString('        private UserRepository $repository,', $code);
        self::assertStringContainsString('    public function findUser(int $id): ?User', $code);
        self::assertStringContainsString('        return $this->repository->find($id);', $code);
    }

    public function testImportSorting() : void
    {
        $generator = new CodeGenerator();

        $generator->import('Zebra\\Class');
        $generator->import('App\\Models\\User');
        $generator->import('function array_map');
        $generator->import('App\\Models\\Post');
        $generator->import('Beta\\Class');

        $result = $generator->dump([]);

        $lines = explode("\n", $result);
        $useStatements = array_filter($lines, fn($line) => str_starts_with($line, 'use '));
        $useStatements = array_values($useStatements);

        self::assertCount(5, $useStatements);
        self::assertStringContainsString('App\\Models\\Post', $useStatements[0]);
        self::assertStringContainsString('App\\Models\\User', $useStatements[1]);
        self::assertStringContainsString('function array_map', $useStatements[2]);
        self::assertStringContainsString('Beta\\Class', $useStatements[3]);
        self::assertStringContainsString('Zebra\\Class', $useStatements[4]);
    }

    public function testSuffixFirstWithGroupAsFirstElement() : void
    {
        $generator = new CodeGenerator();

        $data = [
            Group::indent(1, ['inner content']),
            'second line',
        ];

        $result = iterator_to_array($generator->suffixFirst(',', $data));

        self::assertCount(2, $result);
        self::assertInstanceOf(Group::class, $result[0]);
        self::assertEquals('second line', $result[1]);
    }

    public function testJoinFirstPairWithEmptySecondGroup() : void
    {
        $generator = new CodeGenerator();

        $data = [
            'first',
            Group::indent(0, []),
            'third',
        ];

        $result = iterator_to_array($generator->joinFirstPair($data));

        self::assertContains('third', $result);
    }

    public function testDumpCallWithEmptyArgsOnIterable() : void
    {
        $generator = new CodeGenerator();

        $object = ['$object'];
        $result = [];
        foreach ($generator->dumpCall($object, 'method', []) as $item) {
            $result[] = $item;
        }

        self::assertCount(2, $result);
        self::assertEquals('$object', $result[0]);
        self::assertInstanceOf(Group::class, $result[1]);

        $groupLines = $result[1]->lines;
        $groupContent = is_callable($groupLines) ? iterator_to_array($groupLines()) : $groupLines;
        self::assertEquals('->method()', $groupContent[0]);
    }

    public function testDumpCallWithMultipleArgsOnIterable() : void
    {
        $generator = new CodeGenerator();

        $object = ['$object'];
        $args = ['"arg1"', '"arg2"', '"arg3"'];
        $result = [];
        foreach ($generator->dumpCall($object, 'method', $args, false, true) as $item) {
            $result[] = $item;
        }

        self::assertCount(2, $result);
        self::assertEquals('$object', $result[0]);
        self::assertInstanceOf(Group::class, $result[1]);

        $groupLines = $result[1]->lines;
        $groupContent = is_callable($groupLines) ? iterator_to_array($groupLines()) : $groupLines;
        self::assertEquals('->method(', $groupContent[0]);
        self::assertInstanceOf(Group::class, $groupContent[1]);
        self::assertEquals(')', $groupContent[2]);
    }
}
