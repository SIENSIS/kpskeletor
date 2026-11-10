<?php

declare(strict_types=1);

namespace Modules\Auth\Database\Seeds;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Models\UserModel;
use Modules\Auth\Models\UserProfileModel;
use Modules\Core\Demo\DemoData;
use Modules\Core\Demo\DemoSeeder;

/**
 * Demo users of the Auth module, one per group, loaded with `php spark modules:demo Auth`.
 * Each one gets a random password, shown once by whoever loaded the demo data (never a fixed
 * password; remove them with `php spark modules:demo Auth --remove`). Deleting a user removes
 * its identities, groups and profile through the foreign keys.
 */
final class AuthDemoSeeder extends DemoSeeder
{
    /** @var list<array{username: string, email: string, name: string, group: string}> */
    private const USERS = [
        ['username' => 'superadmin', 'email' => 'superadmin@example.com', 'name' => 'Montserrat Puig', 'group' => 'superadmin'],
        ['username' => 'admin', 'email' => 'admin@example.com', 'name' => 'Jordi Vidal', 'group' => 'admin'],
        ['username' => 'editor', 'email' => 'editor@example.com', 'name' => 'Laia Ferrer', 'group' => 'editor'],
        ['username' => 'usuari', 'email' => 'usuari@example.com', 'name' => 'Pau Soler', 'group' => 'user'],
    ];

    public function load(DemoData $demo): void
    {
        $users    = model(UserModel::class);
        $profiles = model(UserProfileModel::class);

        foreach (self::USERS as $row) {
            // An existing account with the same email or username (e.g. the superadmin created by
            // the installer) is kept and its demo user skipped.
            if ($users->findByCredentials(['email' => $row['email']]) !== null || $users->where('username', $row['username'])->countAllResults() > 0) {
                continue;
            }

            $password = DemoData::randomPassword();
            $user     = new User(['username' => $row['username'], 'email' => $row['email'], 'password' => $password, 'active' => 1]);
            $users->save($user);
            $demo->secret($row['email'], $password);

            $id   = (int) $users->getInsertID();
            $user = $users->findById($id);

            if ($user === null) {
                continue;
            }

            $demo->record('users', $id);
            $user->addGroup($row['group']);

            $profiles->insert([
                'user_id'    => $id,
                'name'       => $row['name'],
                'locale'     => 'ca',
                'timezone'   => 'Europe/Madrid',
                'theme_mode' => 'auto',
            ]);
            $demo->record('user_profiles', (int) $profiles->getInsertID());
        }
    }
}
