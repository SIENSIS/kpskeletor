<?php

declare(strict_types=1);

namespace Modules\Devtools\Generator;

use Modules\Core\Generator\Definition\ModuleDefinition;

/**
 * The wizard's definition between requests, in the user's session (one draft per session): every
 * button of the form saves it, the preview and the write read it, so the re-authentication
 * round trip comes back to the same preview.
 */
final class Draft
{
    private const KEY = 'devtools.generator';

    public static function get(): ModuleDefinition
    {
        $json = session(self::KEY);

        if (is_string($json)) {
            try {
                return ModuleDefinition::fromJson($json);
            } catch (\InvalidArgumentException) {
                // A broken draft starts again.
            }
        }

        return self::blank();
    }

    public static function put(ModuleDefinition $definition): void
    {
        session()->set(self::KEY, $definition->toJson());
    }

    public static function clear(): void
    {
        session()->remove(self::KEY);
    }

    public static function blank(): ModuleDefinition
    {
        return ModuleDefinition::fromArray(['module' => ['name' => ''], 'tables' => []]);
    }
}
