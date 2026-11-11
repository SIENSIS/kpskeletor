<?php

declare(strict_types=1);

namespace Modules\Files\Policies;

use CodeIgniter\Events\Events;
use Modules\Core\Registry\ModuleState;
use Modules\Core\Storage\PathNormalizer;
use Modules\Core\Storage\StorageException;

/**
 * The `FileAccessPolicy`s of the modules, collected once per request through the event
 * `filesAccessPolicies`. Only folders inside `modules/` can have a policy, and only policies
 * of enabled modules count. The policy of the longest matching folder wins.
 */
final class PolicyRegistry
{
    public const EVENT = 'filesAccessPolicies';

    /** @var array<string, FileAccessPolicy>|null */
    private static ?array $shared = null;

    /** @var array<string, FileAccessPolicy> */
    private array $policies = [];

    public static function instance(): self
    {
        $registry = new self();

        if (self::$shared === null) {
            Events::trigger(self::EVENT, $registry);
            self::$shared = $registry->policies;
        }

        $registry->policies = self::$shared;

        return $registry;
    }

    /** Forgets the collected policies (tests). */
    public static function reset(): void
    {
        self::$shared = null;
    }

    public function register(FileAccessPolicy $policy): void
    {
        try {
            $folder = PathNormalizer::normalize($policy->folder());
        } catch (StorageException) {
            return;
        }

        $module = ModuleState::moduleOf($policy::class);

        if (! str_starts_with($folder . '/', 'modules/') || $folder === 'modules'
            || ($module !== null && ! (new ModuleState())->isEnabled($module))) {
            return;
        }

        $this->policies[$folder] = $policy;
    }

    public function for(string $path): ?FileAccessPolicy
    {
        $best = null;
        $size = 0;

        foreach ($this->policies as $folder => $policy) {
            if (($path === $folder || str_starts_with($path, $folder . '/')) && strlen($folder) > $size) {
                $best = $policy;
                $size = strlen($folder);
            }
        }

        return $best;
    }

    /**
     * @return array<string, FileAccessPolicy>
     */
    public function all(): array
    {
        return $this->policies;
    }
}
