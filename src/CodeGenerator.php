<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use Closure;
use Generator;
use UnitEnum;

/**
 * @phpstan-type CodeLine string|Group
 * @phpstan-type CodeLines Closure(): (Generator<CodeLine>|array<CodeLine>|string)|array<CodeLine|Generator<CodeLine>>|Generator<CodeLine>|string
 */
final class CodeGenerator
{
    /**
     * @var array<string, Alias|FullyQualified|FunctionName|NamespaceName>
     */
    private array $imports = [];
    private readonly ?NamespaceName $namespace;

    public function __construct(
        null | NamespaceName | string $namespace = null,
    ) {
        $this->namespace = NamespaceName::maybeFromString($namespace);
    }

    /**
     * Dumps the generated code with proper formatting (removes consecutive newlines, trims)
     * @param CodeLines $iterable
     */
    public function dump(array | Closure | Generator | string $iterable) : string
    {
        $output = implode(
            PHP_EOL,
            self::resolveIterable($this->maybeIndent($iterable)),
        );

        // Replace consecutive newlines with a single newline
        $output = preg_replace('/(\r?\n){3,}/', PHP_EOL . PHP_EOL, $output) ?? '';

        return rtrim($output);
    }

    /**
     * Dumps a complete PHP file with opening tag, declare, namespace, and imports
     * @param CodeLines $iterable
     */
    public function dumpFile(array | Closure | Generator | string $iterable) : string
    {
        $content = $this->dump(function () use ($iterable) {
            yield '<?php';
            yield '';
            yield 'declare(strict_types=1);';
            yield '';

            if ($this->namespace !== null) {
                yield sprintf('namespace %s;', $this->namespace);
                yield '';
            }

            $content = self::resolveIterable($iterable);

            yield from $this->maybeDump([], $this->dumpImports(), ['']);

            yield from $content;
        });

        // Ensure file ends with a newline
        return $content === '' ? '' : $content . PHP_EOL;
    }

    /**
     * Generates sorted import statements for all registered imports
     * @return Generator<string>
     */
    private function dumpImports() : Generator
    {
        uasort($this->imports, fn($left, $right) => $left->compare($right));

        foreach ($this->imports as $alias => $import) {
            if ($import instanceof Alias) {
                yield $import->toUseStatement();

                continue;
            }

            if ($import instanceof FunctionName) {
                // Handle function imports
                yield sprintf('use %s;', $import);

                continue;
            }

            if ($import instanceof NamespaceName) {
                // Parent namespace import - check if we need an alias
                $lastPart = $import->lastPart;

                if ($alias !== $lastPart) {
                    yield sprintf('use %s as %s;', $import, $alias);
                } else {
                    yield sprintf('use %s;', $import);
                }

                continue;
            }

            // Skip if it's in the same namespace as the file
            if ($import->namespace !== null && $this->namespace !== null && $import->namespace->equals($this->namespace)) {
                continue;
            }

            // Check if we need an alias
            if ($alias !== $import->className->name) {
                yield sprintf('use %s as %s;', $import, $alias);
            } else {
                yield sprintf('use %s;', $import);
            }
        }
    }

    /**
     * Yields from data with optional before/after content.
     * Only yields before/after if the data is not empty.
     *
     * @param null|CodeLines $before
     * @param CodeLines $data
     * @param null|CodeLines $after
     *
     * @return Generator<CodeLine>
     */
    public function maybeDump(
        null | array | Closure | Generator | string $before,
        array | Closure | Generator | string $data,
        null | array | Closure | Generator | string $after,
    ) : Generator {
        $hasContent = false;

        foreach (self::resolveIterable($data) as $item) {
            if ( ! $hasContent) {
                $hasContent = true;

                if ($before !== null) {
                    yield from self::resolveIterable($before);
                }
            }

            yield $item;
        }

        if ( ! $hasContent || $after === null) {
            return;
        }

        yield from self::resolveIterable($after);
    }

    /**
     * Finds an available alias for a type, appending numbers if the alias is already taken
     */
    private function findAvailableAlias(Importable $type, string $alias, int $i = 1) : string
    {
        $aliasToCheck = $i === 1 ? $alias : sprintf('%s%d', $alias, $i);

        if ( ! isset($this->imports[$aliasToCheck])) {
            return $aliasToCheck;
        }

        $existing = $this->imports[$aliasToCheck];

        // Check if it's the same import
        if ($existing->equals($type)) {
            return $aliasToCheck;
        }

        return $this->findAvailableAlias($type, $alias, $i + 1);
    }

    /**
     * Imports an enum and returns the fully qualified reference to use in the generated code
     */
    public function importEnum(UnitEnum $enum) : string
    {
        return sprintf('%s::%s', $this->import($enum::class), $enum->name);
    }

    /**
     * Imports a class, namespace, or function and returns the alias to use in the generated code
     */
    public function import(Importable | string $fqcnOrEnum) : string
    {
        if ($fqcnOrEnum instanceof FunctionName) {
            $alias = $this->findAvailableAlias($fqcnOrEnum, $fqcnOrEnum->shortName);
            $this->imports[$alias] = $fqcnOrEnum;

            return $alias;
        }

        if ($fqcnOrEnum instanceof NamespaceName) {
            $alias = $this->findAvailableAlias($fqcnOrEnum, $fqcnOrEnum->lastPart);
            $this->imports[$alias] = $fqcnOrEnum;

            return $alias;
        }

        $fqcn = FullyQualified::maybeFromString($fqcnOrEnum);
        $alias = $this->findAvailableAlias($fqcn, $fqcn->className->name);
        $this->imports[$alias] = $fqcn;

        return $alias;
    }

    /**
     * Imports a class by importing its parent namespace and returning the relative path
     */
    public function importByParent(Importable | string $name) : string
    {
        $fqcn = FullyQualified::maybeFromString($name);

        // If there's no namespace, just return the class name
        if ($fqcn->namespace === null) {
            return (string) $fqcn->className;
        }

        // Check if the full target namespace is the same as the current namespace
        if ($this->namespace?->equals($fqcn->namespace) === true) {
            return (string) $fqcn->className;
        }

        // Import the namespace and return the alias with class name
        return (string) new FullyQualified(
            $this->import($fqcn->namespace),
            $fqcn->className,
        );
    }

    /**
     * Generates a PHP attribute string for the given fully qualified class name
     */
    public function dumpAttribute(FullyQualified | string $fqcn) : string
    {
        return sprintf('#[%s]', $this->import($fqcn));
    }

    /**
     * Generates a class reference string (e.g., Foo::class)
     */
    public function dumpClassReference(FullyQualified | string $fqcn, bool $import = true, bool $byParent = false) : string
    {
        $fqcn = FullyQualified::maybeFromString($fqcn);

        return sprintf(
            '%s::class',
            match (true) {
                $import && $byParent => $this->importByParent($fqcn),
                $import => $this->import($fqcn),
                default => '\\' . $fqcn,
            },
        );
    }

    /**
     * Applies indentation to lines based on their level and Group indentation
     *
     * @param CodeLines $data
     * @return Generator<string>
     */
    private function maybeIndent(array | Closure | Generator | string $data, int $level = 0) : Generator
    {
        foreach (self::resolveIterable($data) as $line) {
            if ($line instanceof Group) {
                yield from $this->maybeIndent($line->lines, $level + $line->indention);

                continue;
            }

            $line = implode(
                PHP_EOL,
                array_map(
                    fn($line) => str_repeat('    ', $level) . $line,
                    explode(PHP_EOL, $line),
                ),
            );

            yield trim($line) === '' ? '' : $line;
        }
    }

    /**
     * Formats a string as a nowdoc if it contains newlines, otherwise as a regular string
     */
    public function maybeNowDoc(string $input, string $tag = 'EOD') : string
    {
        if ( ! str_contains($input, PHP_EOL)) {
            return var_export($input, true);
        }

        return sprintf("<<<'%s'\n%s", $tag, implode(
            PHP_EOL,
            array_map(
                fn($line) => '    ' . $line,
                [...explode(PHP_EOL, $input), $tag],
            ),
        ));
    }

    /**
     * Wraps code lines as a statement by adding a semicolon to the last line
     * @param CodeLines $data
     * @return Generator<CodeLine>
     */
    public function statement(array | Closure | Generator | string $data) : Generator
    {
        yield from $this->suffixLast(';', $data);
    }

    /**
     * Adds a suffix to the last line of the iterable
     *
     * @param CodeLines $data
     * @return Generator<CodeLine>
     */
    public function suffixLast(string $suffix, array | Closure | Generator | string $data) : Generator
    {
        foreach (self::resolveIterable($data) as $line) {
            if (isset($previousValue)) {
                yield $previousValue;
            }

            $previousValue = $line;
        }

        if (isset($previousValue)) {
            if ($previousValue instanceof Group) {
                yield Group::indent($this->suffixLast($suffix, $previousValue->lines), $previousValue->indention);

                return;
            }

            yield $previousValue . $suffix;
        }
    }

    /**
     * Trims empty lines from the start and end of the iterable
     *
     * @param CodeLines $data
     * @return Generator<CodeLine>
     */
    public function trim(array | Closure | Generator | string $data) : Generator
    {
        $lines = self::resolveIterable($data);

        // Find first non-empty line
        $start = 0;
        foreach ($lines as $i => $line) {
            if ( ! ($line instanceof Group) && trim($line) === '') {
                ++$start;
            } else {
                break;
            }
        }

        // Find last non-empty line
        $end = count($lines) - 1;
        for ($i = $end; $i >= $start; --$i) {
            if ( ! ($lines[$i] instanceof Group) && trim($lines[$i]) === '') {
                --$end;
            } else {
                break;
            }
        }

        // Yield the trimmed content
        for ($i = $start; $i <= $end; ++$i) {
            yield $lines[$i];
        }
    }

    /**
     * Creates an indented Group, optionally trimming empty lines first
     *
     * @param CodeLines $data
     */
    public function indent(array | Closure | Generator | string $data, bool $trim = true, int $indention = 1) : Group
    {
        return Group::indent($trim ? $this->trim($data) : $data, $indention);
    }

    /**
     * Wraps code lines with a prefix and optional suffix
     *
     * @param CodeLines $data
     * @return Generator<CodeLine>
     */
    public function wrap(string $prefix, array | Closure | Generator | string $data, ?string $suffix = null) : Generator
    {
        yield from $this->prefixFirst(
            $prefix,
            $suffix !== null ? $this->suffixLast($suffix, $data) : $data,
        );
    }

    /**
     * Conditionally wraps code lines with a prefix and optional suffix
     * @param CodeLines $data
     * @return Generator<CodeLine>
     */
    public function maybeWrap(bool $condition, string $prefix, array | Closure | Generator | string $data, ?string $suffix = null) : Generator
    {
        $data = self::resolveIterable($data);

        if ($condition) {
            yield from $this->wrap($prefix, $data, $suffix);
        } else {
            yield from $data;
        }
    }

    /**
     * Adds a prefix to the first line of the iterable
     *
     * @param CodeLines $data
     * @return Generator<CodeLine>
     */
    public function prefixFirst(string $prefix, array | Closure | Generator | string $data) : Generator
    {
        $first = true;
        foreach (self::resolveIterable($data) as $line) {
            if ($first) {
                $first = false;

                if ($line instanceof Group) {
                    yield Group::indent($this->prefixFirst($prefix, $line->lines), $line->indention);

                    continue;
                }

                yield $prefix . $line;

                continue;
            }

            yield $line;
        }
    }

    /**
     * Adds a suffix to the first line of the iterable
     *
     * @param CodeLines $data
     *
     * @return Generator<string|Group>
     */
    public function suffixFirst(string $suffix, array | Closure | Generator | string $data) : Generator
    {
        $first = true;
        foreach (self::resolveIterable($data) as $line) {
            if ($first) {
                $first = false;

                if ($line instanceof Group) {
                    yield Group::indent($this->suffixFirst($suffix, $line->lines), $line->indention);

                    continue;
                }

                yield $line . $suffix;

                continue;
            }

            yield $line;
        }
    }

    /**
     * Joins code lines with a delimiter into a single string
     *
     * @param CodeLines $data
     */
    public function join(string $delimiter, array | Closure | Generator | string $data) : string
    {
        $resolved = [];
        foreach (self::resolveIterable($data) as $item) {
            $resolved[] = $item instanceof Group ? '' : (string) $item;
        }

        return implode($delimiter, $resolved);
    }

    /**
     * Joins the first two elements of the iterable together
     *
     * @param CodeLines $data
     *
     * @return Generator<string|Group>
     */
    public function joinFirstPair(array | Closure | Generator | string $data) : Generator
    {
        $first = null;
        $i = 0;
        foreach (self::resolveIterable($data) as $line) {
            if ($i === 0) {
                ++$i;
                $first = $line;

                continue;
            }

            if ($i === 1) {
                ++$i;

                if ($line instanceof Group && $first !== null) {
                    $prefix = $first instanceof Group ? '' : (string) $first;
                    $lines = self::resolveIterable($line->lines);

                    if ($lines === []) {
                        yield $prefix;
                    } else {
                        yield Group::indent($this->prefixFirst($prefix, $lines), $line->indention);
                    }

                    continue;
                }

                if ($first !== null) {
                    $prefix = $first instanceof Group ? '' : (string) $first;
                    $suffix = $line instanceof Group ? '' : (string) $line;
                    yield $prefix . $suffix;
                } else {
                    yield $line;
                }

                continue;
            }

            ++$i;
            yield $line;
        }

        if ($i === 1 && $first !== null) {
            yield $first;
        }
    }

    /**
     * Adds a suffix to all lines except comments
     *
     * @param CodeLines $data
     *
     * @return Generator<CodeLine>
     */
    public function allSuffix(string $suffix, array | Closure | Generator | string $data) : Generator
    {
        foreach (self::resolveIterable($data) as $value) {
            if ($value instanceof Group) {
                yield new ($value::class)($this->suffixLast($suffix, $value->lines));

                continue;
            }

            if (str_starts_with($value, '//')) {
                yield $value;

                continue;
            }

            yield $value . $suffix;
        }
    }

    /**
     * Generates a method call or constructor invocation
     *
     * @param CodeLines $object
     * @param CodeLines $args
     *
     * @return Generator<CodeLine>
     */
    public function dumpCall(
        array | Closure | Generator | string $object,
        string $method,
        array | Closure | Generator | string $args = [],
        bool $static = false,
        bool $addCommaAfterEachArgument = true,
    ) : Generator {
        $args = self::resolveIterable($args);

        if ( ! is_string($object)) {
            yield from self::resolveIterable($object);
            yield Group::indent(function () use ($addCommaAfterEachArgument, $method, $args) {
                if ($args === []) {
                    yield sprintf('->%s()', $method);

                    return;
                }

                if (count($args) === 1) {
                    yield from $this->wrap(sprintf('->%s(', $method), $args, ')');

                    return;
                }

                yield sprintf('->%s(', $method);
                yield Group::indent($addCommaAfterEachArgument ? $this->allSuffix(',', $args) : $args);
                yield ')';
            });

            return;
        }

        if (($static && $method !== '__construct') || ( ! $static && $method === '__construct')) {
            $object = $this->import($object);
        }

        $call = match (true) {
            ! $static && $method === '__construct' => sprintf('new %s', $object),
            default => sprintf('%s%s%s', $object, $static ? '::' : '->', $method),
        };

        if ($args === []) {
            yield sprintf('%s()', $call);

            return;
        }

        if (count($args) === 1) {
            yield from $this->wrap(sprintf('%s(', $call), $args, ')');

            return;
        }

        yield sprintf('%s(', $call);
        yield Group::indent($addCommaAfterEachArgument ? $this->allSuffix(',', $args) : $args);
        yield ')';
    }

    /**
     * Generates a function call with arguments
     *
     * @param CodeLines $args
     *
     * @return Generator<CodeLine>
     */
    public function dumpFunctionCall(string $function, array | Closure | Generator | string $args = []) : Generator
    {
        $args = self::resolveIterable($args);

        if (count($args) === 0) {
            yield sprintf('%s()', $function);

            return;
        }

        if (count($args) === 1) {
            yield from $this->prefixFirst(
                sprintf('%s(', $function),
                $this->suffixLast(
                    ')',
                    $args,
                ),
            );

            return;
        }

        yield sprintf('%s(', $function);
        yield Group::indent($this->allSuffix(',', $args));
        yield ')';
    }

    /**
     * Resolves a callable, iterable, or string into an array of lines
     * @param CodeLines $iterable
     *
     * @return ($iterable is iterable<string> ? array<string> : array<CodeLine>)
     */
    public static function resolveIterable(array | Closure | Generator | string $iterable) : array
    {
        if (is_callable($iterable)) {
            $iterable = $iterable();
        }

        if (is_string($iterable)) {
            return [$iterable];
        }

        $resolved = [];
        foreach ($iterable as $item) {
            if ($item instanceof Generator) {
                $resolved = array_merge($resolved, iterator_to_array($item, false));
            } else {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }

    /**
     * Adds a prefix to every line of the iterable
     *
     * @param CodeLines $data
     * @return Generator<string|Group>
     */
    public function prefix(string $prefix, array | Closure | Generator | string $data) : Generator
    {
        foreach (CodeGenerator::resolveIterable($data) as $line) {
            if ($line instanceof Group) {
                yield Group::indent($this->prefix($prefix, $line->lines), $line->indention);

                continue;
            }

            foreach (explode(PHP_EOL, $line) as $singleLine) {
                yield $prefix . $singleLine;
            }
        }
    }

    /**
     * Adds single-line comment prefix to every line
     *
     * @param CodeLines $data
     * @return Generator<string|Group>
     */
    public function comment(array | Closure | Generator | string $data) : Generator
    {
        yield from $this->prefix('// ', $data);
    }

    /**
     * Wraps content in a block comment
     *
     * @param CodeLines $data
     * @return Generator<string|Group>
     */
    public function blockComment(array | Closure | Generator | string $data) : Generator
    {
        yield from $this->maybeDump('/*', $this->prefix(' * ', $data), ' */');
    }

    /**
     * Wraps content in a PHPDoc comment
     *
     * @param CodeLines $data
     * @return Generator<string|Group>
     */
    public function docComment(array | Closure | Generator | string $data) : Generator
    {
        yield from $this->maybeDump('/**', $this->prefix(' * ', $data), ' */');
    }
}
