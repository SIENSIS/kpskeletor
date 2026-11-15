<?php

declare(strict_types=1);

use CodeIgniter\Events\Events;
use Modules\Core\Groups\GroupRegistry;
use Modules\Users\Services\BirthdayAudiences;

/**
 * Event listeners of the Users module (discovered by CodeIgniter).
 *
 * The reserved groups are created by `modules:sync` after the modules' migrations: on a fresh
 * install the birthdays audiences get their defaults then (D-25-08).
 */
Events::on(GroupRegistry::EVENT_RESERVED_CREATED, static function (): void {
    BirthdayAudiences::seedDefaults();
});
