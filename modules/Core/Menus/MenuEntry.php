<?php

declare(strict_types=1);

namespace Modules\Core\Menus;

/**
 * One entry of the generated back-office menu, already filtered for a user.
 *
 * `key` is stable (`<Module>.<resource>` or the route name) so the Menus module (session 21) can
 * customise it; `label` is a language key; `path` (relative to the site URL) is null for a group without its own
 * page.
 */
final class MenuEntry
{
    /**
     * @param list<MenuEntry> $children
     */
    public function __construct(
        public readonly string $key,
        public readonly string $module,
        public readonly string $label,
        public readonly string $icon,
        public readonly ?string $path,
        public readonly ?string $route,
        public readonly int $order,
        public readonly string $zone,
        public array $children = [],
        public bool $active = false,
        public bool $open = false,
    ) {
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /** The absolute URL of the entry, or null for a group. */
    public function url(): ?string
    {
        return $this->path === null ? null : site_url($this->path);
    }

    /** The translated label. */
    public function text(): string
    {
        return lang($this->label);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key'      => $this->key,
            'module'   => $this->module,
            'label'    => $this->label,
            'icon'     => $this->icon,
            'path'     => $this->path,
            'route'    => $this->route,
            'order'    => $this->order,
            'zone'     => $this->zone,
            'children' => array_map(static fn (MenuEntry $e): array => $e->toArray(), $this->children),
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $children = [];

        foreach ((array) ($data['children'] ?? []) as $child) {
            if (is_array($child)) {
                $children[] = self::fromArray($child);
            }
        }

        return new self(
            (string) $data['key'],
            (string) $data['module'],
            (string) $data['label'],
            (string) $data['icon'],
            isset($data['path']) ? (string) $data['path'] : null,
            isset($data['route']) ? (string) $data['route'] : null,
            (int) $data['order'],
            (string) $data['zone'],
            $children,
        );
    }
}
