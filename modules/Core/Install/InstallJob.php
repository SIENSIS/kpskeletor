<?php

declare(strict_types=1);

namespace Modules\Core\Install;

use Closure;
use Modules\Core\Security\Check\SecurityChecker;
use Modules\Core\Security\Check\SecurityStatus;
use Throwable;

/**
 * Runs a web installation as a job (session 39): after the response when the server can finish
 * it first (PHP-FPM / FastCGI or LiteSpeed, like the Backup module's RestoreJob), so the page
 * can show the progress; `InstallJobs` keeps the steps, the log and the result.
 *
 * Implements the installer's listener: each step moves the percentage within its range
 * (`RANGES`); the passwords live only in this process's memory.
 */
final class InstallJob implements InstallProgress
{
    /**
     * Step code => [first, last] percentage.
     *
     * @var array<string, array{int, int}>
     */
    public const RANGES = [
        'queued'        => [0, 0],
        'checks'        => [1, 2],
        'env'           => [3, 4],
        'database'      => [5, 6],
        'migrations'    => [7, 50],
        'modules'       => [51, 58],
        'permissions'   => [59, 61],
        'settings'      => [62, 63],
        'assets'        => [64, 67],
        'groups'        => [68, 69],
        'api'           => [70, 71],
        'superadmin'    => [72, 74],
        'demo'          => [75, 90],
        'envFile'       => [91, 92],
        'lock'          => [93, 94],
        'securityCheck' => [95, 99],
        'done'          => [100, 100],
    ];

    private string $id = '';

    /**
     * @param (Closure(string): SecurityChecker)|null $checker The security check of the installed environment (tests)
     */
    public function __construct(
        private readonly InstallJobs $jobs,
        private readonly Installer $installer,
        private readonly ?Closure $checker = null,
    ) {
    }

    /**
     * Whether this server can send the response before the job runs. Not with the console
     * server (`php spark serve`), mod_php or in tests: the installation then runs inside the
     * request (the fallback).
     */
    public static function canRunAfterResponse(): bool
    {
        if (ENVIRONMENT === 'testing' || PHP_SAPI === 'cli' || PHP_SAPI === 'cli-server') {
            return false;
        }

        return function_exists('fastcgi_finish_request') || function_exists('litespeed_finish_request');
    }

    /**
     * Runs `$job` after the response has been sent (the caller has checked canRunAfterResponse()).
     *
     * @param Closure(): void $job
     */
    public static function afterResponse(Closure $job): void
    {
        register_shutdown_function(static function () use ($job): void {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            } elseif (function_exists('litespeed_finish_request')) {
                litespeed_finish_request();
            }

            ignore_user_abort(true);
            @set_time_limit(0);
            $job();
        });
    }

    /**
     * Installs, records the result in the job and releases the installation lock (held by `$id`).
     *
     * @param array{loginUrl: string, username: string} $page Values of the result page
     */
    public function run(string $id, InstallOptions $options, array $page): ?InstallResult
    {
        $this->id = $id;
        $this->jobs->update($id, ['status' => 'running']);

        // Messages (e.g. the failure) in the language of the page that started the job.
        $locale = (string) ($this->jobs->get($id)['locale'] ?? '');

        if (in_array($locale, config('App')->supportedLocales, true)) {
            service('language')->setLocale($locale);
        }

        try {
            $result = $this->installer->install($options, null, false, $this);
        } catch (Throwable $e) {
            if (! $e instanceof InstallException) {
                log_message('error', 'Web installation failed: {exception}', ['exception' => $e]);
            }

            $this->jobs->update($id, ['errors' => $e instanceof InstallException ? $e->errors : []]);
            $this->jobs->unlock($id);

            return null;
        }

        [$errors, $warnings] = $this->securityCheck($options->environment);

        $oneTime = ! $result->envWritten || $result->demoSecrets !== [];

        if ($oneTime) {
            $this->jobs->putSecrets($id, [
                'env'  => $result->envWritten ? '' : $result->env,
                'demo' => $result->demoSecrets,
            ]);
        }

        $this->step('done');
        $this->jobs->update($id, [
            'status' => 'done',
            'result' => [
                'envWritten' => $result->envWritten,
                'oneTime'    => $oneTime,
                'demoLoaded' => $result->demoLoaded,
                'demoError'  => $result->demoError,
                'cronLine'   => $result->cronLine,
                'loginUrl'   => $page['loginUrl'],
                'username'   => $page['username'],
                'security'   => $errors === null ? null : ['errors' => $errors, 'warnings' => $warnings],
            ],
        ]);
        $this->jobs->unlock($id);

        return $result;
    }

    public function step(string $code, int $done = 0, int $total = 0, array $params = []): void
    {
        $this->jobs->log($this->id, 'Install.step.' . $code, ['done' => $done, 'total' => $total] + $params, [
            'step'    => $code,
            'done'    => $done,
            'total'   => $total,
            'params'  => $params,
            'percent' => self::percent($code, $done, $total),
        ]);
    }

    public function log(string $key, array $params = []): void
    {
        $this->jobs->log($this->id, $key, $params);
    }

    public function failed(string $code, string $message): void
    {
        $this->jobs->log($this->id, 'Install.progress.failed', [], [
            'status'  => 'failed',
            'step'    => $code,
            'message' => $message,
        ]);
    }

    /**
     * The percentage of a step: its range, moved by done/total for the steps that repeat.
     */
    public static function percent(string $code, int $done = 0, int $total = 0): int
    {
        [$first, $last] = self::RANGES[$code] ?? [0, 0];

        if ($total < 1) {
            return $first;
        }

        return $first + (int) floor(($last - $first) * max(0, min($done, $total)) / $total);
    }

    /**
     * `security:check` of the installed environment, stored for the Security status page
     * without notifying anybody (as `app:install` does).
     *
     * @return array{int|null, int}
     */
    private function securityCheck(string $environment): array
    {
        $this->step('securityCheck');

        try {
            $checker = $this->checker !== null ? ($this->checker)($environment) : new SecurityChecker(SecurityChecker::defaultChecks($environment));
            $report  = $checker->run();
            (new SecurityStatus())->store($report, false);
            $counts = $report->counts();
        } catch (Throwable $e) {
            log_message('warning', 'Security check after the web installation failed: {message}', ['message' => $e->getMessage()]);
            $this->log('Install.progress.securityFailed');

            return [null, 0];
        }

        $this->log('Install.progress.securitySummary', ['errors' => $counts['error'], 'warnings' => $counts['warning']]);

        return [$counts['error'], $counts['warning']];
    }
}
