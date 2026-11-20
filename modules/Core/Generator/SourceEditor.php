<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt;
use PhpParser\NodeFinder;
use PhpParser\Parser;
use PhpParser\ParserFactory;

/**
 * Changes existing PHP files of a module (manifest, routes, language files, demo seeder) with
 * `nikic/php-parser` (D-42-01, a development dependency): the file is parsed, the node to change
 * is found in the syntax tree, and only that part of the original text is changed, using the
 * node's positions. Everything else (comments, alignment, blank lines) is kept as written, so the
 * preview's diff shows only what the generator adds. Existing keys are never overwritten.
 */
final class SourceEditor
{
    private readonly Parser $parser;

    private readonly NodeFinder $finder;

    public function __construct()
    {
        if (! self::available()) {
            throw new GeneratorException(lang('Core.generator.noParser'));
        }

        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
        $this->finder = new NodeFinder();
    }

    public static function available(): bool
    {
        return class_exists(ParserFactory::class);
    }

    /**
     * Adds the missing items of `$items` to an array: the default of a class property
     * (`$property`), or the returned array of a language file (`$property` = ''), descending into
     * `$path`. String keys that exist are merged recursively when both sides are arrays and kept
     * otherwise; integer keys append list values that are not there yet.
     *
     * @param array<mixed>    $items
     * @param list<string>    $path
     */
    public function addArrayItems(string $source, string $property, array $items, array $path = []): string
    {
        $array    = $this->findArray($source, $property, $path);
        $existing = $this->items($array);
        $new      = [];
        $nested   = [];

        foreach ($items as $key => $value) {
            if (is_int($key)) {
                if (! in_array($value, $existing['values'], true)) {
                    $new[] = [null, $value];
                }

                continue;
            }

            if (! array_key_exists($key, $existing['keys'])) {
                $new[] = [$key, $value];

                continue;
            }

            if (is_array($value) && $existing['keys'][$key] instanceof Array_) {
                $nested[$key] = $value;
            } elseif (is_array($value)) {
                throw new GeneratorException(lang('Core.generator.keyConflict', [implode('.', [...$path, $key])]));
            }
        }

        if ($new !== []) {
            $source = $this->insertItems($source, $array, $new);
        }

        foreach ($nested as $key => $value) {
            $source = $this->addArrayItems($source, $property, $value, [...$path, (string) $key]);
        }

        return $source;
    }

    /**
     * Sets the default of a class property, or declares it after the property `$after` when the
     * class does not declare it.
     */
    public function setProperty(string $source, string $property, string $type, mixed $value, string $after): string
    {
        $node = $this->findProperty($source, $property);

        if ($node !== null) {
            $default = $node->props[0]->default;

            if ($default === null) {
                throw new GeneratorException(lang('Core.generator.cannotEdit', ['$' . $property]));
            }

            return $this->splice($source, $default->getStartFilePos(), $default->getEndFilePos() + 1, PhpExport::value($value));
        }

        $anchor = $this->findProperty($source, $after) ?? throw new GeneratorException(lang('Core.generator.cannotEdit', ['$' . $after]));
        $indent = $this->indentAt($source, $anchor->getStartFilePos());
        $line   = "\n" . $indent . 'public ' . $type . ' $' . $property . ' = ' . PhpExport::value($value, $indent) . ';';
        $end    = $this->restOfLineEnd($source, $anchor->getEndFilePos() + 1);

        return $this->splice($source, $end, $end, $line);
    }

    public function hasProperty(string $source, string $property): bool
    {
        return $this->findProperty($source, $property) !== null;
    }

    /**
     * Appends statements at the end of a file (the module's routes), after checking both parse.
     */
    public function appendCode(string $source, string $code): string
    {
        $this->parse($source);
        $result = rtrim($source) . "\n\n" . trim($code) . "\n";
        $this->parse($result);

        return $result;
    }

    /**
     * Adds a statement at the end of a method's body (before `return` if it ends with one).
     */
    public function addToMethod(string $source, string $method, string $statement): string
    {
        $node = $this->findMethod($source, $method) ?? throw new GeneratorException(lang('Core.generator.cannotEdit', [$method . '()']));
        $stmts = $node->stmts ?? [];
        $last  = $stmts === [] ? null : $stmts[array_key_last($stmts)];

        if ($last instanceof Stmt\Return_) {
            $indent = $this->indentAt($source, $last->getStartFilePos());

            return $this->splice($source, $this->lineStart($source, $last->getStartFilePos()), $this->lineStart($source, $last->getStartFilePos()), $indent . $statement . "\n\n");
        }

        $close  = $node->getEndFilePos();
        $indent = $this->indentAt($source, $node->getStartFilePos()) . '    ';
        $before = $this->lineStart($source, $close);

        if ($stmts === []) {
            return $this->splice($source, $before, $before, $indent . $statement . "\n");
        }

        return $this->splice($source, $before, $before, $indent . $statement . "\n");
    }

    public function hasMethod(string $source, string $method): bool
    {
        return $this->findMethod($source, $method) !== null;
    }

    /**
     * Adds a method (its code, indented by the caller with 4 spaces) at the end of the class.
     */
    public function addMethod(string $source, string $code): string
    {
        $class = $this->finder->findFirstInstanceOf($this->parse($source), Stmt\Class_::class) ?? throw new GeneratorException(lang('Core.generator.cannotEdit', ['class']));
        $before = $this->lineStart($source, $class->getEndFilePos());

        return $this->splice($source, $before, $before, "\n" . rtrim($code) . "\n");
    }

    /**
     * Adds a `use` import after the last one (or after the namespace) unless it is there.
     */
    public function addUse(string $source, string $class): string
    {
        $stmts = $this->parse($source);

        foreach ($this->finder->findInstanceOf($stmts, Stmt\Use_::class) as $use) {
            foreach ($use->uses as $item) {
                if ($item->name->toString() === ltrim($class, '\\')) {
                    return $source;
                }
            }
        }

        $uses = $this->finder->findInstanceOf($stmts, Stmt\Use_::class);
        $line = 'use ' . ltrim($class, '\\') . ';';

        if ($uses !== []) {
            $last = $uses[array_key_last($uses)];

            return $this->splice($source, $last->getEndFilePos() + 1, $last->getEndFilePos() + 1, "\n" . $line);
        }

        $namespace = $this->finder->findFirstInstanceOf($stmts, Stmt\Namespace_::class);

        if ($namespace instanceof Stmt\Namespace_ && $namespace->name !== null) {
            $end = $namespace->name->getEndFilePos() + 2;

            return $this->splice($source, $end, $end, "\n\n" . $line);
        }

        throw new GeneratorException(lang('Core.generator.cannotEdit', ['use']));
    }

    /**
     * @return list<Node>
     */
    public function parse(string $source): array
    {
        try {
            return array_values($this->parser->parse($source) ?? []);
        } catch (\PhpParser\Error $e) {
            throw new GeneratorException(lang('Core.generator.parseError', [$e->getMessage()]), 0, $e);
        }
    }

    /**
     * @param list<string> $path
     */
    private function findArray(string $source, string $property, array $path): Array_
    {
        $stmts = $this->parse($source);

        if ($property === '') {
            $return = null;

            foreach ($stmts as $stmt) {
                if ($stmt instanceof Stmt\Return_) {
                    $return = $stmt->expr;
                }
            }

            $array = $return;
        } else {
            $array = $this->findPropertyIn($stmts, $property)?->props[0]->default;
        }

        $where = $property === '' ? 'return' : '$' . $property;

        foreach ($path as $key) {
            if (! $array instanceof Array_) {
                break;
            }

            $found = null;

            foreach ($array->items as $item) {
                if ($item->key instanceof String_ && $item->key->value === $key) {
                    $found = $item->value;
                }
            }

            $array = $found;
            $where .= '.' . $key;
        }

        if (! $array instanceof Array_) {
            throw new GeneratorException(lang('Core.generator.cannotEdit', [$where]));
        }

        return $array;
    }

    /**
     * @return array{keys: array<string, Node\Expr>, values: list<mixed>}
     */
    private function items(Array_ $array): array
    {
        $keys   = [];
        $values = [];

        foreach ($array->items as $item) {
            if ($item->key instanceof String_) {
                $keys[$item->key->value] = $item->value;
            } elseif ($item->key === null && $item->value instanceof String_) {
                $values[] = $item->value->value;
            } elseif ($item->key === null && $item->value instanceof Node\Expr\ClassConstFetch && $item->value->class instanceof Node\Name) {
                $values[] = $item->value->class->toString() . '::class';
            }
        }

        return ['keys' => $keys, 'values' => $values];
    }

    /**
     * @param list<array{0: string|null, 1: mixed}> $items
     */
    private function insertItems(string $source, Array_ $array, array $items): string
    {
        $start  = $array->getStartFilePos();
        $end    = $array->getEndFilePos();
        $base   = $this->indentAt($source, $start);
        $nodes  = array_values($array->items);

        if ($nodes === []) {
            $indent = $base . '    ';
            $text   = "[\n";

            foreach ($items as [$key, $value]) {
                $text .= $indent . PhpExport::item($key, $value, $indent) . ",\n";
            }

            return $this->splice($source, $start, $end + 1, $text . $base . ']');
        }

        $last     = $nodes[array_key_last($nodes)];
        $after    = $last->getEndFilePos() + 1;
        $between  = substr($source, $after, $end - $after);
        $comma    = strpos($between, ',');
        $hasComma = $comma !== false && trim(substr($between, 0, $comma)) === '';
        $at       = $hasComma ? $after + $comma + 1 : $after;
        $multi    = str_contains(substr($source, $start, $end - $start), "\n");

        if ($multi) {
            $indent = $this->indentAt($source, $nodes[0]->getStartFilePos());

            if (! $hasComma) {
                $source = $this->splice($source, $after, $after, ',');
                $at     = $after + 1;
            }

            // After a trailing comment of the last item's line.
            $lineEnd = $this->restOfLineEnd($source, $at);
            $text    = '';

            foreach ($items as [$key, $value]) {
                $text .= "\n" . $indent . PhpExport::item($key, $value, $indent) . ',';
            }

            return $this->splice($source, $lineEnd, $lineEnd, $text);
        }

        $text = $hasComma ? ' ' : ', ';
        $text .= implode(', ', array_map(static fn (array $i): string => PhpExport::item($i[0], $i[1], $base), $items));

        return $this->splice($source, $at, $at, $text);
    }

    private function findProperty(string $source, string $property): ?Stmt\Property
    {
        return $this->findPropertyIn($this->parse($source), $property);
    }

    /**
     * @param list<Node> $stmts
     */
    private function findPropertyIn(array $stmts, string $property): ?Stmt\Property
    {
        $node = $this->finder->findFirst($stmts, static fn (Node $n): bool => $n instanceof Stmt\Property && $n->props[0]->name->toString() === $property);

        return $node instanceof Stmt\Property ? $node : null;
    }

    private function findMethod(string $source, string $method): ?Stmt\ClassMethod
    {
        $node = $this->finder->findFirst($this->parse($source), static fn (Node $n): bool => $n instanceof Stmt\ClassMethod && $n->name->toString() === $method);

        return $node instanceof Stmt\ClassMethod ? $node : null;
    }

    private function indentAt(string $source, int $position): string
    {
        $line = substr($source, $this->lineStart($source, $position));

        return (string) preg_replace('/^([ \t]*).*/s', '$1', $line);
    }

    /**
     * The end of the line at `$position` when the rest of it is blank or a `//` comment;
     * otherwise `$position` itself.
     */
    private function restOfLineEnd(string $source, int $position): int
    {
        $newline = strpos($source, "\n", $position);
        $end     = $newline === false ? strlen($source) : $newline;
        $rest    = trim(substr($source, $position, $end - $position));

        return $rest === '' || str_starts_with($rest, '//') || str_starts_with($rest, '#') ? $end : $position;
    }

    private function lineStart(string $source, int $position): int
    {
        $newline = strrpos(substr($source, 0, $position), "\n");

        return $newline === false ? 0 : $newline + 1;
    }

    private function splice(string $source, int $from, int $to, string $text): string
    {
        return substr($source, 0, $from) . $text . substr($source, $to);
    }
}
