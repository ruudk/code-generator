<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Group::class)]
final class GroupTest extends TestCase
{
    public function testConstructorWithString() : void
    {
        $group = new Group('test line');

        self::assertSame(['test line'], $group->lines);
        self::assertSame(0, $group->indention);
    }

    public function testConstructorWithArray() : void
    {
        $lines = ['line 1', 'line 2', 'line 3'];
        $group = new Group($lines);

        self::assertSame($lines, $group->lines);
        self::assertSame(0, $group->indention);
    }

    public function testConstructorWithCallable() : void
    {
        $callable = function () {
            yield 'line 1';
            yield 'line 2';
        };

        $group = new Group($callable);

        self::assertSame(['line 1', 'line 2'], $group->lines);
        self::assertSame(0, $group->indention);
    }

    public function testConstructorWithNestedGroups() : void
    {
        $nestedGroup = new Group('nested line');
        $lines = ['line 1', $nestedGroup, 'line 2'];
        $group = new Group($lines);

        self::assertSame($lines, $group->lines);
        self::assertInstanceOf(Group::class, $group->lines[1]);
    }

    public function testConstructorWithGenerator() : void
    {
        $generator = function () {
            yield 'line 1';
            yield 'line 2';
            yield 'line 3';
        };

        $group = new Group($generator());

        self::assertSame(['line 1', 'line 2', 'line 3'], $group->lines);
    }

    public function testIndentStaticMethodWithString() : void
    {
        $group = Group::indent('indented line', 2);

        self::assertSame(['indented line'], $group->lines);
        self::assertSame(2, $group->indention);
    }

    public function testIndentStaticMethodWithArray() : void
    {
        $lines = ['line 1', 'line 2'];
        $group = Group::indent($lines, 3);

        self::assertSame($lines, $group->lines);
        self::assertSame(3, $group->indention);
    }

    public function testIndentStaticMethodWithCallable() : void
    {
        $callable = function () {
            yield 'indented 1';
            yield 'indented 2';
        };

        $group = Group::indent($callable, 4);

        self::assertSame(['indented 1', 'indented 2'], $group->lines);
        self::assertSame(4, $group->indention);
    }

    public function testIndentStaticMethodWithZeroIndentation() : void
    {
        $group = Group::indent('no indent', 0);

        self::assertSame(['no indent'], $group->lines);
        self::assertSame(0, $group->indention);
    }

    public function testIndentStaticMethodWithNegativeIndentation() : void
    {
        $group = Group::indent('negative indent', -1);

        self::assertSame(['negative indent'], $group->lines);
        self::assertSame(-1, $group->indention);
    }

    public function testIsEmptyWithEmptyArray() : void
    {
        $group = new Group([]);

        self::assertTrue($group->isEmpty());
    }

    public function testIsEmptyWithEmptyCallable() : void
    {
        $callable = function () {
            yield from [];
        };

        $group = new Group($callable);

        self::assertTrue($group->isEmpty());
    }

    public function testIsEmptyWithNonEmptyString() : void
    {
        $group = new Group('not empty');

        self::assertFalse($group->isEmpty());
    }

    public function testIsEmptyWithNonEmptyArray() : void
    {
        $group = new Group(['line']);

        self::assertFalse($group->isEmpty());
    }

    public function testIsEmptyWithEmptyStringInArray() : void
    {
        $group = new Group(['']);

        self::assertFalse($group->isEmpty());
    }

    public function testComplexNestedStructure() : void
    {
        $innerGroup = Group::indent(['inner line 1', 'inner line 2'], 2);
        $middleGroup = new Group(['middle line', $innerGroup]);
        $outerGroup = Group::indent(['outer line', $middleGroup, 'final line']);

        self::assertSame(1, $outerGroup->indention);
        self::assertCount(3, $outerGroup->lines);
        self::assertInstanceOf(Group::class, $outerGroup->lines[1]);
        self::assertFalse($outerGroup->isEmpty());

        $middleGroupFromOuter = $outerGroup->lines[1];
        self::assertInstanceOf(Group::class, $middleGroupFromOuter);
        self::assertSame(0, $middleGroupFromOuter->indention);
        self::assertCount(2, $middleGroupFromOuter->lines);

        $innerGroupFromMiddle = $middleGroupFromOuter->lines[1];
        self::assertInstanceOf(Group::class, $innerGroupFromMiddle);
        self::assertSame(2, $innerGroupFromMiddle->indention);
        self::assertSame(['inner line 1', 'inner line 2'], $innerGroupFromMiddle->lines);
    }

    public function testMixedContentTypes() : void
    {
        $nestedGroup = new Group('nested');
        $callable = function () use ($nestedGroup) {
            yield 'from callable 1';
            yield $nestedGroup;
            yield 'from callable 2';
        };

        $group = new Group($callable);

        self::assertCount(3, $group->lines);
        self::assertSame('from callable 1', $group->lines[0]);
        self::assertInstanceOf(Group::class, $group->lines[1]);
        self::assertSame('from callable 2', $group->lines[2]);
    }
}
