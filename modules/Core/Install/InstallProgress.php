<?php

declare(strict_types=1);

namespace Modules\Core\Install;

/**
 * Listener of the installer's steps (the web installer's progress page, tests).
 *
 * Step codes, in order: `checks`, `env`, `database`, `migrations` (one per namespace, "n of m"),
 * `modules` (registry and first-install seeds, one per module), `permissions`, `settings`,
 * `assets`, `groups`, `api`, `superadmin`, `demo` (one per module), `envFile`, `lock`; the web
 * job adds `securityCheck` and `done`. The label of a step is the language key
 * `Install.step.<code>` with the step's parameters ({done}, {total} and the step's own).
 *
 * Parameters and log lines never hold secrets: only step codes, counts, module and namespace
 * names and language keys (no passwords, keys, `.env` values or demo passwords).
 */
interface InstallProgress
{
    /**
     * A step starts (`$done` of `$total` for the steps that repeat, 0 of 0 otherwise).
     *
     * @param array<string, scalar> $params
     */
    public function step(string $code, int $done = 0, int $total = 0, array $params = []): void;

    /**
     * An extra line for the log: a language key (`Install.progress.<what>`) and its parameters.
     *
     * @param array<string, scalar> $params
     */
    public function log(string $key, array $params = []): void;

    /**
     * The installation stopped in step `$code` (the message may be shown to the installer's user).
     */
    public function failed(string $code, string $message): void;
}
