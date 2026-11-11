<?php

declare(strict_types=1);

namespace Modules\Core\Menus;

/**
 * One entry of the generated back-office menu, already filtered for a user.
 *
 * `key` is stable (`<Module>.<resource>` or the route name) so the Menus module can customise it;
 * `label` is a language key, replaced by `labels` (locale => text) when the entry was renamed;
 * `path` (relative to the site URL) is null for a group without its own page; `href` is the
 * absolute URL of an external custom link (then `path` is null and the entry is never active).
 */
final class MenuEntry
{
    /**
     * @param list<MenuEntry>       $children
     * @param array<string, string> $labels   locale => text (a renamed entry)
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
        public readonly array $labels = [],
        public readonly ?string $href = null,
        public readonly string $target = '_self',
    ) {
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    /** The absolute URL of the entry, or null for a group. */
    public function url(): ?string
    {
        if ($this->href !== null) {
            return $this->href;
        }

        return $this->path === null ? null : site_url($this->path);
    }

    /** True when the entry opens in a new tab (then with `rel="noopener noreferrer"`). */
    public function newTab(): bool
    {
        return $this->target === '_blank';
    }

    /** The translated label: the text of a renamed entry in the current language, else the language key's. */
    public function text(): string
    {
        if ($this->labels !== []) {
            $labels = $this->labels;
            $locale = service('request')->getLocale();
            $text   = $labels[$locale] ?? $labels[config('App')->defaultLocale] ?? reset($labels);

            if ($text !== '') {
                return $text;
            }
        }

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
            'labels'   => $this->labels,
            'href'     => $this->href,
            'target'   => $this->target,
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
            false,
            false,
            array_map('strval', (array) ($data['labels'] ?? [])),
            isset($data['href']) ? (string) $data['href'] : null,
            (string) ($data['target'] ?? '_self'),
        );
    }
}
