<?php

declare(strict_types=1);

use Ruudk\CodeGenerator\CodeGenerator;

include '../vendor/autoload.php';

$generator = new CodeGenerator('Example\Demo');

echo $generator->dumpFile([
    '// Auto-generated example file',
    '',

    $generator->dumpAttribute('Example\Attributes\Something'),
    $generator->dumpAttribute('Example\Attributes\Single', ['value: "Hello, World!"']),
    $generator->dumpAttribute('Example\Attributes\Multiple', ['value: "Hello, World!"', 'other: "Other value"']),
    sprintf(
        'final readonly class %s extends %s',
        $generator->import('Example\Demo'),
        $generator->import('Example\Parent'),
    ),
    '{',
    $generator->indent(function () use ($generator) {
        yield 'public function __construct(';
        yield $generator->indent(function () use ($generator) {
            yield sprintf('private %s $date,', $generator->import(DateTimeImmutable::class));
        });
        yield ') {';
        yield $generator->indent(function () use ($generator) {
            yield from $generator->statement($generator->dumpCall('parent', '__construct', [
                "'Hello, World!'",
                'true',
            ], true));
        });
        yield '}';
    }),
    '}',
]);
