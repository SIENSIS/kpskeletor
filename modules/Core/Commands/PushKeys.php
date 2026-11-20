<?php

declare(strict_types=1);

namespace Modules\Core\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Core\Push\PushKeyWriter;
use Modules\Core\Push\PushSubscriptions;
use Modules\Core\Push\WebPush;
use Throwable;

/**
 * php spark push:keys [--force] [--yes]
 *
 * Generates the VAPID key pair of the `push` notification channel into `.env`
 * (`push.vapidPublicKey`, `push.vapidPrivateKey`, and `push.subject` when it is missing). With
 * keys already there it stops unless `--force`: rotating the keys removes every push
 * subscription (it asks first, `--yes` skips the question). Never available from the back office.
 */
final class PushKeys extends BaseCommand
{
    protected $group       = 'Application';
    protected $name        = 'push:keys';
    protected $description = 'Generates or rotates the VAPID keys of Web Push notifications (.env).';
    protected $usage       = 'push:keys [--force] [--yes]';
    protected $options     = [
        '--force' => 'Replace existing keys (removes every push subscription)',
        '--yes'   => 'Do not ask for confirmation',
    ];

    /**
     * @param array<int|string, string|null> $params
     *
     * @return int
     */
    public function run(array $params)
    {
        $writer = new PushKeyWriter();

        if (! $writer->envWritable()) {
            CLI::error(lang('Push.cli.envNotWritable'));

            return EXIT_ERROR;
        }

        $exists = WebPush::problem() !== 'missing';
        $force  = ModulesSync::option($params, 'force') !== null;

        if ($exists && ! $force) {
            CLI::write(lang('Push.cli.exists'), 'yellow');

            return EXIT_ERROR;
        }

        if ($exists) {
            try {
                $devices = (new PushSubscriptions())->countAll();
            } catch (Throwable) {
                $devices = 0;
            }

            CLI::write(lang('Push.cli.rotateWarning', [$devices]), 'yellow');

            if (ModulesSync::option($params, 'yes') === null && CLI::prompt(lang('Push.cli.confirm'), ['n', 'y']) !== 'y') {
                CLI::write(lang('Push.cli.cancelled'));

                return EXIT_ERROR;
            }
        }

        $removed = $writer->write(WebPush::defaultSubject((string) (setting('Email.fromEmail') ?? ''), base_url()));

        security_log('Core', 'push_keys', ['summary' => $exists ? 'rotated' : 'generated', 'changes' => ['devices_removed' => $removed]]);
        CLI::write(lang($exists ? 'Push.cli.rotated' : 'Push.cli.generated', [$removed]), 'green');

        return EXIT_SUCCESS;
    }
}
