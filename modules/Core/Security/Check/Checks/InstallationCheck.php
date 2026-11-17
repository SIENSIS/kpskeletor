<?php

declare(strict_types=1);

namespace Modules\Core\Security\Check\Checks;

use FilesystemIterator;
use Modules\Core\Config\ProductionCheck;
use Modules\Core\Demo\DemoDataManager;
use Modules\Core\Security\Check\FileAccess;
use Modules\Core\Security\Check\SecurityCheck;
use Modules\Core\Security\Check\SecurityReport;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Throwable;

/**
 * Group `installation`: the web installer is closed (`writable/install.lock`), no demo data,
 * `writable/` modes (`0750` folders, `0640` files or stricter; `writable/maintenance.json` never
 * readable by others), what the web server user may write, and `public/` holding nothing but
 * `index.php` as PHP (no `.env`, no `writable`).
 */
final class InstallationCheck implements SecurityCheck
{
    private const GROUP = 'installation';

    /** Script extensions a web server may execute. */
    private const SCRIPTS = ['php', 'phtml', 'phar', 'php3', 'php4', 'php5', 'php7', 'php8', 'phps', 'pht', 'cgi', 'pl', 'py', 'sh'];

    /**
     * @param (\Closure(): list<string>)|null $demoModules modules with demo data loaded (tests)
     */
    public function __construct(
        private readonly string $root = ROOTPATH,
        private readonly string $writable = WRITEPATH,
        private readonly string $public = FCPATH,
        private readonly ?ProductionCheck $config = null,
        private readonly ?FileAccess $access = null,
        private readonly ?\Closure $demoModules = null,
    ) {
    }

    public function groups(): array
    {
        return [self::GROUP];
    }

    public function run(SecurityReport $report): void
    {
        $config = $this->config ?? config(ProductionCheck::class);

        $this->installLock($report);
        $this->demoData($report);
        $this->writableModes($report, $config);
        $this->webWritable($report, $config);
        $this->publicFolder($report);
    }

    private function installLock(SecurityReport $report): void
    {
        if (is_file(rtrim($this->writable, '/') . '/install.lock')) {
            $report->ok(self::GROUP, 'installation.lock', lang('SecurityCheck.lock.title'), lang('SecurityCheck.lock.ok'));
        } else {
            $report->error(self::GROUP, 'installation.lock', lang('SecurityCheck.lock.title'), lang('SecurityCheck.lock.missing'), lang('SecurityCheck.lock.fix'));
        }
    }

    private function demoData(SecurityReport $report): void
    {
        try {
            $modules = $this->demoModules !== null ? ($this->demoModules)() : (new DemoDataManager())->loadedModules();
        } catch (Throwable) {
            $report->info(self::GROUP, 'installation.demo', lang('SecurityCheck.demo.title'), lang('SecurityCheck.demo.unknown'));

            return;
        }

        if ($modules === []) {
            $report->ok(self::GROUP, 'installation.demo', lang('SecurityCheck.demo.title'), lang('SecurityCheck.demo.ok'));
        } else {
            $report->error(self::GROUP, 'installation.demo', lang('SecurityCheck.demo.title'), lang('SecurityCheck.demo.loaded', [implode(', ', $modules)]), lang('SecurityCheck.demo.fix'));
        }
    }

    private function writableModes(SecurityReport $report, ProductionCheck $config): void
    {
        $base  = rtrim($this->writable, '/');
        $loose = [];
        $count = 0;

        if (FileAccess::looserThan($base, 0750)) {
            $loose[] = 'writable/ (' . sprintf('%04o', FileAccess::mode($base)) . ')';
        }

        try {
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);

            foreach ($items as $item) {
                if (++$count > $config->maxScannedFiles) {
                    break;
                }

                if ($item->isLink()) {
                    continue;
                }

                $path = $item->getPathname();

                if (FileAccess::looserThan($path, $item->isDir() ? 0750 : 0640)) {
                    $loose[] = 'writable/' . ltrim(substr($path, strlen($base)), '/') . ' (' . sprintf('%04o', FileAccess::mode($path)) . ')';
                }
            }
        } catch (Throwable) {
            // unreadable folder: reported by the modes of what could be read
        }

        $maintenance = $base . '/maintenance.json';

        if (is_file($maintenance) && FileAccess::readableByOthers($maintenance)) {
            $report->error(self::GROUP, 'installation.maintenanceFile', lang('SecurityCheck.maintenanceFile.title'), lang('SecurityCheck.maintenanceFile.readable'), lang('SecurityCheck.writableModes.fix'));
        }

        if ($loose === []) {
            $report->ok(self::GROUP, 'installation.writableModes', lang('SecurityCheck.writableModes.title'), lang('SecurityCheck.writableModes.ok'));
        } else {
            $report->warning(self::GROUP, 'installation.writableModes', lang('SecurityCheck.writableModes.title'), lang('SecurityCheck.writableModes.loose', [count($loose), implode(', ', array_slice($loose, 0, 8)) . (count($loose) > 8 ? ' …' : '')]), lang('SecurityCheck.writableModes.fix'));
        }
    }

    private function webWritable(SecurityReport $report, ProductionCheck $config): void
    {
        $access   = $this->access ?? new FileAccess($config->webUser);
        $root     = rtrim($this->root, '/') . '/';
        $writable = [];

        foreach ($config->readOnlyPaths as $relative) {
            $path = $root . $relative;

            if (! file_exists($path)) {
                continue;
            }

            if ($access->writableByWebUser($path) === true) {
                $writable[] = $relative === '' ? lang('SecurityCheck.webWritable.root') : $relative;
            }
        }

        if ($writable !== []) {
            $report->error(self::GROUP, 'installation.webWritable', lang('SecurityCheck.webWritable.title'), lang('SecurityCheck.webWritable.error', [implode(', ', $writable)]), lang('SecurityCheck.webWritable.fix', [implode(', ', $config->writablePaths)]));
        } elseif (! $access->knowsWebUser()) {
            $report->warning(self::GROUP, 'installation.webWritable', lang('SecurityCheck.webWritable.title'), lang('SecurityCheck.webWritable.unknown'), lang('SecurityCheck.webWritable.fixUnknown'));
        } else {
            $report->ok(self::GROUP, 'installation.webWritable', lang('SecurityCheck.webWritable.title'), lang('SecurityCheck.webWritable.ok', [implode(', ', $config->writablePaths)]));
        }
    }

    private function publicFolder(SecurityReport $report): void
    {
        $base  = rtrim($this->public, '/');
        $found = [];

        foreach (['.env', 'writable', '.git'] as $name) {
            if (file_exists($base . '/' . $name)) {
                $found[] = $name;
            }
        }

        try {
            $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));

            foreach ($items as $item) {
                $relative = ltrim(substr($item->getPathname(), strlen($base)), '/');

                if ($relative !== 'index.php' && in_array(strtolower($item->getExtension()), self::SCRIPTS, true)) {
                    $found[] = $relative;
                }

                if (count($found) > 20) {
                    break;
                }
            }
        } catch (Throwable) {
            // unreadable
        }

        if ($found === []) {
            $report->ok(self::GROUP, 'installation.public', lang('SecurityCheck.public.title'), lang('SecurityCheck.public.ok'));
        } else {
            $report->error(self::GROUP, 'installation.public', lang('SecurityCheck.public.title'), lang('SecurityCheck.public.error', [implode(', ', array_slice($found, 0, 10))]), lang('SecurityCheck.public.fix'));
        }
    }
}
