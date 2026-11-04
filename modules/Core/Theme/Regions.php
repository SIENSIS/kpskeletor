<?php

declare(strict_types=1);

namespace Modules\Core\Theme;

/**
 * Content of the layout regions for the current request (header, sidebar, footer, home blocks…).
 *
 * Controllers and modules add already-escaped HTML to a region; the layout prints the regions the
 * active theme declares for it (`theme_region('sidebar')`). Content for a region the theme does
 * not declare is not printed.
 */
final class Regions
{
    /** @var array<string, list<array{html: string, order: int}>> */
    private array $content = [];

    /**
     * @param string $html already escaped HTML (usually a rendered view)
     */
    public function add(string $region, string $html, int $order = 100): void
    {
        $this->content[$region][] = ['html' => $html, 'order' => $order];
    }

    public function has(string $region): bool
    {
        return ($this->content[$region] ?? []) !== [];
    }

    public function render(string $region): string
    {
        $blocks = $this->content[$region] ?? [];
        usort($blocks, static fn (array $a, array $b): int => $a['order'] <=> $b['order']);

        return implode("\n", array_column($blocks, 'html'));
    }

    public function clear(): void
    {
        $this->content = [];
    }
}
