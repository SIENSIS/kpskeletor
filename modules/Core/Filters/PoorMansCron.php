<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Scheduler\TaskRunner;
use Throwable;

/**
 * The fallback for hosts without cron (setting `Core.poorMansCron`, off by default): a back-office
 * request runs the due scheduled tasks at most once a minute, after the response is sent
 * (docs/scheduler.md).
 *
 * Part of the `backend` filter chain (after filter). The tasks run in a shutdown function: with
 * PHP-FPM or LiteSpeed the response is finished first (`fastcgi_finish_request()`); with other
 * servers (mod_php, `php spark serve`) the browser waits for the tasks. The session is closed
 * first so the user's next request is not blocked. A real cron entry is always preferred.
 */
final class PoorMansCron implements FilterInterface
{
    /** Seconds between two runs. */
    public const INTERVAL = 60;

    private const CACHE_KEY = 'core_poor_mans_cron';

    private static bool $scheduled = false;

    /**
     * Replaces `register_shutdown_function()` (tests).
     *
     * @var (\Closure(callable): void)|null
     */
    public static ?\Closure $defer = null;

    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        return null;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        if (self::$scheduled || ! self::enabled() || ! self::claimMinute()) {
            return null;
        }

        self::$scheduled = true;
        $callback        = [self::class, 'runAfterResponse'];
        self::$defer !== null ? (self::$defer)($callback) : register_shutdown_function($callback);

        return null;
    }

    /**
     * Forgets that this process already scheduled a run (tests).
     */
    public static function reset(): void
    {
        self::$scheduled = false;
        cache()->delete(self::CACHE_KEY);
    }

    public static function enabled(): bool
    {
        try {
            return (bool) setting('Core.poorMansCron');
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * True for the first request of each interval (the cache keeps the time of the last run; the
     * runner's atomic claims prevent double runs if two requests pass at the same moment).
     */
    public static function claimMinute(?int $now = null): bool
    {
        $now ??= time();
        $cache = cache();
        $last  = $cache->get(self::CACHE_KEY);

        if (is_int($last) && $now - $last < self::INTERVAL) {
            return false;
        }

        $cache->save(self::CACHE_KEY, $now, self::INTERVAL * 2);

        return true;
    }

    /**
     * Shutdown function: finish the response, release the session, run the due tasks.
     */
    public static function runAfterResponse(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_write_close();
        }

        if (function_exists('fastcgi_finish_request')) {
            fastcgi_finish_request();
        } elseif (function_exists('litespeed_finish_request')) {
            litespeed_finish_request();
        }

        ignore_user_abort(true);

        try {
            (new TaskRunner())->runDue(null, TaskRunner::SOURCE_WEB);
        } catch (Throwable $e) {
            log_message('error', 'Poor man\'s cron failed: {message}', ['message' => $e->getMessage()]);
        }
    }
}
