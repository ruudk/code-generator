<?php

declare(strict_types=1);

namespace Ruudk\CodeGenerator;

use Closure;
use Generator;
use UnitEnum;

/**
 * @phpstan-type CodeLine string|Group
 * @phpstan-type CodeLines Closure(): (Generator<CodeLine>|array<CodeLine>|string)|array<CodeLine>|string
 */
final class CodeGenerator
{
    /**
     * @var array<string, string>
     */
    private array $imports = [];

    public function __construct(
        private readonly ?string $namespace = null,
    ) {}

    /**
     * Dumps the generated code with proper formatting, namespace, and imports
     * @param CodeLines $iterable
     */
    public function dump(array | Closure | Generator | string $iterable) : string
    {
        $resolvedContent = self::resolveIterable($iterable);

        return rtrim(
            ltrim(
                implode(
                    PHP_EOL,
                    self::resolveIterable($this->maybeIndent(function () use ($resolvedContent) {
                        yield '<?php';
                        yield '';
                        yield 'declare(strict_types=1);';
                        yield '';

                        if ($this->namespace !== null) {
                            yield sprintf('namespace %s;', $this->namespace);
                            yield '';
                        }

                        yield from $this->maybeDump([], $this->dumpImports(), ['']);

                        yield from $resolvedContent;
                    })),
                ),
                ' ',
            ),
            PHP_EOL,
        ) . PHP_EOL;
    }

    /**
     * Generates sorted import statements for all registered imports
     * @return Generator<string>
     */
    private function dumpImports() : Generator
    {
        uasort(
            $this->imports,
            fn($left, $right) => strcasecmp(
                str_replace('\\', ' ', str_starts_with($left, 'function ') ? substr($left, 9) : $left),
                str_replace('\\', ' ', str_starts_with($right, 'function ') ? substr($right, 9) : $right),
            ),
        );

        foreach ($this->imports as $alias => $import) {
            if ( ! str_starts_with($import, 'function ')) {
                [$namespace, $class] = $this->splitFqcn($import);

                if ($namespace === $this->namespace) {
                    continue;
                }

                if ($alias !== $class) {
                    yield sprintf('use %s as %s;', $import, $alias);

                    continue;
                }
            }

            yield sprintf('use %s;', $import);
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
     * Splits a fully qualified class name into namespace and class name parts
     * @return array{string, string}
     */
    public function splitFqcn(string $fqcn) : array
    {
        $parts = explode('\\', $fqcn);
        $className = array_pop($parts);
        $namespace = implode('\\', $parts);

        return [$namespace, $className];
    }

    /**
     * Finds an available alias for a type, appending numbers if the alias is already taken
     */
    private function findAvailableAlias(string $type, string $alias, int $i = 1) : string
    {
        $aliasToCheck = $i === 1 ? $alias : sprintf('%s%d', $alias, $i);

        if ( ! isset($this->imports[$aliasToCheck]) || $this->imports[$aliasToCheck] === $type) {
            return $aliasToCheck;
        }

        return $this->findAvailableAlias($type, $alias, $i + 1);
    }

    /**
     * Imports a class, function, or enum and returns the alias to use in the generated code
     */
    public function import(string | UnitEnum $fqcnOrEnum, bool $byParent = false) : string
    {
        if (is_string($fqcnOrEnum) && str_starts_with($fqcnOrEnum, 'function ')) {
            $this->imports[$fqcnOrEnum] = $fqcnOrEnum;

            $parts = explode('\\', $fqcnOrEnum);

            return array_pop($parts);
        }

        if ($fqcnOrEnum instanceof UnitEnum) {
            $type = $fqcnOrEnum::class;
        } else {
            $type = $fqcnOrEnum;
        }

        [$namespace, $alias] = $this->splitFqcn($type);

        if ($byParent) {
            [, $parent] = $this->splitFqcn($namespace);

            $parent = $this->findAvailableAlias($namespace, $parent);

            $this->imports[$parent] = $namespace;

            $reference = sprintf('%s\\%s', $parent, $alias);
        } else {
            $alias = $this->findAvailableAlias($type, $alias);

            $this->imports[$alias] = $type;
            $reference = $alias;
        }

        if ($fqcnOrEnum instanceof UnitEnum) {
            return sprintf('%s::%s', $reference, $fqcnOrEnum->name);
        }

        return $reference;
    }

    /**
     * Generates a PHP attribute string for the given fully qualified class name
     */
    public function dumpAttribute(string $fqcn) : string
    {
        return sprintf('#[%s]', $this->import($fqcn));
    }

    /**
     * Generates a class reference string (e.g., Foo::class)
     */
    public function dumpClassReference(string $fqcn, bool $import = true, bool $byParent = false) : string
    {
        return sprintf('%s::class', $import ? $this->import($fqcn, $byParent) : '\\' . $fqcn);
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
                yield Group::indent($previousValue->indention, $this->suffixLast($suffix, $previousValue->lines));

                return;
            }

            yield $previousValue . $suffix;
        }
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
                    yield Group::indent($line->indention, $this->prefixFirst($prefix, $line->lines));

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
                    yield Group::indent($line->indention, $this->suffixFirst($suffix, $line->lines));

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
                $i++;
                $first = $line;

                continue;
            }

            if ($i === 1) {
                $i++;

                if ($line instanceof Group && $first !== null) {
                    $prefix = $first instanceof Group ? '' : (string) $first;
                    yield Group::indent($line->indention, $this->prefixFirst($prefix, $line->lines));

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

            $i++;
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
            yield Group::indent(1, function () use ($addCommaAfterEachArgument, $method, $args) {
                if ($args === []) {
                    yield sprintf('->%s()', $method);

                    return;
                }

                if (count($args) === 1) {
                    yield from $this->wrap(sprintf('->%s(', $method), $args, ')');

                    return;
                }

                yield sprintf('->%s(', $method);
                yield Group::indent(1, $addCommaAfterEachArgument ? $this->allSuffix(',', $args) : $args);
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
        yield Group::indent(1, $addCommaAfterEachArgument ? $this->allSuffix(',', $args) : $args);
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

        if (count($args) <= 1) {
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
        yield Group::indent(1, $this->allSuffix(',', $args));
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
                $resolved = array_merge($resolved, iterator_to_array($item));
            } else {
                $resolved[] = $item;
            }
        }

        return $resolved;
    }
}
