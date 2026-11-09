<?php

declare(strict_types=1);

namespace Modules\Users\Tests;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\I18n\Time;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Kpcrudy\Crud;
use Modules\Settings\Tests\SettingsTestCase;

/**
 * Base of the Users tests: the Auth fixture (migrated database, catalogue, grants, the reserved
 * groups, requests with session and CSRF) plus the manifests' declarations (settings defaults),
 * a fixed clock and helpers for masked ids and fresh user entities.
 */
abstract class UsersTestCase extends SettingsTestCase
{
    protected const NOW = 1_800_000_000;

    protected function setUp(): void
    {
        parent::setUp();
        Time::setTestNow(Time::createFromTimestamp(self::NOW));
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    /**
     * The masked id of a user in the CRUD's URLs.
     */
    protected function code(int|string $id, string $table = 'users'): string
    {
        return Crud::maskId((int) $id, $table);
    }

    protected function fresh(User $user): User
    {
        $fresh = model(UserModel::class)->findById($user->id);
        $this->assertInstanceOf(User::class, $fresh);

        return $fresh;
    }

    /**
     * Whether a request answers "not found" (the feature tests get the exception).
     */
    protected function assertNotFound(string $method, string $path): void
    {
        try {
            $this->send($method, $path)->assertStatus(404);
        } catch (PageNotFoundException) {
            $this->addToAssertionCount(1);
        }
    }

    protected function later(int $seconds): void
    {
        Time::setTestNow(Time::createFromTimestamp(self::NOW + $seconds));
    }
}
