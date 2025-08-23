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
    private function assertDump(string $expected, array | Closure | Generator | string $content) : void
    {
        self::assertSame($expected, $this->generator->dump($content));
    }

    /**
     * @param CodeLines $content
     */
    private function assertDumpFile(string $expected, array | Closure | Generator | string $content) : void
    {
        self::assertSame($expected, $this->generator->dumpFile($content));
    }

    public function testConstructorWithNamespace() : void
    {
        $this->generator = new CodeGenerator('App\\Models');

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace App\Models;

                PHP,
            [],
        );
    }

    public function testConstructorWithoutNamespace() : void
    {
        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                PHP,
            [],
        );
    }

    public function testDumpWithSimpleContent() : void
    {
        $this->assertDump(
            <<<'PHP'
                class Foo
                {
                }
                PHP,
            ['class Foo', '{', '}'],
        );
    }

    public function testDumpWithCallable() : void
    {
        $this->assertDump(
            <<<'PHP'
                class Bar
                {
                }
                PHP,
            function () {
                yield 'class Bar';
                yield '{';
                yield '}';
            },
        );
    }

    public function testDumpWithIndentation() : void
    {
        $this->assertDump(
            <<<'PHP'
                class Test {
                    public function method()
                    {
                        return true;
                    }
                }
                PHP,
            [
                'class Test {',
                Group::indent([
                    'public function method()',
                    '{',
                    Group::indent(['return true;']),
                    '}',
                ]),
                '}',
            ],
        );
    }

    public function testImportClass() : void
    {
        $this->generator = new CodeGenerator('App\\Services');

        $alias = $this->generator->import('App\\Models\\User');

        self::assertSame('User', $alias);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace App\Services;

                use App\Models\User;

                PHP,
            [],
        );
    }

    public function testImportClassWithConflict() : void
    {
        $alias1 = $this->generator->import('App\\Models\\User');
        $alias2 = $this->generator->import('App\\Entities\\User');

        self::assertSame('User', $alias1);
        self::assertSame('User2', $alias2);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use App\Entities\User as User2;
                use App\Models\User;

                PHP,
            [],
        );
    }

    public function testImportFunction() : void
    {
        $alias = $this->generator->import(new FunctionName('array_map'));

        self::assertSame('array_map', $alias);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use function array_map;

                PHP,
            [],
        );
    }

    public function testImportEnum() : void
    {
        $reference = $this->generator->importEnum(TestEnum::OPTION_ONE);

        self::assertSame('TestEnum::OPTION_ONE', $reference);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Ruudk\CodeGenerator\Fixtures\TestEnum;

                PHP,
            [],
        );
    }

    public function testImportEnumDirectly() : void
    {
        $reference = $this->generator->importEnum(TestEnum::OPTION_TWO);

        self::assertSame('TestEnum::OPTION_TWO', $reference);

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use Ruudk\CodeGenerator\Fixtures\TestEnum;

                PHP,
            [],
        );
    }

    public function testImportSameNamespace() : void
    {
        $this->generator = new CodeGenerator('App\\Models');

        $this->generator->import('App\\Models\\User');

        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace App\Models;

                PHP,
            [],
        );
    }

    public function testDumpAttribute() : void
    {
        self::assertSame(
            '#[Required]',
            $this->generator->dumpAttribute('App\\Attributes\\Required'),
        );
    }

    public function testDumpClassReference() : void
    {
        self::assertSame(
            'User::class',
            $this->generator->dumpClassReference('App\\Models\\User'),
        );
    }

    public function testDumpClassReferenceWithoutImport() : void
    {
        self::assertSame(
            '\\App\\Models\\User::class',
            $this->generator->dumpClassReference('App\\Models\\User', false),
        );
    }

    public function testMaybeNowDocWithSingleLine() : void
    {
        self::assertSame(
            "'single line'",
            $this->generator->maybeNowDoc('single line'),
        );
    }

    public function testMaybeNowDocWithMultipleLines() : void
    {
        self::assertSame(
            <<<'PHP'
                <<<'EOD'
                    line 1
                    line 2
                    line 3
                    EOD
                PHP,
            $this->generator->maybeNowDoc("line 1\nline 2\nline 3"),
        );
    }

    public function testMaybeNowDocWithCustomTag() : void
    {
        self::assertSame(
            <<<'PHP'
                <<<'SQL'
                    multi
                    line
                    SQL
                PHP,
            $this->generator->maybeNowDoc("multi\nline", 'SQL'),
        );
    }

    public function testStatement() : void
    {
        $this->assertDump(
            '$x = 5;',
            $this->generator->statement(['$x = 5']),
        );
    }

    public function testStatementWithGroup() : void
    {
        $this->assertDump(
            <<<'PHP'
                if ($condition)
                    return true;
                PHP,
            $this->generator->statement([
                'if ($condition)',
                Group::indent(['return true']),
            ]),
        );
    }

    public function testSuffixLast() : void
    {
        $this->assertDump(
            <<<'PHP'
                item1
                item2
                item3,
                PHP,
            $this->generator->suffixLast(',', ['item1', 'item2', 'item3']),
        );
    }

    public function testSuffixLastWithEmptyIterable() : void
    {
        self::assertSame(
            '',
            $this->generator->dump($this->generator->suffixLast(',', [])),
        );
    }

    public function testWrap() : void
    {
        $this->assertDump(
            '[content]',
            $this->generator->wrap('[', ['content'], ']'),
        );
    }

    public function testWrapWithMultipleLines() : void
    {
        $this->assertDump(
            <<<'PHP'
                (line1
                line2)
                PHP,
            $this->generator->wrap('(', ['line1', 'line2'], ')'),
        );
    }

    public function testMaybeWrapTrue() : void
    {
        $this->assertDump(
            <<<'PHP'
                (content)
                PHP,
            $this->generator->maybeWrap(true, '(', ['content'], ')'),
        );
    }

    public function testMaybeWrapFalse() : void
    {
        $this->assertDump(
            <<<'PHP'
                content
                PHP,
            $this->generator->maybeWrap(false, '(', ['content'], ')'),
        );
    }

    public function testPrefixFirst() : void
    {
        $this->assertDump(
            <<<'PHP'
                > line1
                line2
                line3
                PHP,
            $this->generator->prefixFirst('> ', ['line1', 'line2', 'line3']),
        );
    }

    public function testPrefixFirstWithGroup() : void
    {
        $this->assertDump(
            <<<'PHP'
                    prefix: content
                PHP,
            $this->generator->prefixFirst('prefix: ', [Group::indent(['content'])]),
        );
    }

    public function testSuffixFirst() : void
    {
        $this->assertDump(
            <<<'PHP'
                key:
                value1
                value2
                PHP,
            $this->generator->suffixFirst(':', ['key', 'value1', 'value2']),
        );
    }

    public function testJoin() : void
    {
        self::assertSame(
            'item1, item2, item3',
            $this->generator->join(', ', ['item1', 'item2', 'item3']),
        );
    }

    public function testJoinWithGroups() : void
    {
        self::assertSame(
            'item1, , item3',
            $this->generator->join(', ', ['item1', new Group('nested'), 'item3']),
        );
    }

    public function testJoinFirstPairSimple() : void
    {
        $this->assertDump(
            <<<'PHP'
                firstsecond
                third
                PHP,
            $this->generator->joinFirstPair(['first', 'second', 'third']),
        );
    }

    public function testJoinFirstPairSingleElement() : void
    {
        $this->assertDump(
            <<<'PHP'
                only
                PHP,
            $this->generator->joinFirstPair(['only']),
        );
    }

    public function testJoinFirstPairWithGroup() : void
    {
        $this->assertDump(
            <<<'PHP'
                    prefixcontent
                third
                PHP,
            $this->generator->joinFirstPair(['prefix', Group::indent(['content']), 'third']),
        );
    }

    public function testAllSuffix() : void
    {
        $this->assertDump(
            <<<'PHP'
                item1,
                item2,
                item3,
                PHP,
            $this->generator->allSuffix(',', ['item1', 'item2', 'item3']),
        );
    }

    public function testAllSuffixSkipsComments() : void
    {
        $this->assertDump(
            <<<'PHP'
                item1,
                // comment
                item2,
                PHP,
            $this->generator->allSuffix(',', ['item1', '// comment', 'item2']),
        );
    }

    public function testAllSuffixWithGroup() : void
    {
        $this->assertDump(
            'item,',
            $this->generator->allSuffix(',', [new Group(['item'])]),
        );
    }

    public function testDumpCallStaticMethod() : void
    {
        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use App\Utils\Helper;

                Helper::process($data)

                PHP,
            $this->generator->dumpCall('App\\Utils\\Helper', 'process', ['$data'], true),
        );
    }

    public function testDumpCallConstructor() : void
    {
        $this->assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use App\Models\User;

                new User()

                PHP,
            $this->generator->dumpCall('App\\Models\\User', '__construct', []),
        );
    }

    public function testDumpCallInstanceMethod() : void
    {
        $this->assertDump(
            <<<'PHP'
                $user->getName()
                PHP,
            $this->generator->dumpCall('$user', 'getName'),
        );
    }

    public function testDumpCallWithArrayOfGenerators() : void
    {
        $true = function () : Generator {
            yield 'true';
        };

        $false = function () : Generator {
            yield 'false';
        };

        $this->assertDump(
            <<<'PHP'
                $var->method(
                    true,
                    false,
                )
                PHP,
            $this->generator->dumpCall('$var', 'method', [
                $true(),
                $false(),
            ]),
        );
    }

    public function testDumpCallWithMultipleArguments() : void
    {
        $this->assertDump(
            <<<'PHP'
                $object->method(
                    $arg1,
                    $arg2,
                    $arg3,
                )
                PHP,
            $this->generator->dumpCall('$object', 'method', ['$arg1', '$arg2', '$arg3']),
        );
    }

    public function testDumpCallWithIterableObject() : void
    {
        $this->assertDump(
            <<<'PHP'
                $object
                    ->method($arg)
                PHP,
            $this->generator->dumpCall(['$object'], 'method', ['$arg']),
        );
    }

    public function testDumpFunctionCall() : void
    {
        $this->assertDump(
            <<<'PHP'
                array_map(
                    $callback,
                    $array,
                )
                PHP,
            $this->generator->dumpFunctionCall('array_map', ['$callback', '$array']),
        );
    }

    public function testDumpFunctionCallNoArgs() : void
    {
        $result = $this->generator->dumpFunctionCall('time', []);
        $expected = 'time()';

        self::assertSame($expected, $this->generator->dump($result));
    }

    public function testDumpFunctionCallSingleArg() : void
    {
        $this->assertDump(
            'count($array)',
            $this->generator->dumpFunctionCall('count', ['$array']),
        );
    }

    public function testResolveIterableWithString() : void
    {
        self::assertSame(
            ['test string'],
            CodeGenerator::resolveIterable('test string'),
        );
    }

    public function testResolveIterableWithArray() : void
    {
        self::assertSame(
            ['line1', 'line2', 'line3'],
            CodeGenerator::resolveIterable(['line1', 'line2', 'line3']),
        );
    }

    public function testResolveIterableWithCallable() : void
    {
        $callable = function () {
            yield 'generated1';
            yield 'generated2';
        };

        self::assertSame(
            ['generated1', 'generated2'],
            CodeGenerator::resolveIterable($callable),
        );
    }

    public function testResolveIterableWithGenerator() : void
    {
        self::assertSame(
            ['item1', 'item2'],
            CodeGenerator::resolveIterable(function () {
                yield 'item1';
                yield 'item2';
            }),
        );
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

        $this->assertDumpFile($expected, function () {
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
        $this->generator->import(new FunctionName('array_map'));
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

        $this->assertDumpFile($expected, []);
    }

    public function testImportSortingWithSymfonyFunctions() : void
    {
        // Add imports in random order to test sorting
        $this->generator->import(new FunctionName('Symfony\\Component\\DependencyInjection\\Loader\\Configurator\\service'));
        $this->generator->import('Symfony\\Component\\Serializer\\Mapping\\Loader\\LoaderInterface');
        $this->generator->import('Symfony\\Component\\DependencyInjection\\ContainerBuilder');
        $this->generator->import(new FunctionName('Symfony\\Component\\DependencyInjection\\Loader\\Configurator\\param'));
        $this->generator->import('Symfony\\Component\\DependencyInjection\\Loader\\Configurator\\ContainerConfigurator');
        $this->generator->import(new FunctionName('Symfony\\Component\\DependencyInjection\\Loader\\Configurator\\inline_service'));

        // This should match your exact expected output
        $expected = <<<'PHP'
            <?php

            declare(strict_types=1);

            use Symfony\Component\DependencyInjection\ContainerBuilder;
            use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
            use function Symfony\Component\DependencyInjection\Loader\Configurator\inline_service;
            use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
            use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
            use Symfony\Component\Serializer\Mapping\Loader\LoaderInterface;

            PHP;

        $this->assertDumpFile($expected, []);
    }

    public function testSuffixFirstWithGroupAsFirstElement() : void
    {
        $this->assertDump(
            <<<'PHP'
                    inner content,
                second line
                PHP,
            $this->generator->suffixFirst(',', [
                Group::indent(['inner content']),
                'second line',
            ]),
        );
    }

    public function testJoinFirstPairWithEmptySecondGroup() : void
    {
        $data = [
            'first',
            Group::indent([], 0),
            'third',
        ];

        $result = $this->generator->joinFirstPair($data);

        $expected = <<<'PHP'
            first
            third
            PHP;

        $this->assertDump($expected, $result);
    }

    public function testDumpCallWithEmptyArgsOnIterable() : void
    {
        $this->assertDump(
            <<<'PHP'
                $object
                    ->method()
                PHP,
            $this->generator->dumpCall(['$object'], 'method', []),
        );
    }

    public function testDumpCallWithMultipleArgsOnIterable() : void
    {
        $this->assertDump(
            <<<'PHP'
                $object
                    ->method(
                        "arg1",
                        "arg2",
                        "arg3",
                    )
                PHP,
            $this->generator->dumpCall(['$object'], 'method', ['"arg1"', '"arg2"', '"arg3"'], false, true),
        );
    }

    public function testDumpFileWithNoImportsHasNoExtraNewline() : void
    {
        $this->generator = new CodeGenerator('App\\Services');

        self::assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace App\Services;

                class Test {}

                PHP,
            ['class Test {}'],
        );
    }

    public function testDumpFileWithImportsHasProperSpacing() : void
    {
        $this->generator = new CodeGenerator('App\\Services');
        $this->generator->import('App\\Models\\User');

        self::assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                namespace App\Services;

                use App\Models\User;

                class Test {}

                PHP,
            ['class Test {}'],
        );
    }

    public function testDumpFileWithoutNamespaceAndNoImports() : void
    {
        self::assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                class Test {}

                PHP,
            ['class Test {}'],
        );
    }

    public function testDumpFileWithoutNamespaceButWithImports() : void
    {
        $this->generator->import('App\\Models\\User');

        self::assertDumpFile(
            <<<'PHP'
                <?php

                declare(strict_types=1);

                use App\Models\User;

                class Test {}

                PHP,
            ['class Test {}'],
        );
    }

    public function testDumpPreventsConsecutiveNewlines() : void
    {
        self::assertDump(
            <<<'PHP'
                class UserService
                {

                    public function getUser(): User
                    {

                        return new User();
                    }

                }
                PHP,
            function () {
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
            },
        );
    }

    public function testDumpPreventsConsecutiveNewlinesWithGroups() : void
    {
        self::assertDump(
            <<<'PHP'
                class Test {

                    public function method()
                    {

                        return true;
                    }

                }
                PHP,
            [
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
            ],
        );
    }

    public function testTrim() : void
    {
        $this->assertDump(
            <<<'PHP'
                first line
                middle line
                last line
                PHP,
            $this->generator->trim([
                '',
                '',
                'first line',
                'middle line',
                'last line',
                '',
                '',
            ]),
        );
    }

    public function testTrimWithOnlyEmptyLines() : void
    {
        self::assertSame('', $this->generator->dump($this->generator->trim(['', '', ''])));
    }

    public function testTrimWithNoEmptyLines() : void
    {
        $this->assertDump(
            <<<'PHP'
                line1
                line2
                line3
                PHP,
            $this->generator->trim(['line1', 'line2', 'line3']),
        );
    }

    public function testTrimWithEmptyLinesInMiddle() : void
    {
        $this->assertDump(
            <<<'PHP'
                first

                middle

                last
                PHP,
            $this->generator->trim([
                '',
                'first',
                '',
                '',
                'middle',
                '',
                'last',
                '',
            ]),
        );
    }

    public function testTrimWithGroups() : void
    {
        $this->assertDump(
            <<<'PHP'
                    content
                after group
                PHP,
            $this->generator->trim([
                '',
                Group::indent(['content']),
                'after group',
                '',
            ]),
        );
    }

    public function testTrimWithGenerator() : void
    {
        $this->assertDump(
            <<<'PHP'
                actual content
                PHP,
            $this->generator->trim(function () {
                yield '';
                yield '';
                yield 'actual content';
                yield '';
            }),
        );
    }

    public function testTrimWithIndentedEmptyLines() : void
    {
        $this->assertDump(
            <<<'PHP'
                content
                PHP,
            $this->generator->trim([
                '    ',
                "\t",
                'content',
                '  ',
            ]),
        );
    }

    public function testIndentWithTrimEnabled() : void
    {
        $this->assertDump(
            <<<'PHP'
                class Example
                {
                    public function test()
                    {
                        return true;
                    }
                }
                PHP,
            [
                'class Example',
                '{',
                $this->generator->indent([
                    '',
                    '',
                    'public function test()',
                    '{',
                    '    return true;',
                    '}',
                    '',
                    '',
                ]),
                '}',
            ],
        );
    }

    public function testIndentWithTrimDisabled() : void
    {
        $this->assertDump(
            <<<'PHP'
                class Example
                {

                    public function test()
                    {
                        return true;
                    }

                }
                PHP,
            [
                'class Example',
                '{',
                $this->generator->indent([
                    '',
                    'public function test()',
                    '{',
                    '    return true;',
                    '}',
                    '',
                ], false),
                '}',
            ],
        );
    }

    public function testIndentWithCustomIndentionLevel() : void
    {
        $this->assertDump(
            <<<'PHP'
                function test()
                {
                        if (true) {
                            return "nested";
                        }
                }
                PHP,
            [
                'function test()',
                '{',
                $this->generator->indent([
                    'if (true) {',
                    '    return "nested";',
                    '}',
                ], true, 2),
                '}',
            ],
        );
    }

    public function testIndentWithGenerator() : void
    {
        $this->assertDump(
            <<<'PHP'
                class Person
                {
                    private string $name;

                    private int $age;
                }
                PHP,
            [
                'class Person',
                '{',
                $this->generator->indent(function () {
                    yield '';
                    yield 'private string $name;';
                    yield '';
                    yield 'private int $age;';
                    yield '';
                }),
                '}',
            ],
        );
    }

    public function testIndentPreservesNestedGroups() : void
    {
        $this->assertDump(
            <<<'PHP'
                class Test
                {
                    public function nested()
                    {
                        if ($condition) {
                            return true;
                        }
                    }
                }
                PHP,
            [
                'class Test',
                '{',
                $this->generator->indent([
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
                ]),
                '}',
            ],
        );
    }

    public function testPrefixWithSimpleArray() : void
    {
        $this->assertDump(
            <<<'PHP'
                // line1
                // line2
                // line3
                PHP,
            $this->generator->prefix('// ', ['line1', 'line2', 'line3']),
        );
    }

    public function testPrefixWithMultilineString() : void
    {
        $this->assertDump(
            <<<'PHP'
                > first line
                > second line
                > third line
                PHP,
            $this->generator->prefix('> ', "first line\nsecond line\nthird line"),
        );
    }

    public function testPrefixWithGroup() : void
    {
        $this->assertDump(
            <<<'PHP'
                // class Example
                // {
                    // public function test()
                    // {
                        // return true;
                    // }
                // }
                PHP,
            $this->generator->prefix('// ', function () {
                yield 'class Example';
                yield '{';
                yield Group::indent([
                    'public function test()',
                    '{',
                    Group::indent(['return true;']),
                    '}',
                ]);
                yield '}';
            }),
        );
    }

    public function testCommentWithSimpleArray() : void
    {
        $this->assertDump(
            <<<'PHP'
                // line1
                // line2
                // line3
                PHP,
            $this->generator->comment(['line1', 'line2', 'line3']),
        );
    }

    public function testCommentWithMultilineString() : void
    {
        $this->assertDump(
            <<<'PHP'
                // first line
                // second line
                PHP,
            $this->generator->comment("first line\nsecond line"),
        );
    }

    public function testBlockComment() : void
    {
        $this->assertDump(
            <<<'PHP'
                /*
                 * This is a block comment
                 * with multiple lines
                 * of text
                 */
                PHP,
            $this->generator->blockComment([
                'This is a block comment',
                'with multiple lines',
                'of text',
            ]),
        );
    }

    public function testBlockCommentWithMultilineString() : void
    {
        $this->assertDump(
            <<<'PHP'
                /*
                 * Line 1
                 * Line 2
                 * Line 3
                 */
                PHP,
            $this->generator->blockComment("Line 1\nLine 2\nLine 3"),
        );
    }

    public function testDocComment() : void
    {
        $this->assertDump(
            <<<'PHP'
                /**
                 * This is a PHPDoc comment
                 * @param string $name The name parameter
                 * @return void
                 */
                PHP,
            $this->generator->docComment([
                'This is a PHPDoc comment',
                '@param string $name The name parameter',
                '@return void',
            ]),
        );
    }

    public function testDocCommentWithFullDump() : void
    {
        $this->assertDump(
            <<<'PHP'
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
                PHP,
            [
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
            ],
        );
    }

    public function testCommentWithGroup() : void
    {
        $this->assertDump(
            <<<'PHP'
                // class Test
                // {
                    // public function method()
                    // {
                        // return true;
                    // }
                // }
                PHP,
            $this->generator->comment([
                'class Test',
                '{',
                Group::indent([
                    'public function method()',
                    '{',
                    Group::indent(['return true;']),
                    '}',
                ]),
                '}',
            ]),
        );
    }

    public function testBlockCommentWithGroup() : void
    {
        $this->assertDump(
            <<<'PHP'
                /*
                 * Example code:
                     * if ($condition) {
                         * return true;
                     * }
                 */
                PHP,
            $this->generator->blockComment([
                'Example code:',
                Group::indent([
                    'if ($condition) {',
                    Group::indent(['return true;']),
                    '}',
                ]),
            ]),
        );
    }

    public function testBlockCommentWithEmptyContent() : void
    {
        $this->assertDump('', $this->generator->blockComment([]));
    }

    public function testDocCommentWithEmptyContent() : void
    {
        $this->assertDump('', $this->generator->docComment([]));
    }

    public function testCommentWithEmptyContent() : void
    {
        $this->assertDump('', $this->generator->comment([]));
    }

    public function testBlockCommentWithEmptyString() : void
    {
        $this->assertDump(
            <<<'PHP'
                /*
                 * 
                 */
                PHP,
            $this->generator->blockComment(''),
        );
    }

    public function testDocCommentWithEmptyGenerator() : void
    {
        $this->assertDump('', $this->generator->docComment([]));
    }
}
