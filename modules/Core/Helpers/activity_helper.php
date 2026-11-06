<?php

declare(strict_types=1);

if (! function_exists('activity_log')) {
    /**
     * Writes an entry to the activity log (docs/activity.md). Context: `resource`, `record_id`,
     * `summary`, `before`, `after`, `changes`, `user_id`; secrets are masked by key.
     *
     * ```php
     * activity_log('News', 'publish', ['resource' => 'news-posts', 'record_id' => $id, 'summary' => $title]);
     * ```
     *
     * @param array<string, mixed> $context
     */
    function activity_log(string $module, string $action, array $context = []): ?int
    {
        return service('activity')->log($module, $action, $context);
    }
}

if (! function_exists('security_log')) {
    /**
     * Writes a security entry (type `security`) to the activity log: failed logins and codes,
     * throttling, denials, re-authentication, tokens, refused uploads and packages.
     *
     * @param array<string, mixed> $context
     */
    function security_log(string $module, string $action, array $context = []): ?int
    {
        return service('activity')->security($module, $action, $context);
    }
}
