<?php

declare(strict_types=1);

namespace Modules\Core\Generator\Definition;

/**
 * The back-office menu entry of a generated CRUD (docs/menus.md): zone `sidebar` (under a parent
 * resource of the module, `main` by default, or at the root) or `settings` (in one of the Core's
 * groups, D-38-01), icon and order. `enabled = false` declares no entry.
 */
final class MenuDefinition
{
    public const ZONES = ['sidebar', 'settings'];

    public const SETTINGS_GROUPS = ['group.access', 'group.security', 'group.api', 'group.customisation', 'group.system'];

    public function __construct(
        public readonly bool $enabled = true,
        public readonly string $zone = 'sidebar',
        public readonly ?string $parent = 'main',
        public readonly string $icon = 'bi bi-table',
        public readonly int $order = 10,
    ) {
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function fromArray(?array $data): self
    {
        $data ??= [];
        $zone   = is_string($data['zone'] ?? null) ? $data['zone'] : 'sidebar';
        $parent = array_key_exists('parent', $data) ? $data['parent'] : ($zone === 'settings' ? 'group.system' : 'main');

        return new self(
            enabled: ! array_key_exists('enabled', $data) || ColumnDefinition::bool($data['enabled']),
            zone: $zone,
            parent: is_string($parent) && trim($parent) !== '' ? trim($parent) : null,
            icon: is_string($data['icon'] ?? null) && trim($data['icon']) !== '' ? trim($data['icon']) : 'bi bi-table',
            order: ColumnDefinition::int($data['order'] ?? null) ?? 10,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['enabled' => $this->enabled, 'zone' => $this->zone, 'parent' => $this->parent, 'icon' => $this->icon, 'order' => $this->order];
    }
}
