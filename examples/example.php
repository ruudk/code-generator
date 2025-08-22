<?php

declare(strict_types=1);

use Ruudk\CodeGenerator\CodeGenerator;
use Ruudk\CodeGenerator\Group;

include '../vendor/autoload.php';

$generator = new CodeGenerator('Example\Demo');

// First, let's see what happens step by step
echo $generator->dumpFile(function () use ($generator) {
    // Comments
    yield '// This file demonstrates ALL CodeGenerator features';
    yield '// Auto-generated example file';
    yield '';

    // Class with attributes
    yield $generator->dumpAttribute('Example\Attributes\Entity');
    yield $generator->dumpAttribute('Example\Attributes\Table');
    yield sprintf(
        'final class DemoClass extends %s implements %s, %s',
        $generator->import('Example\BaseClass'),
        $generator->import('Example\Interfaces\FirstInterface'),
        $generator->import('Example\Interfaces\SecondInterface'),
    );
    yield '{';
    yield $generator->indent(function () use ($generator) {
        // Properties with indentation
        yield '// Class properties';
        yield sprintf('private %s $date;', $generator->import(DateTimeImmutable::class));
        yield '';
        yield ''; // multiple newlines are ignored
        yield sprintf('private %s $collection;', $generator->import('Doctrine\Common\Collections\Collection'));
        yield sprintf('private ?%s $optional = null;', $generator->import(stdClass::class));
        yield '';

        // Constructor using dumpCall

        yield 'public function __construct(';
        yield $generator->indent([
            sprintf('private readonly %s $service,', $generator->import('App\Services\MainService')),
            sprintf(
                'private %s $enum = %s,',
                $generator->import('App\Enums\Status'),
                $generator->import('App\Enums\Status') . '::Active',
            ),
            'string $name = \'default\',',
        ]);
        yield ') {';
        $generator->indent(function () use ($generator) {
            // Parent constructor call
            yield from $generator->dumpCall('parent', '__construct', [
                'enabled: true',
                'timeout: 30',
            ], static: true);

            yield '';

            // Method chaining with dumpCall
            yield from $generator->statement(
                $generator->dumpCall(
                    $generator->dumpCall(
                        $generator->dumpCall('$this', 'getBuilder'),
                        'setName',
                        ['$name'],
                    ),
                    'setDebug',
                    ['true'],
                ),
            );

            yield '';

            // Static method call
            yield from $generator->statement(
                $generator->dumpCall('Logger', 'info', [
                    '\'Constructor initialized\'',
                    '[\'class\' => __CLASS__]',
                ], static: true),
            );
        });
        yield '}';
        yield '';

        // Method with various features
        yield '/**';
        yield ' * Demonstrates various generator features';
        yield ' */';
        yield 'public function processData(array $data): void';
        yield '{';
        yield $generator->indent(function () use ($generator) {
            // Using prefixFirst and suffixLast
            yield from $generator->prefixFirst(
                '$result = ',
                $generator->suffixLast(';', ['$this->calculate($data)']),
            );
            yield '';

            // Using wrap
            yield from $generator->statement(
                $generator->wrap('$wrapped = [', ['$result'], ']'),
            );
            yield '';

            // Using maybeWrap with condition
            yield from $generator->maybeWrap(true, 'if ($result) { ', [
                '$this->save($result);',
            ], ' }');
            yield '';

            // Using allSuffix for array elements
            yield '$items = [';
            yield $generator->indent(
                $generator->allSuffix(',', [
                    '\'first\'',
                    '\'second\'',
                    '\'third\'',
                    '// Comment not suffixed',
                ]),
            );
            yield '];';
            yield '';

            // Using dumpFunctionCall
            yield from $generator->statement(
                $generator->dumpFunctionCall('array_map', [
                    'fn($x) => $x * 2',
                    '$items',
                ]),
            );
            yield '';

            // Using join
            $joined = $generator->join(', ', ['$a', '$b', '$c']);
            yield sprintf('$concatenated = sprintf(\'%%s\', %s);', $joined);
            yield '';

            // Using joinFirstPair
            yield from $generator->statement(
                $generator->joinFirstPair([
                    '$prefix',
                    ' = \'value\'',
                    ' . \'suffix\'',
                ]),
            );
            yield '';

            // Using suffixFirst
            yield 'foreach ($items as $item) {';
            yield $generator->indent(
                $generator->suffixFirst(':', [
                    'echo $item',
                    'echo PHP_EOL',
                ]),
            );
            yield '}';
            yield '';

            // Using new instance creation
            yield from $generator->statement(
                $generator->dumpCall(DateTime::class, '__construct', ['\'now\'']),
            );
            yield '';

            // Multi-line string with maybeNowDoc
            $multilineText = "This is a\nmulti-line\nstring example";
            yield sprintf('$text = %s;', $generator->maybeNowDoc($multilineText));
            yield '';

            // Class reference
            yield sprintf(
                '$className = %s;',
                $generator->dumpClassReference('Example\SomeClass'),
            );
            yield '';
        });
        yield '}';
        yield '';

        // Private method with Group nesting
        yield 'private function calculate(array $data): int';
        yield '{';
        yield $generator->indent(function () use ($generator) {
            yield 'return match($data[\'type\'] ?? null) {';
            yield $generator->indent([
                '\'sum\' => array_sum($data[\'values\']),',
                '\'count\' => count($data[\'values\']),',
                '\'max\' => max($data[\'values\']),',
                'default => 0,',
            ]);
            yield '};';
        });
        yield '}';
        yield '';

        // Static factory method
        yield sprintf('public static function create(): %s', $generator->import('self'));
        yield '{';
        yield $generator->indent([
            sprintf('return new %s(', $generator->import('self')),
            $generator->indent([
                sprintf('new %s(),', $generator->import('App\Services\MainService')),
            ]),
            ');',
        ]);
        yield '}';
    });
    yield '}';
    yield '';

    // Interface
    yield sprintf(
        'interface CustomInterface extends %s',
        $generator->import('Example\Interfaces\BaseInterface'),
    );
    yield '{';
    yield $generator->indent([
        'public function process(mixed $data): void;',
        '',
        'public function validate(array $rules): bool;',
    ]);
    yield '}';
    yield '';

    // Enum
    yield 'enum Color: string';
    yield '{';
    yield $generator->indent([
        'case RED = \'#FF0000\';',
        'case GREEN = \'#00FF00\';',
        'case BLUE = \'#0000FF\';',
        '',
        'public function toRgb(): array',
        '{',
        $generator->indent([
            'return match($this) {',
            $generator->indent([
                'self::RED => [255, 0, 0],',
                'self::GREEN => [0, 255, 0],',
                'self::BLUE => [0, 0, 255],',
            ]),
            '};',
        ]),
        '}',
    ]);
    yield '}';
    yield '';

    // Trait
    yield 'trait TimestampableTrait';
    yield '{';
    yield $generator->indent(function () use ($generator) {
        yield sprintf('private %s $createdAt;', $generator->import(DateTimeInterface::class));
        yield sprintf('private ?%s $updatedAt = null;', $generator->import(DateTimeInterface::class));
        yield '';
        yield 'public function updateTimestamps(): void';
        yield '{';
        yield $generator->indent([
            'if ($this->createdAt === null) {',
            $generator->indent(
                $generator->statement(['$this->createdAt = new \\DateTimeImmutable()']),
            ),
            '}',
            '$this->updatedAt = new \\DateTimeImmutable();',
        ]);
        yield '}';
    });
    yield '}';
    yield '';

    // Anonymous class
    yield from $generator->statement([
        '$anonymous = new class(',
        $generator->indent(['$dependency']),
        ') {',
        $generator->indent([
            'public function __construct(private mixed $dep) {}',
            '',
            'public function execute(): void',
            '{',
            $generator->indent(['echo \'Anonymous class method\';']),
            '}',
        ]),
        '}',
    ]);
    yield '';

    // Global function
    yield 'function helperFunction(string $input): string';
    yield '{';
    yield $generator->indent(
        $generator->statement([
            'return strtoupper($input)',
        ]),
    );
    yield '}';
});
