<?php

declare(strict_types=1);

namespace Modules\Menus\Services;

use Modules\Core\Menus\MenuLinkType;
use Modules\Core\Registry\DeclarationRegistry;
use Throwable;

/**
 * The link types offered by the enabled modules (`$menuLinks`, the Core's `MenuLinkType`): a
 * page, a news category… A type of a disabled module is absent, so its items are hidden.
 */
final class LinkTypes
{
    /** @var array<string, MenuLinkType>|null */
    private ?array $types = null;

    public function __construct(private readonly DeclarationRegistry $registry = new DeclarationRegistry())
    {
    }

    /**
     * @return array<string, MenuLinkType> key => type
     */
    public function all(): array
    {
        if ($this->types !== null) {
            return $this->types;
        }

        $this->types = [];

        foreach ($this->registry->menuLinkTypes() as $class) {
            try {
                $type = class_exists($class) ? new $class() : null;
            } catch (Throwable) {
                $type = null;
            }

            if ($type instanceof MenuLinkType) {
                $this->types[$type->key()] = $type;
            }
        }

        return $this->types;
    }

    public function get(string $key): ?MenuLinkType
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * @return array<string, string> key => translated name
     */
    public function labels(): array
    {
        return array_map(static fn (MenuLinkType $type): string => $type->label(), $this->all());
    }
}
