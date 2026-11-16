<?php

declare(strict_types=1);

namespace Modules\Backup\Services;

use Closure;
use Modules\Backup\Config\Backup as BackupConfig;
use Throwable;

/**
 * Runs a restore started in the back office as a background job (D-27-04): after the response
 * is sent when the server allows it (FastCGI / LiteSpeed; `Config\Backup::$jobsAfterResponse`),
 * otherwise inside the request. The password lives only in this process's memory; the progress
 * goes to the JobStore, the merged `.env` (if the secrets differ) to the job's `.env` file.
 */
final class RestoreJob
{
    private readonly BackupConfig $config;

    public function __construct(
        ?BackupConfig $config = null,
        private readonly JobStore $jobs = new JobStore(),
        private readonly ?RestoreService $restorer = null,
    ) {
        $this->config = $config ?? config(BackupConfig::class);
    }

    /**
     * Starts the job; returns true when it already ran (inside the request).
     */
    public function launch(string $id, string $file, #[\SensitiveParameter] string $password, ?string $only, int $userId): bool
    {
        $run = function () use ($id, $file, $password, $only, $userId): void {
            $this->run($id, $file, $password, $only, $userId);
        };

        if ($this->config->jobsAfterResponse && ENVIRONMENT !== 'testing') {
            register_shutdown_function(static function () use ($run): void {
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
                $run();
            });

            return false;
        }

        $run();

        return true;
    }

    public function run(string $id, string $file, #[\SensitiveParameter] string $password, ?string $only, int $userId): void
    {
        $this->jobs->update($id, ['status' => 'running', 'step' => 'verify', 'percent' => 1]);
        $progress = Closure::fromCallable(function (string $step, int $percent) use ($id): void {
            $this->jobs->update($id, ['status' => $step === 'done' ? 'done' : 'running', 'step' => $step, 'percent' => $percent]);
        });

        try {
            $result = ($this->restorer ?? new RestoreService($this->config))->restore($file, $password, $only, $userId, 'ui', $progress);

            if ($result->mergedEnv !== null) {
                $this->jobs->putEnv($id, $result->mergedEnv);
            }

            $this->jobs->update($id, [
                'status' => 'done', 'step' => 'done', 'percent' => 100,
                'parts' => $result->parts, 'safety' => $result->safetyFile, 'secrets' => $result->envDifferences,
            ]);
        } catch (Throwable $e) {
            $this->jobs->update($id, [
                'status' => 'failed', 'step' => 'failed',
                'message' => $e instanceof BackupException ? $e->getMessage() : lang('Backup.error.unexpected'),
            ]);
        }
    }
}
