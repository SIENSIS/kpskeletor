<?php

declare(strict_types=1);

namespace Modules\Core\Install;

use CodeIgniter\Database\Config as DatabaseConfig;
use Config\App;
use Config\Database;
use Config\Encryption;
use Config\Services;
use ReflectionProperty;

/**
 * Applies the values the installer is about to write to `.env` to the running process, so the
 * rest of the installation (migrations, the first superadmin, 2FA) uses them before the file
 * exists (the web installer may only show it for the operator to copy).
 *
 * The values become environment variables (what DotEnv would have loaded) and the shared config
 * objects that already read theirs are refreshed: Database (and its open default connection is
 * forgotten), Encryption (and the encrypter service), App's mask key and base URL, and the
 * kpcrudy/kpapy configs that copy the mask key.
 */
class RuntimeConfig
{
    /**
     * @param array<string, string> $values `.env` keys => values
     */
    public function apply(array $values): void
    {
        foreach ($values as $key => $value) {
            if ($key === 'CI_ENVIRONMENT') {
                continue; // The running environment does not change.
            }

            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            service('superglobals')->setServer($key, $value);
        }

        $database          = config(Database::class);
        $database->default = (new Database())->default;
        $this->forgetConnection('default');

        config(Encryption::class)->key = (new Encryption())->key;
        Services::resetSingle('encrypter');

        $app          = config(App::class);
        $app->maskKey = $values['app.maskKey'] ?? $app->maskKey;

        if (($values['app.baseURL'] ?? '') !== '') {
            $app->baseURL = $values['app.baseURL'];
        }

        foreach (['Kpcrudy', 'Kpapy'] as $name) {
            config($name)->maskKey = $app->maskKey;
        }
    }

    /**
     * Closes and forgets the shared connection of a group, so the next `db_connect()` uses the
     * refreshed settings. CodeIgniter keeps them in a protected static array with no reset.
     */
    private function forgetConnection(string $group): void
    {
        $property  = new ReflectionProperty(DatabaseConfig::class, 'instances');
        $instances = $property->getValue();

        if (is_array($instances) && isset($instances[$group])) {
            $instances[$group]->close();
            unset($instances[$group]);
            $property->setValue(null, $instances);
        }
    }
}
