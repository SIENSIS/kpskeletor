<?php

declare(strict_types=1);

namespace Modules\Core\Menus;

/**
 * One item of a public menu, already filtered for the visitor and translated (`menu()` helper,
 * theme partial `partials/public_menu`). `url` is absolute (null for a heading without a link);
 * external links open with `rel="noopener noreferrer"` when `target` is `_blank`.
 */
final class PublicMenuItem
{
    /**
     * @param list<PublicMenuItem> $children
     */
    public function __construct(
        public readonly int $id,
        public readonly string $label,
        public readonly ?string $url,
        public readonly string $icon = '',
        public readonly string $target = '_self',
        public readonly string $cssClass = '',
        public array $children = [],
        public bool $active = false,
        public bool $open = false,
    ) {
    }

    public function hasChildren(): bool
    {
        return $this->children !== [];
    }

    public function newTab(): bool
    {
        return $this->target === '_blank';
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id'       => $this->id,
            'label'    => $this->label,
            'url'      => $this->url,
            'icon'     => $this->icon,
            'target'   => $this->target,
            'cssClass' => $this->cssClass,
            'children' => array_map(static fn (PublicMenuItem $i): array => $i->toArray(), $this->children),
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
            (int) $data['id'],
            (string) $data['label'],
            isset($data['url']) ? (string) $data['url'] : null,
            (string) ($data['icon'] ?? ''),
            (string) ($data['target'] ?? '_self'),
            (string) ($data['cssClass'] ?? ''),
            $children,
        );
    }
}
