<?php

declare(strict_types=1);

namespace Modules\Updates\Services;

use Closure;
use CodeIgniter\I18n\Time;
use Modules\Core\Config\Version;
use Modules\Core\Modules\ModuleDiscovery;
use Modules\Core\Registry\ModuleState;
use Modules\Updates\Config\Updates as UpdatesConfig;
use Modules\Updates\Notifications\NewVersion;
use Throwable;

/**
 * Checks for new versions (scheduled task, "check now" button, `php spark updates:check`;
 * never on a regular request): the application (`Config\Updates::$repository`, installed
 * version `Version::CORE`) and every enabled module that declares `$repository` in its manifest
 * (installed version: the manifest's). The result is written to UpdateStatus; each new version is
 * notified once to the holders of `updates.view` (NewVersion). Network errors are logged by the
 * OutboundClient and kept in the status, never thrown to the caller.
 */
final class ReleaseChecker
{
    private readonly UpdatesConfig $config;

    /** @var Closure(string): ReleaseSource */
    private readonly Closure $sources;

    /** @var Closure(): array<string, array{version: string, repository: string}> */
    private readonly Closure $modules;

    /**
     * @param (Closure(string): ReleaseSource)|null                                     $sources repository => its source
     * @param (Closure(): array<string, array{version: string, repository: string}>)|null $modules enabled modules with a repository
     */
    public function __construct(?UpdatesConfig $config = null, ?Closure $sources = null, ?Closure $modules = null, private readonly ?UpdateStatus $status = null)
    {
        $this->config  = $config ?? config(UpdatesConfig::class);
        $this->sources = $sources ?? fn (string $repository): ReleaseSource => new ReleaseSource($repository, $this->config->token);
        $this->modules = $modules ?? self::enabledModules(...);
    }

    public function status(): UpdateStatus
    {
        return $this->status ?? new UpdateStatus($this->config);
    }

    /**
     * Runs the check, stores and returns the status.
     *
     * @return array<string, mixed>
     */
    public function check(bool $notify = true): array
    {
        $previous = $this->status()->read();
        $status   = [
            'checkedAt' => Time::now('UTC')->format('Y-m-d H:i:s'),
            'error'     => null,
            'core'      => $this->one(trim($this->config->repository), Version::CORE),
            'modules'   => [],
            'notified'  => array_values(array_filter(is_array($previous['notified'] ?? null) ? $previous['notified'] : [], 'is_string')),
        ];

        foreach (($this->modules)() as $name => $module) {
            $status['modules'][$name] = $this->one($module['repository'], $module['version']);
        }

        $errors = array_filter(array_merge([$status['core']['error']], array_column($status['modules'], 'error')));

        if ($errors !== []) {
            $status['error'] = (string) reset($errors);
        }

        if ($notify) {
            $status['notified'] = $this->notify($status);
        }

        $this->status()->write($status);
        activity_log('Updates', 'checked', ['resource' => 'updates', 'summary' => $this->summary($status)]);

        return $status;
    }

    /**
     * Names with a newer version in a status (`Core` for the application).
     *
     * @param array<string, mixed> $status
     *
     * @return array<string, string> name => latest version
     */
    public static function newer(array $status): array
    {
        $newer = [];
        $all   = ['Core' => $status['core'] ?? null] + (is_array($status['modules'] ?? null) ? $status['modules'] : []);

        foreach ($all as $name => $item) {
            if (is_array($item) && ($item['newer'] ?? false) === true && is_array($item['latest'] ?? null)) {
                $newer[(string) $name] = (string) ($item['latest']['version'] ?? '');
            }
        }

        return $newer;
    }

    /**
     * @return array{installed: string, repository: string, latest: array<string, mixed>|null, newer: bool, error: string|null}
     */
    private function one(string $repository, string $installed): array
    {
        $result = ['installed' => $installed, 'repository' => $repository, 'latest' => null, 'newer' => false, 'error' => null];

        if ($repository === '') {
            return $result;
        }

        try {
            $latest = ($this->sources)($repository)->latest($this->config->prereleases);
        } catch (UpdateException $e) {
            $result['error'] = $e->getMessage();

            return $result;
        } catch (Throwable $e) {
            $result['error'] = lang('Updates.error.network', [$e->getMessage()]);

            return $result;
        }

        if ($latest !== null) {
            $result['latest'] = $latest->toArray();
            $result['newer']  = VersionComparator::normalize($installed) !== null && VersionComparator::isNewer($latest->version, $installed);
        }

        return $result;
    }

    /**
     * Notifies each new version once.
     *
     * @param array<string, mixed> $status
     *
     * @return list<string> the notified keys
     */
    private function notify(array $status): array
    {
        /** @var list<string> $notified */
        $notified = $status['notified'];

        foreach (self::newer($status) as $name => $version) {
            $key = $name . '@' . $version;

            if (in_array($key, $notified, true)) {
                continue;
            }

            $installed = $name === 'Core' ? (string) $status['core']['installed'] : (string) ($status['modules'][$name]['installed'] ?? '');

            try {
                notifyPermission('updates.view', new NewVersion(['name' => $name, 'version' => $version, 'installed' => $installed]));
            } catch (Throwable $e) {
                activity_log('Updates', 'notify_failed', ['resource' => 'updates', 'summary' => $e->getMessage()]);

                continue;
            }

            $notified[] = $key;
        }

        return array_slice($notified, -200);
    }

    /**
     * @param array<string, mixed> $status
     */
    private function summary(array $status): string
    {
        $newer = self::newer($status);

        if ($status['error'] !== null) {
            return (string) $status['error'];
        }

        return $newer === [] ? lang('Updates.msg.upToDate') : implode(', ', array_map(static fn (string $name, string $version): string => $name . ' ' . $version, array_keys($newer), $newer));
    }

    /**
     * @return array<string, array{version: string, repository: string}>
     */
    private static function enabledModules(): array
    {
        $state   = new ModuleState();
        $modules = [];

        foreach ((new ModuleDiscovery())->discover() as $module) {
            $manifest = $module->manifest;

            if ($manifest === null || $manifest->repository === null || trim($manifest->repository) === '' || ! $state->isEnabled($module->name)) {
                continue;
            }

            $modules[$module->name] = ['version' => $manifest->version, 'repository' => trim($manifest->repository)];
        }

        ksort($modules);

        return $modules;
    }
}
