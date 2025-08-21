<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use Closure;
use Generator;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Ruudk\CodeGenerator\Fixtures\TestEnum;

/**
 * @phpstan-import-type CodeLines from CodeGenerator
 */
#[CoversClass(CodeGenerator::class)]
final class CodeGeneratorTest extends TestCase
{
    private CodeGenerator $generator;

    #[Override]
    protected function setUp() : void
    {
        parent::setUp();

        $this->generator = new CodeGenerator();
    }

    /**
     * @param CodeLines $content
     */
    private function assertGeneratedCode(string $expected, array | Closure | Generator | string $content) : void
    {
        self::assertSame($expected, $this->generator->dump($content));
    }

    public function testConstructorWithNamespace() : void
    {
        $this->generator = new CodeGenerator('App\\Models');

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Models;

            PHP;

        $this->assertGeneratedCode($expected, []);
    }

    public function testConstructorWithoutNamespace() : void
    {
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            PHP;

        $this->assertGeneratedCode($expected, []);
    }

    public function testDumpWithSimpleContent() : void
    {
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            class Foo
            {
            }

            PHP;

        $this->assertGeneratedCode($expected, ['class Foo', '{', '}']);
    }

    public function testDumpWithCallable() : void
    {
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            class Bar
            {
            }

            PHP;

        $this->assertGeneratedCode($expected, function () {
            yield 'class Bar';
            yield '{';
            yield '}';
        });
    }

    public function testDumpWithIndentation() : void
    {
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            class Test {
                public function method()
                {
                    return true;
                }
            }

            PHP;

        $this->assertGeneratedCode($expected, [
            'class Test {',
            Group::indent([
                'public function method()',
                '{',
                Group::indent(['return true;']),
                '}',
            ]),
            '}',
        ]);
    }

    public function testImportClass() : void
    {
        $this->generator = new CodeGenerator('App\\Services');

        $alias = $this->generator->import('App\\Models\\User');

        self::assertSame('User', $alias);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Services;

            use App\Models\User;

            PHP;

        $this->assertGeneratedCode($expected, []);
    }

    public function testImportClassWithConflict() : void
    {
        $alias1 = $this->generator->import('App\\Models\\User');
        $alias2 = $this->generator->import('App\\Entities\\User');

        self::assertSame('User', $alias1);
        self::assertSame('User2', $alias2);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use App\Entities\User as User2;
            use App\Models\User;

            PHP;

        $this->assertGeneratedCode($expected, []);
    }

    public function testImportFunction() : void
    {
        $alias = $this->generator->import('function array_map');

        self::assertSame('function array_map', $alias);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use function array_map;

            PHP;

        $this->assertGeneratedCode($expected, []);
    }

    public function testImportEnum() : void
    {
        $reference = $this->generator->import(TestEnum::OPTION_ONE);

        self::assertSame('TestEnum::OPTION_ONE', $reference);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use Ruudk\CodeGenerator\Fixtures\TestEnum;

            PHP;

        $this->assertGeneratedCode($expected, []);
    }

    public function testImportByParent() : void
    {
        $reference = $this->generator->import('App\\Models\\User', true);

        self::assertSame('Models\\User', $reference);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use App\Models;

            PHP;

        $this->assertGeneratedCode($expected, []);
    }

    public function testImportSameNamespace() : void
    {
        $this->generator = new CodeGenerator('App\\Models');

        $this->generator->import('App\\Models\\User');

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Models;

            PHP;

        $this->assertGeneratedCode($expected, []);
    }

    public function testSplitFqcn() : void
    {
        [$namespace, $class] = $this->generator->splitFqcn('App\\Models\\User');

        self::assertSame('App\\Models', $namespace);
        self::assertSame('User', $class);
    }

    public function testSplitFqcnWithoutNamespace() : void
    {
        [$namespace, $class] = $this->generator->splitFqcn('SimpleClass');

        self::assertSame('', $namespace);
        self::assertSame('SimpleClass', $class);
    }

    public function testDumpAttribute() : void
    {
        $result = $this->generator->dumpAttribute('App\\Attributes\\Required');

        self::assertSame('#[Required]', $result);
    }

    public function testDumpClassReference() : void
    {
        $result = $this->generator->dumpClassReference('App\\Models\\User');

        self::assertSame('User::class', $result);
    }

    public function testDumpClassReferenceWithoutImport() : void
    {
        $result = $this->generator->dumpClassReference('App\\Models\\User', false);

        self::assertSame('\\App\\Models\\User::class', $result);
    }

    public function testDumpClassReferenceByParent() : void
    {
        $result = $this->generator->dumpClassReference('App\\Models\\User', true, true);

        self::assertSame('Models\\User::class', $result);
    }

    public function testMaybeNowDocWithSingleLine() : void
    {
        $result = $this->generator->maybeNowDoc('single line');

        self::assertSame("'single line'", $result);
    }

    public function testMaybeNowDocWithMultipleLines() : void
    {
        $result = $this->generator->maybeNowDoc("line 1\nline 2\nline 3");

        $expected = <<<'PHP'
            <<<'EOD'
                line 1
                line 2
                line 3
                EOD
            PHP;

        self::assertSame($expected, $result);
    }

    public function testMaybeNowDocWithCustomTag() : void
    {
        $result = $this->generator->maybeNowDoc("multi\nline", 'SQL');

        $expected = <<<'PHP'
            <<<'SQL'
                multi
                line
                SQL
            PHP;

        self::assertSame($expected, $result);
    }

    public function testStatement() : void
    {
        $result = $this->generator->statement(['$x = 5']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            $x = 5;

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testStatementWithGroup() : void
    {
        $result = $this->generator->statement([
            'if ($condition)',
            Group::indent(['return true']),
        ]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            if ($condition)
                return true;

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testSuffixLast() : void
    {
        $result = $this->generator->suffixLast(',', ['item1', 'item2', 'item3']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            item1
            item2
            item3,

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testSuffixLastWithEmptyIterable() : void
    {
        $result = $this->generator->suffixLast(',', []);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testWrap() : void
    {
        $result = $this->generator->wrap('[', ['content'], ']');
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            [content]

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testWrapWithMultipleLines() : void
    {
        $result = $this->generator->wrap('(', ['line1', 'line2'], ')');
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            (line1
            line2)

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testMaybeWrapTrue() : void
    {
        $result = $this->generator->maybeWrap(true, '(', ['content'], ')');
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            (content)

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testMaybeWrapFalse() : void
    {
        $result = $this->generator->maybeWrap(false, '(', ['content'], ')');
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            content

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testPrefixFirst() : void
    {
        $result = $this->generator->prefixFirst('> ', ['line1', 'line2', 'line3']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            > line1
            line2
            line3

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testPrefixFirstWithGroup() : void
    {
        $group = Group::indent(['content']);
        $result = $this->generator->prefixFirst('prefix: ', [$group]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

                prefix: content

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testSuffixFirst() : void
    {
        $result = $this->generator->suffixFirst(':', ['key', 'value1', 'value2']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            key:
            value1
            value2

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testJoin() : void
    {
        $result = $this->generator->join(', ', ['item1', 'item2', 'item3']);

        self::assertSame('item1, item2, item3', $result);
    }

    public function testJoinWithGroups() : void
    {
        $result = $this->generator->join(', ', ['item1', new Group('nested'), 'item3']);

        self::assertSame('item1, , item3', $result);
    }

    public function testJoinFirstPairSimple() : void
    {
        $result = $this->generator->joinFirstPair(['first', 'second', 'third']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            firstsecond
            third

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testJoinFirstPairSingleElement() : void
    {
        $result = $this->generator->joinFirstPair(['only']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            only

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testJoinFirstPairWithGroup() : void
    {
        $group = Group::indent(['content']);
        $result = $this->generator->joinFirstPair(['prefix', $group, 'third']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

                prefixcontent
            third

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testAllSuffix() : void
    {
        $result = $this->generator->allSuffix(',', ['item1', 'item2', 'item3']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            item1,
            item2,
            item3,

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testAllSuffixSkipsComments() : void
    {
        $result = $this->generator->allSuffix(',', ['item1', '// comment', 'item2']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            item1,
            // comment
            item2,

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testAllSuffixWithGroup() : void
    {
        $group = new Group(['item']);
        $result = $this->generator->allSuffix(',', [$group]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            item,

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpCallStaticMethod() : void
    {
        $result = $this->generator->dumpCall('App\\Utils\\Helper', 'process', ['$data'], true);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use App\Utils\Helper;

            Helper::process($data)

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpCallConstructor() : void
    {
        $result = $this->generator->dumpCall('App\\Models\\User', '__construct', []);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use App\Models\User;

            new User()

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpCallInstanceMethod() : void
    {
        $result = $this->generator->dumpCall('$user', 'getName', []);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            $user->getName()

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpCallWithArrayOfGenerators() : void
    {
        $true = function () : Generator {
            yield 'true';
        };

        $false = function () : Generator {
            yield 'false';
        };

        $result = $this->generator->dumpCall('$var', 'method', [
            $true(),
            $false(),
        ]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            $var->method(
                true,
                false,
            )

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpCallWithMultipleArguments() : void
    {
        $result = $this->generator->dumpCall('$object', 'method', ['$arg1', '$arg2', '$arg3']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            $object->method(
                $arg1,
                $arg2,
                $arg3,
            )

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpCallWithIterableObject() : void
    {
        $result = $this->generator->dumpCall(['$object'], 'method', ['$arg']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            $object
                ->method($arg)

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpFunctionCall() : void
    {
        $result = $this->generator->dumpFunctionCall('array_map', ['$callback', '$array']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            array_map(
                $callback,
                $array,
            )

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpFunctionCallNoArgs() : void
    {
        // Note: This is the current behavior - empty args results in no output
        // This might be a bug that should be fixed in the implementation
        $result = $this->generator->dumpFunctionCall('time', []);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpFunctionCallSingleArg() : void
    {
        $result = $this->generator->dumpFunctionCall('count', ['$array']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            count($array)

            PHP;

        $this->assertGeneratedCode($expected, $result);
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
        $result = CodeGenerator::resolveIterable(function () {
            yield 'item1';
            yield 'item2';
        });

        self::assertSame(['item1', 'item2'], $result);
    }

    public function testCompleteCodeGeneration() : void
    {
        $this->generator = new CodeGenerator('App\\Services');

        $this->generator->import('App\\Models\\User');
        $this->generator->import('App\\Repositories\\UserRepository');

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Services;

            use App\Models\User;
            use App\Repositories\UserRepository;

            class UserService
            {
                public function __construct(
                    private UserRepository $repository,
                ) {}

                public function findUser(int $id): ?User
                {
                    return $this->repository->find($id);
                }
            }

            PHP;

        $this->assertGeneratedCode($expected, function () {
            yield 'class UserService';
            yield '{';
            yield Group::indent(function () {
                yield 'public function __construct(';
                yield Group::indent([
                    'private UserRepository $repository,',
                ]);
                yield ') {}';
                yield '';
                yield 'public function findUser(int $id): ?User';
                yield '{';
                yield Group::indent([
                    'return $this->repository->find($id);',
                ]);
                yield '}';
            });
            yield '}';
        });
    }

    public function testImportSorting() : void
    {
        $this->generator->import('Zebra\\Class');
        $this->generator->import('App\\Models\\User');
        $this->generator->import('function array_map');
        $this->generator->import('App\\Models\\Post');
        $this->generator->import('Beta\\Class');

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use App\Models\Post;
            use App\Models\User;
            use function array_map;
            use Beta\Class as Class2;
            use Zebra\Class;

            PHP;

        $this->assertGeneratedCode($expected, []);
    }

    public function testSuffixFirstWithGroupAsFirstElement() : void
    {
        $data = [
            Group::indent(['inner content']),
            'second line',
        ];

        $result = $this->generator->suffixFirst(',', $data);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

                inner content,
            second line

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testJoinFirstPairWithEmptySecondGroup() : void
    {
        $data = [
            'first',
            Group::indent([], 0),
            'third',
        ];

        $result = $this->generator->joinFirstPair($data);

        // Note: When the second element is an empty group, 'first' gets lost
        // This might be unexpected behavior
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            third

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpCallWithEmptyArgsOnIterable() : void
    {
        $object = ['$object'];
        $result = $this->generator->dumpCall($object, 'method', []);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            $object
                ->method()

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpCallWithMultipleArgsOnIterable() : void
    {
        $object = ['$object'];
        $args = ['"arg1"', '"arg2"', '"arg3"'];
        $result = $this->generator->dumpCall($object, 'method', $args, false, true);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            $object
                ->method(
                    "arg1",
                    "arg2",
                    "arg3",
                )

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDumpWithNoImportsHasNoExtraNewline() : void
    {
        $this->generator = new CodeGenerator('App\\Services');

        $result = $this->generator->dump(['class Test {}']);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Services;

            class Test {}

            PHP;

        self::assertSame($expected, $result);
    }

    public function testDumpWithImportsHasProperSpacing() : void
    {
        $this->generator = new CodeGenerator('App\\Services');
        $this->generator->import('App\\Models\\User');

        $result = $this->generator->dump(['class Test {}']);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Services;

            use App\Models\User;

            class Test {}

            PHP;

        self::assertSame($expected, $result);
    }

    public function testDumpWithoutNamespaceAndNoImports() : void
    {
        $result = $this->generator->dump(['class Test {}']);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            class Test {}

            PHP;

        self::assertSame($expected, $result);
    }

    public function testDumpWithoutNamespaceButWithImports() : void
    {
        $this->generator->import('App\\Models\\User');

        $result = $this->generator->dump(['class Test {}']);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use App\Models\User;

            class Test {}

            PHP;

        self::assertSame($expected, $result);
    }

    public function testDumpPreventsConsecutiveNewlines() : void
    {
        $this->generator = new CodeGenerator('App\\Services');

        $result = $this->generator->dump(function () {
            yield 'class UserService';
            yield '{';
            yield '';
            yield '';
            yield '';
            yield '    public function getUser(): User';
            yield '    {';
            yield '';
            yield '';
            yield '        return new User();';
            yield '    }';
            yield '';
            yield '';
            yield '';
            yield '}';
        });

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace App\Services;

            class UserService
            {

                public function getUser(): User
                {

                    return new User();
                }

            }

            PHP;

        self::assertSame($expected, $result);
    }

    public function testDumpPreventsConsecutiveNewlinesWithGroups() : void
    {
        $result = $this->generator->dump([
            'class Test {',
            '',
            '',
            Group::indent([
                '',
                '',
                'public function method()',
                '{',
                Group::indent([
                    '',
                    '',
                    'return true;',
                ]),
                '}',
            ]),
            '',
            '',
            '}',
        ]);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            class Test {

                public function method()
                {

                    return true;
                }

            }

            PHP;

        self::assertSame($expected, $result);
    }

    public function testTrim() : void
    {
        $result = $this->generator->trim([
            '',
            '',
            'first line',
            'middle line',
            'last line',
            '',
            '',
        ]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            first line
            middle line
            last line

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testTrimWithOnlyEmptyLines() : void
    {
        $result = $this->generator->trim(['', '', '']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testTrimWithNoEmptyLines() : void
    {
        $result = $this->generator->trim(['line1', 'line2', 'line3']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            line1
            line2
            line3

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testTrimWithEmptyLinesInMiddle() : void
    {
        $result = $this->generator->trim([
            '',
            'first',
            '',
            '',
            'middle',
            '',
            'last',
            '',
        ]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            first

            middle

            last

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testTrimWithGroups() : void
    {
        $result = $this->generator->trim([
            '',
            Group::indent(['content']),
            'after group',
            '',
        ]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

                content
            after group

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testTrimWithGenerator() : void
    {
        $data = function () {
            yield '';
            yield '';
            yield 'actual content';
            yield '';
        };

        $result = $this->generator->trim($data);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            actual content

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testTrimWithIndentedEmptyLines() : void
    {
        $result = $this->generator->trim([
            '    ',
            "\t",
            'content',
            '  ',
        ]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            content

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testIndentWithTrimEnabled() : void
    {
        $result = $this->generator->indent([
            '',
            '',
            'public function test()',
            '{',
            '    return true;',
            '}',
            '',
            '',
        ]);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            class Example
            {
                public function test()
                {
                    return true;
                }
            }

            PHP;

        $this->assertGeneratedCode($expected, [
            'class Example',
            '{',
            $result,
            '}',
        ]);
    }

    public function testIndentWithTrimDisabled() : void
    {
        $result = $this->generator->indent([
            '',
            'public function test()',
            '{',
            '    return true;',
            '}',
            '',
        ], false);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            class Example
            {

                public function test()
                {
                    return true;
                }

            }

            PHP;

        $this->assertGeneratedCode($expected, [
            'class Example',
            '{',
            $result,
            '}',
        ]);
    }

    public function testIndentWithCustomIndentionLevel() : void
    {
        $result = $this->generator->indent([
            'if (true) {',
            '    return "nested";',
            '}',
        ], true, 2);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            function test()
            {
                    if (true) {
                        return "nested";
                    }
            }

            PHP;

        $this->assertGeneratedCode($expected, [
            'function test()',
            '{',
            $result,
            '}',
        ]);
    }

    public function testIndentWithGenerator() : void
    {
        $data = function () {
            yield '';
            yield 'private string $name;';
            yield '';
            yield 'private int $age;';
            yield '';
        };

        $result = $this->generator->indent($data);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            class Person
            {
                private string $name;

                private int $age;
            }

            PHP;

        $this->assertGeneratedCode($expected, [
            'class Person',
            '{',
            $result,
            '}',
        ]);
    }

    public function testIndentPreservesNestedGroups() : void
    {
        $result = $this->generator->indent([
            '',
            'public function nested()',
            '{',
            Group::indent([
                'if ($condition) {',
                Group::indent(['return true;']),
                '}',
            ]),
            '}',
            '',
        ]);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            class Test
            {
                public function nested()
                {
                    if ($condition) {
                        return true;
                    }
                }
            }

            PHP;

        $this->assertGeneratedCode($expected, [
            'class Test',
            '{',
            $result,
            '}',
        ]);
    }

    public function testPrefixWithSimpleArray() : void
    {
        $result = $this->generator->prefix('// ', ['line1', 'line2', 'line3']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            // line1
            // line2
            // line3

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testPrefixWithMultilineString() : void
    {
        $result = $this->generator->prefix('> ', "first line\nsecond line\nthird line");
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            > first line
            > second line
            > third line

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testPrefixWithGroup() : void
    {
        $data = function () {
            yield 'class Example';
            yield '{';
            yield Group::indent([
                'public function test()',
                '{',
                Group::indent(['return true;']),
                '}',
            ]);
            yield '}';
        };

        $prefixed = $this->generator->prefix('// ', $data);

        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            // class Example
            // {
                // public function test()
                // {
                    // return true;
                // }
            // }

            PHP;

        $this->assertGeneratedCode($expected, $prefixed);
    }

    public function testCommentWithSimpleArray() : void
    {
        $result = $this->generator->comment(['line1', 'line2', 'line3']);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            // line1
            // line2
            // line3

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testCommentWithMultilineString() : void
    {
        $result = $this->generator->comment("first line\nsecond line");
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            // first line
            // second line

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testBlockComment() : void
    {
        $data = [
            'This is a block comment',
            'with multiple lines',
            'of text',
        ];

        $result = $this->generator->blockComment($data);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            /*
             * This is a block comment
             * with multiple lines
             * of text
             */

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testBlockCommentWithMultilineString() : void
    {
        $result = $this->generator->blockComment("Line 1\nLine 2\nLine 3");
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            /*
             * Line 1
             * Line 2
             * Line 3
             */

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDocComment() : void
    {
        $data = [
            'This is a PHPDoc comment',
            '@param string $name The name parameter',
            '@return void',
        ];

        $result = $this->generator->docComment($data);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            /**
             * This is a PHPDoc comment
             * @param string $name The name parameter
             * @return void
             */

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDocCommentWithFullDump() : void
    {
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            /**
             * Calculate the sum of two numbers
             * 
             * @param int $a First number
             * @param int $b Second number
             * @return int The sum
             */
            function add(int $a, int $b): int
            {
                return $a + $b;
            }

            PHP;

        $this->assertGeneratedCode($expected, [
            $this->generator->docComment([
                'Calculate the sum of two numbers',
                '',
                '@param int $a First number',
                '@param int $b Second number',
                '@return int The sum',
            ]),
            'function add(int $a, int $b): int',
            '{',
            Group::indent(['return $a + $b;']),
            '}',
        ]);
    }

    public function testCommentWithGroup() : void
    {
        $data = [
            'class Test',
            '{',
            Group::indent([
                'public function method()',
                '{',
                Group::indent(['return true;']),
                '}',
            ]),
            '}',
        ];

        $commented = $this->generator->comment($data);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            // class Test
            // {
                // public function method()
                // {
                    // return true;
                // }
            // }

            PHP;

        $this->assertGeneratedCode($expected, $commented);
    }

    public function testBlockCommentWithGroup() : void
    {
        $data = [
            'Example code:',
            Group::indent([
                'if ($condition) {',
                Group::indent(['return true;']),
                '}',
            ]),
        ];

        $result = $this->generator->blockComment($data);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            /*
             * Example code:
                 * if ($condition) {
                     * return true;
                 * }
             */

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testBlockCommentWithEmptyContent() : void
    {
        $result = $this->generator->blockComment([]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDocCommentWithEmptyContent() : void
    {
        $result = $this->generator->docComment([]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testCommentWithEmptyContent() : void
    {
        $result = $this->generator->comment([]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testBlockCommentWithEmptyString() : void
    {
        $result = $this->generator->blockComment('');
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            /*
             * 
             */

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }

    public function testDocCommentWithEmptyGenerator() : void
    {
        $result = $this->generator->docComment([]);
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            PHP;

        $this->assertGeneratedCode($expected, $result);
    }
}
