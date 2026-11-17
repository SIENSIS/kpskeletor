<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check\Checks;

use Config\App;
use Config\Encryption;
use Config\Kpapy;
use Config\Kpcrudy;
use Modules\Core\Config\ProductionCheck;
use Modules\Core\Security\Check\FileAccess;
use Modules\Core\Security\Check\SecretScanner;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;
use Modules\Core\Security\MaskKey;

/**
 * Group `secrets`: the encryption key, `app.maskKey` (the same for kpcrudy and kpapy), `.env`
 * not readable by other users, and no secret in the files git tracks. (`backup.password` is
 * checked by the Backup module.)
 */
final class SecretsCheck implements SecurityCheck
{
    private const GROUP = 'secrets';

    /**
     * @param list<string>|null $findings secrets found in tracked files (tests); default: SecretScanner
     */
    public function __construct(
        private readonly ?string $encryptionKey = null,
        private readonly ?string $maskKey = null,
        private readonly ?string $kpcrudyKey = null,
        private readonly ?string $kpapyKey = null,
        private readonly string $root = ROOTPATH,
        private readonly ?array $findings = null,
        private readonly bool $scanTracked = true,
    ) {
    }

    public function groups(): array
    {
        return [self::GROUP];
    }

    public function run(SecurityReport $report): void
    {
        $this->encryptionKey($report);
        $this->maskKey($report);
        $this->envFile($report);

        if ($this->scanTracked) {
            $this->trackedFiles($report);
        }
    }

    private function encryptionKey(SecurityReport $report): void
    {
        $key = $this->encryptionKey ?? config(Encryption::class)->key;

        if (str_starts_with($key, 'hex2bin:')) {
            $key = (string) @hex2bin(substr($key, 8));
        } elseif (str_starts_with($key, 'base64:')) {
            $key = (string) base64_decode(substr($key, 7), true);
        }

        if ($key === '') {
            $report->error(self::GROUP, 'secrets.encryptionKey', lang('SecurityCheck.encryptionKey.title'), lang('SecurityCheck.encryptionKey.missing'), lang('SecurityCheck.encryptionKey.fix'));
        } elseif (strlen($key) < 32) {
            $report->error(self::GROUP, 'secrets.encryptionKey', lang('SecurityCheck.encryptionKey.title'), lang('SecurityCheck.encryptionKey.short', [strlen($key)]), lang('SecurityCheck.encryptionKey.fix'));
        } else {
            $report->ok(self::GROUP, 'secrets.encryptionKey', lang('SecurityCheck.encryptionKey.title'), lang('SecurityCheck.encryptionKey.ok'));
        }
    }

    private function maskKey(SecurityReport $report): void
    {
        $key     = $this->maskKey ?? config(App::class)->maskKey;
        $problem = MaskKey::problem($key, 'production');

        if ($problem !== null) {
            $report->error(self::GROUP, 'secrets.maskKey', lang('SecurityCheck.maskKey.title'), $problem, lang('SecurityCheck.maskKey.fix'));

            return;
        }

        $kpcrudy = $this->kpcrudyKey ?? config(Kpcrudy::class)->maskKey;
        $kpapy   = $this->kpapyKey ?? config(Kpapy::class)->maskKey;

        if (! hash_equals($key, $kpcrudy) || ! hash_equals($key, $kpapy)) {
            $report->error(self::GROUP, 'secrets.maskKey', lang('SecurityCheck.maskKey.title'), lang('SecurityCheck.maskKey.different'), lang('SecurityCheck.maskKey.fixDifferent'));

            return;
        }

        $report->ok(self::GROUP, 'secrets.maskKey', lang('SecurityCheck.maskKey.title'), lang('SecurityCheck.maskKey.ok'));
    }

    private function envFile(SecurityReport $report): void
    {
        $env = $this->root . '.env';

        if (! is_file($env)) {
            $report->warning(self::GROUP, 'secrets.envFile', lang('SecurityCheck.envFile.title'), lang('SecurityCheck.envFile.missing'), lang('SecurityCheck.envFile.fixMissing'));
        } elseif (FileAccess::readableByOthers($env) || FileAccess::worldWritable($env)) {
            $report->error(self::GROUP, 'secrets.envFile', lang('SecurityCheck.envFile.title'), lang('SecurityCheck.envFile.readable', [sprintf('%04o', FileAccess::mode($env))]), lang('SecurityCheck.envFile.fix'));
        } else {
            $report->ok(self::GROUP, 'secrets.envFile', lang('SecurityCheck.envFile.title'), lang('SecurityCheck.envFile.ok', [sprintf('%04o', FileAccess::mode($env))]));
        }
    }

    private function trackedFiles(SecurityReport $report): void
    {
        $findings = $this->findings ?? (new SecretScanner($this->root, config(ProductionCheck::class)->maxScannedFiles))->scan();

        if ($findings === null) {
            $report->info(self::GROUP, 'secrets.trackedFiles', lang('SecurityCheck.trackedFiles.title'), lang('SecurityCheck.trackedFiles.noGit'), lang('SecurityCheck.trackedFiles.fixNoGit'));
        } elseif ($findings !== []) {
            $report->error(self::GROUP, 'secrets.trackedFiles', lang('SecurityCheck.trackedFiles.title'), lang('SecurityCheck.trackedFiles.found', [implode(', ', array_slice($findings, 0, 10)) . (count($findings) > 10 ? ' …' : '')]), lang('SecurityCheck.trackedFiles.fix'));
        } else {
            $report->ok(self::GROUP, 'secrets.trackedFiles', lang('SecurityCheck.trackedFiles.title'), lang('SecurityCheck.trackedFiles.ok'));
        }
    }
}
