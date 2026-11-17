<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

/**
 * Reads the property defaults of a manifest class (`Config/Module.php`, a theme's `Theme.php`)
 * **without running it**: the file is tokenised and only literal values are evaluated (strings,
 * numbers, booleans, null, arrays, `X::class`). Anything else (a constant, a call, a
 * concatenation…) reads as unknown. A package is shown and checked with this before any of its
 * code is loaded; the real manifest is validated by `modules:sync` once it is installed.
 */
final class ManifestReader
{
    /** Marks a value that is not a literal. */
    public const UNKNOWN = "\0unknown";

    /** @var list<array{0: int, 1: string}|string> */
    private array $tokens = [];

    private int $pos = 0;

    /**
     * @return array{class: string, extends: string, namespace: string, properties: array<string, mixed>}
     *
     * @throws PackageRefused when the file holds no class
     */
    public function read(string $code): array
    {
        $this->tokens = array_values(array_filter(
            token_get_all($code),
            static fn ($token): bool => ! is_array($token) || ! in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_CLOSE_TAG], true),
        ));
        $this->pos = 0;

        $namespace = '';
        $class     = '';
        $extends   = '';
        $count     = count($this->tokens);

        // Namespace and the first class declaration.
        for (; $this->pos < $count; $this->pos++) {
            $token = $this->tokens[$this->pos];

            if (is_array($token) && $token[0] === T_NAMESPACE) {
                $this->pos++;
                $namespace = $this->name();
            } elseif (is_array($token) && $token[0] === T_CLASS) {
                $this->pos++;
                $class = $this->text($this->tokens[$this->pos] ?? '');
                $this->pos++;

                if ($this->is(T_EXTENDS)) {
                    $this->pos++;
                    $extends = $this->name();
                }

                break;
            }
        }

        if ($class === '') {
            throw new PackageRefused(lang('Core.packages.noClass'));
        }

        while ($this->pos < $count && $this->tokens[$this->pos] !== '{') {
            $this->pos++;
        }

        $this->pos++;
        $properties = [];
        $depth      = 1;

        // Class body: `[modifiers] [type] $name = <expr>;` at depth 1; methods are skipped.
        while ($this->pos < $count && $depth > 0) {
            $token = $this->tokens[$this->pos];

            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
                $this->pos++;

                continue;
            }

            if ($token === '}') {
                $depth--;
                $this->pos++;

                continue;
            }

            if ($depth === 1 && is_array($token) && $token[0] === T_FUNCTION) {
                $this->skipFunction();

                continue;
            }

            if ($depth === 1 && is_array($token) && $token[0] === T_VARIABLE) {
                $name = substr($token[1], 1);
                $this->pos++;

                if (($this->tokens[$this->pos] ?? null) === '=') {
                    $this->pos++;
                    $properties[$name] = $this->expression();
                }

                // Skip to the end of the declaration.
                while ($this->pos < $count && $this->tokens[$this->pos] !== ';' && $this->tokens[$this->pos] !== '}') {
                    $this->pos++;
                }

                continue;
            }

            $this->pos++;
        }

        return ['class' => $class, 'extends' => $extends, 'namespace' => $namespace, 'properties' => $properties];
    }

    /**
     * Reads a file of a package folder.
     *
     * @return array{class: string, extends: string, namespace: string, properties: array<string, mixed>}
     */
    public function readFile(string $file): array
    {
        $code = @file_get_contents($file);

        if ($code === false) {
            throw new PackageRefused(lang('Core.packages.noManifest'));
        }

        return $this->read($code);
    }

    /**
     * A string property, or $default when it is missing or not a literal string.
     *
     * @param array<string, mixed> $properties
     */
    public static function string(array $properties, string $key, string $default = ''): string
    {
        $value = $properties[$key] ?? null;

        return is_string($value) && $value !== self::UNKNOWN ? $value : $default;
    }

    /**
     * An array property (unknown values inside stay as UNKNOWN), or [] when it is not an array.
     *
     * @param array<string, mixed> $properties
     *
     * @return array<array-key, mixed>
     */
    public static function array(array $properties, string $key): array
    {
        $value = $properties[$key] ?? null;

        return is_array($value) ? $value : [];
    }

    private function expression(): mixed
    {
        $token = $this->tokens[$this->pos] ?? null;

        if ($token === null) {
            return self::UNKNOWN;
        }

        // Arrays: [ ... ] or array( ... )
        if ($token === '[' || (is_array($token) && $token[0] === T_ARRAY)) {
            $close = $token === '[' ? ']' : ')';
            $this->pos += $token === '[' ? 1 : 2;

            return $this->arrayBody($close);
        }

        $negative = false;

        if ($token === '-') {
            $negative = true;
            $this->pos++;
            $token = $this->tokens[$this->pos] ?? null;
        }

        $value = self::UNKNOWN;

        if (is_array($token)) {
            switch ($token[0]) {
                case T_CONSTANT_ENCAPSED_STRING:
                    $value = $this->stringLiteral($token[1]);
                    break;

                case T_LNUMBER:
                    $value = (int) $token[1];
                    break;

                case T_DNUMBER:
                    $value = (float) $token[1];
                    break;

                case T_STRING:
                case T_NAME_QUALIFIED:
                case T_NAME_FULLY_QUALIFIED:
                case T_NAME_RELATIVE:
                    $word = strtolower($token[1]);

                    if (in_array($word, ['true', 'false', 'null'], true)) {
                        $value = match ($word) {
                            'true'  => true,
                            'false' => false,
                            default => null,
                        };
                        break;
                    }

                    $name = $this->name();

                    if ($this->is(T_DOUBLE_COLON)) {
                        $this->pos++;

                        if ($this->is(T_CLASS)) {
                            $this->pos++;

                            return $this->finish(ltrim($name, '\\'));
                        }
                    }

                    return $this->finish(self::UNKNOWN);
            }
        }

        $this->pos++;

        if ($negative && (is_int($value) || is_float($value))) {
            $value = -$value;
        } elseif ($negative) {
            $value = self::UNKNOWN;
        }

        return $this->finish($value);
    }

    /**
     * After a value: anything but `,`, `;`, `]` or `)` makes it an expression we do not evaluate.
     */
    private function finish(mixed $value): mixed
    {
        $next = $this->tokens[$this->pos] ?? null;

        if (in_array($next, [',', ';', ']', ')'], true) || $next === null) {
            return $value;
        }

        if (is_array($next) && $next[0] === T_DOUBLE_ARROW) {
            return $value;
        }

        // Skip the rest of the expression (to the next separator at this level).
        $depth = 0;

        while (($token = $this->tokens[$this->pos] ?? null) !== null) {
            if ($token === '(' || $token === '[') {
                $depth++;
            } elseif (($token === ')' || $token === ']') && $depth > 0) {
                $depth--;
            } elseif ($depth === 0 && (in_array($token, [',', ';', ']', ')'], true) || (is_array($token) && $token[0] === T_DOUBLE_ARROW))) {
                break;
            }

            $this->pos++;
        }

        return self::UNKNOWN;
    }

    /**
     * @return array<array-key, mixed>|string
     */
    private function arrayBody(string $close): array|string
    {
        $result = [];
        $known  = true;

        while (($token = $this->tokens[$this->pos] ?? null) !== null && $token !== $close) {
            $first = $this->expression();

            if ($this->is(T_DOUBLE_ARROW)) {
                $this->pos++;
                $value = $this->expression();

                if (is_int($first) || (is_string($first) && $first !== self::UNKNOWN)) {
                    $result[$first] = $value;
                } else {
                    $known = false;
                }
            } else {
                $result[] = $first;
            }

            if (($this->tokens[$this->pos] ?? null) === ',') {
                $this->pos++;
            } elseif (($this->tokens[$this->pos] ?? null) !== $close) {
                $known = false;
                break;
            }
        }

        $this->pos++; // the closing bracket

        return $known ? $result : self::UNKNOWN;
    }

    /**
     * Skips a method: its parameters, and its body or the `;` of an abstract method.
     */
    private function skipFunction(): void
    {
        $count = count($this->tokens);

        while ($this->pos < $count && $this->tokens[$this->pos] !== '{' && $this->tokens[$this->pos] !== ';') {
            $this->pos++;
        }

        if (($this->tokens[$this->pos] ?? null) === ';') {
            $this->pos++;

            return;
        }

        $depth = 0;

        for (; $this->pos < $count; $this->pos++) {
            $token = $this->tokens[$this->pos];

            if ($token === '{' || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif ($token === '}') {
                $depth--;

                if ($depth === 0) {
                    $this->pos++;

                    return;
                }
            }
        }
    }

    private function stringLiteral(string $raw): string
    {
        $quote = $raw[0];
        $body  = substr($raw, 1, -1);

        if ($quote === "'") {
            return str_replace(['\\\\', "\\'"], ['\\', "'"], $body);
        }

        // Double quotes: interpolation produces T_ENCAPSED tokens, so this one has none.
        return stripcslashes($body);
    }

    private function name(): string
    {
        $name = '';

        while (($token = $this->tokens[$this->pos] ?? null) !== null && is_array($token)
            && in_array($token[0], [T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED, T_NAME_RELATIVE, T_NS_SEPARATOR], true)) {
            $name .= $token[1];
            $this->pos++;
        }

        return $name;
    }

    private function is(int $type): bool
    {
        $token = $this->tokens[$this->pos] ?? null;

        return is_array($token) && $token[0] === $type;
    }

    /**
     * @param array{0: int, 1: string}|string $token
     */
    private function text(array|string $token): string
    {
        return is_array($token) ? $token[1] : $token;
    }
}
