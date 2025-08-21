<p align="center">
    <strong>Code Generator for PHP</strong><br>
    <em>Yield your code line by line: let generators handle indentation, formatting, and structure for you</em>
</p>
<p align="center">
    <a href="https://packagist.org/packages/ruudk/code-generator"><img src="https://poser.pugx.org/ruudk/code-generator/v?style=for-the-badge" alt="Latest Stable Version"></a>
    <a href="https://packagist.org/packages/ruudk/code-generator"><img src="https://poser.pugx.org/ruudk/code-generator/require/php?style=for-the-badge" alt="PHP Version Require"></a>
    <a href="https://packagist.org/packages/ruudk/code-generator"><img src="https://poser.pugx.org/ruudk/code-generator/downloads?style=for-the-badge" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/ruudk/code-generator"><img src="https://poser.pugx.org/ruudk/code-generator/license?style=for-the-badge" alt="License"></a>
</p>

------

# Code Generator

Transform the way you generate PHP code! This library makes code generation feel natural and elegant - just yield your lines and watch as perfectly formatted, beautifully structured PHP emerges with automatic namespace imports, proper indentation, and clean syntax.

## ✨ Why This Library?

Ever struggled with generating PHP code? String concatenation getting messy? Indentation driving you crazy? **This is your solution!**

🎯 **Write code that writes code** - but make it beautiful  
🎯 **Yield line by line** - no string concatenation nightmares  
🎯 **Automatic everything** - imports, indentation, formatting handled for you  
🎯 **Composable generators** - chain, wrap, and transform with ease  
🎯 **Zero boilerplate** - focus on what to generate, not how  

## 🚀 Key Features

### 🎨 Beautiful Output, Every Time
- **Smart namespace management** - imports are automatically collected and organized
- **Perfect indentation** - just yield your lines, nesting is handled automatically  
- **Clean formatting** - proper spacing, consistent style, readable code

### 🔧 Powerful Generator Toolkit
- **`indent()`** - Auto-indent nested code blocks
- **`join()`, `wrap()`, `prefix()`, `suffix()`** - Transform and compose generators
- **`comment()`, `blockComment()`, `docComment()`** - Generate any comment style
- **`dumpCall()`, `dumpAttribute()`** - Smart method calls and attributes
- **`statement()`** - Auto-add semicolons where needed

### 🎭 Flexibility First
- **Yield strings, arrays, or generators** - mix and match as needed
- **Compose generators** - results can be wrapped, joined, and transformed
- **Conditional generation** - `maybeWrap()` for optional structures
- **Multi-line strings** - automatic HEREDOC/NOWDOC handling

## 📦 Get Started in Seconds!

Install via Composer and start generating beautiful code immediately:

```bash
composer require ruudk/code-generator --dev
```

That's it! You're ready to transform your PHP code generation. 🚀

## 💡 The Magic: Before & After

### ❌ Before (The Old Way)
```php
// Manual string concatenation, tracking indentation, managing imports...
$code = "<?php\n\n";
$code .= "declare(strict_types=1);\n\n";
$code .= "namespace Example\\Demo;\n\n";
// Manually tracking what needs to be imported
$code .= "use DateTimeImmutable;\n";
$code .= "use Example\\Attributes\\Something;\n";
$code .= "use Example\\Demo;\n";
$code .= "use Example\\Parent;\n\n";
$code .= "// Auto-generated example file\n\n";
$code .= "#[Something]\n";
$code .= "final readonly class Demo extends Parent\n";
$code .= "{\n";
$code .= "    public function __construct(\n";
$code .= "        private DateTimeImmutable \$date,\n";  // Manual indentation!
$code .= "    ) {\n";
$code .= "        parent::__construct(\n";
$code .= "            'Hello, World!',\n";
$code .= "            true,\n";
$code .= "        );\n";
$code .= "    }\n";
$code .= "}\n";

// 😱 Imagine maintaining this for complex files!
```

### ✅ After (The Generator Way)
See the example below - clean, maintainable, and beautiful! The generator handles all the complexity for you.

<!-- source: examples/class.php -->
```php
<?php

declare(strict_types=1);

use Ruudk\CodeGenerator\CodeGenerator;

include 'vendor/autoload.php';

$generator = new CodeGenerator('Example\Demo');

echo $generator->dump([
    '// Auto-generated example file',
    '',

    $generator->dumpAttribute('Example\Attributes\Something'),
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
```

### Output

<!-- output: examples/class.php -->
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
final readonly class Demo extends Parent
{
    public function __construct(
        private DateTimeImmutable $date,
    ) {
        parent::__construct(
            'Hello, World!',
            true,
        );
    }
}
```

## Another Example

<!-- source: examples/example.php -->
```php
<?php

declare(strict_types=1);

use Ruudk\CodeGenerator\CodeGenerator;
use Ruudk\CodeGenerator\Group;

include 'vendor/autoload.php';

$generator = new CodeGenerator('Example\Demo');

// First, let's see what happens step by step
echo $generator->dump(function () use ($generator) {
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
        $generator->import('Example\Interfaces\SecondInterface', byParent: true),
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
```

### Output

<!-- output: examples/example.php -->
```php
<?php

declare(strict_types=1);

namespace Example\Demo;

use App\Enums\Status;
use App\Services\MainService;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use Doctrine\Common\Collections\Collection;
use Example\Attributes\Entity;
use Example\Attributes\Table;
use Example\BaseClass;
use Example\Interfaces;
use Example\Interfaces\BaseInterface;
use Example\Interfaces\FirstInterface;
use Example\SomeClass;
use Logger;
use self;
use stdClass;

// This file demonstrates ALL CodeGenerator features
// Auto-generated example file

#[Entity]
#[Table]
final class DemoClass extends BaseClass implements FirstInterface, Interfaces\SecondInterface
{
    // Class properties
    private DateTimeImmutable $date;

    private Collection $collection;
    private ?stdClass $optional = null;

    public function __construct(
        private readonly MainService $service,
        private Status $enum = Status::Active,
        string $name = 'default',
    ) {
    }

    /**
     * Demonstrates various generator features
     */
    public function processData(array $data): void
    {
        $result = $this->calculate($data);

        $wrapped = [$result];

        if ($result) { $this->save($result); }

        $items = [
            'first',
            'second',
            'third',
            // Comment not suffixed
        ];

        array_map(
            fn($x) => $x * 2,
            $items,
        );

        $concatenated = sprintf('%s', $a, $b, $c);

        $prefix = 'value'
         . 'suffix';

        foreach ($items as $item) {
            echo $item:
            echo PHP_EOL
        }

        new DateTime('now');

        $text = <<<'EOD'
            This is a
            multi-line
            string example
            EOD;

        $className = SomeClass::class;
    }

    private function calculate(array $data): int
    {
        return match($data['type'] ?? null) {
            'sum' => array_sum($data['values']),
            'count' => count($data['values']),
            'max' => max($data['values']),
            default => 0,
        };
    }

    public static function create(): self
    {
        return new self(
            new MainService(),
        );
    }
}

interface CustomInterface extends BaseInterface
{
    public function process(mixed $data): void;

    public function validate(array $rules): bool;
}

enum Color: string
{
    case RED = '#FF0000';
    case GREEN = '#00FF00';
    case BLUE = '#0000FF';

    public function toRgb(): array
    {
        return match($this) {
            self::RED => [255, 0, 0],
            self::GREEN => [0, 255, 0],
            self::BLUE => [0, 0, 255],
        };
    }
}

trait TimestampableTrait
{
    private DateTimeInterface $createdAt;
    private ?DateTimeInterface $updatedAt = null;

    public function updateTimestamps(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        $this->updatedAt = new \DateTimeImmutable();
    }
}

$anonymous = new class(
    $dependency
) {
    public function __construct(private mixed $dep) {}

    public function execute(): void
    {
        echo 'Anonymous class method';
    }
};

function helperFunction(string $input): string
{
    return strtoupper($input);
}
```

## 💖 Support This Project

Love this tool? Help me keep building awesome open source software!

[![Sponsor](https://img.shields.io/badge/Sponsor-%E2%9D%A4-pink)](https://github.com/sponsors/ruudk)

Your sponsorship helps me dedicate more time to maintaining and improving this project. Every contribution, no matter the size, makes a difference!

## 🤝 Contributing

I welcome contributions! Whether it's a bug fix, new feature, or documentation improvement, I'd love to see your PRs.

## 📄 License

MIT License – Free to use in your projects! If you're using this and finding value, please consider [sponsoring](https://github.com/sponsors/ruudk) to support continued development.
