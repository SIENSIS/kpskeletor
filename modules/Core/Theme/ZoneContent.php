<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

/**
 * What modules put in one theme zone for the current request (D-47-01; docs/themes.md,
 * "Zones"): listeners of the Core event `themeZone` receive it, check `$zone` and `add()` their
 * already-escaped HTML with an order. The layout prints it with `theme_zone('<zone>')`.
 */
final class ZoneContent
{
    /** @var list<array{html: string, order: int, seq: int}> */
    private array $parts = [];

    public function __construct(public readonly string $zone)
    {
    }

    /**
     * @param string $html already escaped HTML (usually a rendered view)
     */
    public function add(string $html, int $order = 100): void
    {
        if (trim($html) !== '') {
            $this->parts[] = ['html' => $html, 'order' => $order, 'seq' => count($this->parts)];
        }
    }

    public function isEmpty(): bool
    {
        return $this->parts === [];
    }

    public function render(): string
    {
        $parts = $this->parts;
        usort($parts, static fn (array $a, array $b): int => [$a['order'], $a['seq']] <=> [$b['order'], $b['seq']]);

        return implode("\n", array_column($parts, 'html'));
    }
}
