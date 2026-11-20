<?php

declare(strict_types=1);

namespace Modules\Core\Generator;

/**
 * PHP literals for generated code: short arrays, single quotes, lists and short maps on one line,
 * longer maps one item per line indented from `$indent`.
 */
final class PhpExport
{
    public const WIDTH = 110;

    public static function value(mixed $value, string $indent = ''): string
    {
        return match (true) {
            $value === null      => 'null',
            is_bool($value)      => $value ? 'true' : 'false',
            is_int($value), is_float($value) => (string) $value,
            is_string($value)    => self::string($value),
            is_array($value)     => self::array($value, $indent),
            $value instanceof Code => $value->code,
            default              => throw new \InvalidArgumentException('Unsupported value.'),
        };
    }

    public static function string(string $value): string
    {
        // A backslash needs escaping only before a quote, another backslash or the end.
        $value = (string) preg_replace('/\\\\(?=[\\\\\']|$)/', '\\\\\\\\', $value);

        return "'" . str_replace("'", "\\'", $value) . "'";
    }

    /**
     * @param array<mixed> $value
     */
    public static function array(array $value, string $indent = ''): string
    {
        if ($value === []) {
            return '[]';
        }

        $list  = array_is_list($value);
        $items = [];

        foreach ($value as $key => $item) {
            $items[] = ($list ? '' : self::value($key) . ' => ') . self::value($item, $indent . '    ');
        }

        $inline = '[' . implode(', ', $items) . ']';

        if (! str_contains($inline, "\n") && strlen($indent) + strlen($inline) <= self::WIDTH) {
            return $inline;
        }

        return "[\n" . implode('', array_map(static fn (string $item): string => $indent . '    ' . $item . ",\n", $items)) . $indent . ']';
    }

    /**
     * A key => value item of an array (no trailing comma).
     */
    public static function item(string|int|null $key, mixed $value, string $indent = ''): string
    {
        return ($key === null ? '' : self::value($key) . ' => ') . self::value($value, $indent);
    }
}
