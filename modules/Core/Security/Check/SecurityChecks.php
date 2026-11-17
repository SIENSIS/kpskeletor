<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check;

/**
 * The checks of one `security:check` run. The Core adds its own; other modules add theirs from
 * the `securityChecks` event (checking that they are enabled, like every Core event listener):
 *
 *     Events::on(SecurityChecks::EVENT, static function (SecurityChecks $checks): void {
 *         if ((new ModuleState())->isEnabled('Backup')) {
 *             $checks->add(new BackupChecks());
 *         }
 *     });
 */
final class SecurityChecks
{
    public const EVENT = 'securityChecks';

    /** @var list<SecurityCheck> */
    private array $checks = [];

    public function add(SecurityCheck $check): void
    {
        $this->checks[] = $check;
    }

    /**
     * @return list<SecurityCheck>
     */
    public function all(): array
    {
        return $this->checks;
    }
}
