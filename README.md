<p align="center">
    <a href="https://packagist.org/packages/ruudk/code-generator"><img src="https://poser.pugx.org/ruudk/code-generator/v?style=for-the-badge" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/ruudk/code-generator"><img src="https://poser.pugx.org/ruudk/code-generator/require/php?style=for-the-badge" alt="PHP Version Require"></a>
    <a href="https://packagist.org/packages/ruudk/code-generator"><img src="https://poser.pugx.org/ruudk/code-generator/downloads?style=for-the-badge" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/ruudk/code-generator"><img src="https://poser.pugx.org/ruudk/code-generator/license?style=for-the-badge" alt="License"></a>
</p>

------

# Code Generator

A library for generating beautiful PHP code with automatic namespace import management.

## Installation

Install the library via Composer:

```bash
composer require ruudk/code-generator
```

## Example

```php
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
    Group::indent(function() use ($generator) {
        yield 'public function __construct(';
        yield Group::indent(function () use ($generator) {
            yield sprintf('private %s $date,', $generator->import(DateTimeImmutable::class));
        });
        yield ') {';
        yield Group::indent(function () use ($generator) {
            yield $generator->dumpCall('parent', '__construct', [
                "'Hello, World!'",
                "true",
            ], true);
        });
        yield '}';
    }),
    '}',
]);
```

### Output

```php
<?php

declare(strict_types=1);

namespace Example\Demo;

use DateTimeImmutable;
use Example\Attributes\Something;
use Example\Demo;
use Example\Parent;

// Auto-generated example file

#[Something]
final readonly class Demo extends Parent {
    public function __construct(
        private DateTimeImmutable $date,
    ) {
        parent::__construct(
            'Hello, World!',
            true,
        )
    }
}

```

## Development

### Running Tests

```bash
vendor/bin/phpunit
```

### Static Analysis

```bash
vendor/bin/phpstan analyse
```

### Code Style

```bash
vendor/bin/php-cs-fixer fix
```

## License

This library is released under the MIT License. See the [LICENSE](LICENSE) file for details.
