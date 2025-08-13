<?php

declare(strict_types=1);

use Ruudk\CodeGenerator\CodeGenerator;
use Ruudk\CodeGenerator\Group;

include '../vendor/autoload.php';

$generator = new CodeGenerator('Example\Demo');

echo $generator->dump([
    '// Auto-generated example file',
    '',

    $generator->dumpAttribute('Example\Attributes\Something'),
    sprintf(
        'final readonly class %s extends %s {',
        $generator->import('Example\Demo'),
        $generator->import('Example\Parent'),
    ),
    Group::indent(function () use ($generator) {
        yield 'public function __construct(';
        yield Group::indent(function () use ($generator) {
            yield sprintf('private %s $date,', $generator->import(DateTimeImmutable::class));
        });
        yield ') {';
        yield Group::indent(function () use ($generator) {
            yield $generator->dumpCall('parent', '__construct', [
                "'Hello, World!'",
                'true',
            ], true);
        });
        yield '}';
    }),
    '}',
]);
