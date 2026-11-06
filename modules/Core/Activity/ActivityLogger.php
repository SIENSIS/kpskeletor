<?php

declare(strict_types=1);

namespace Modules\Core\Activity;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\I18n\Time;

/**
 * Writes the append-only activity and security log (`activity_log`; docs/activity.md).
 *
 * ```php
 * activity_log('News', 'publish', ['resource' => 'news-posts', 'record_id' => $id, 'summary' => $title]);
 * security_log('Auth', 'login_failed', ['summary' => $email]);
 * ```
 *
 * - Values are stored as data, never interpolated into a message, so user input cannot forge
 *   entries; the summary loses control characters and line breaks.
 * - `before`/`after`/`changes` are masked by key (`SecretMasker`) and stored as JSON.
 * - The user is the logged-in user unless `user_id` is given; the IP address and the user agent
 *   come from the web request (none on the command line).
 * - Writing never breaks the action being logged: a failure goes to the application log.
 *
 * There is no update or delete: only `PurgeActivityLog` (retention) removes rows.
 */
class ActivityLogger
{
    public const TYPE_ACTIVITY = 'activity';
    public const TYPE_SECURITY = 'security';

    /** Module and action codes: lowercase letters, digits, `_`, `-`, `.` and `:`. */
    private const CODE = '/^[A-Za-z0-9][A-Za-z0-9_.:-]{0,63}$/';

    /** Bytes kept of the changes JSON. */
    public const MAX_CHANGES = 60000;

    /** @var array<string, string> module of each CRUD resource (request cache) */
    private array $modules = [];

    private bool $enabled = true;

    /**
     * @param BaseConnection<object|resource, object|resource>|null $db Default: the default connection
     */
    public function __construct(private readonly ?BaseConnection $db = null)
    {
    }

    /**
     * An activity entry. Context keys (all optional): `resource`, `record_id`, `summary`,
     * `before`, `after` (arrays: the changed values), `changes` (other details, array),
     * `user_id` (default: the logged-in user).
     *
     * @param array<string, mixed> $context
     *
     * @return int|null the entry's id (null when it could not be written)
     */
    public function log(string $module, string $action, array $context = []): ?int
    {
        return $this->write(self::TYPE_ACTIVITY, $module, $action, $context);
    }

    /**
     * A security entry (failed logins and codes, throttling, 403s, re-authentication, tokens,
     * refused uploads and packages…). Same context as `log()`.
     *
     * @param array<string, mixed> $context
     */
    public function security(string $module, string $action, array $context = []): ?int
    {
        return $this->write(self::TYPE_SECURITY, $module, $action, $context);
    }

    /**
     * A write made by a kpcrudy CRUD (`AuditedCrud`): insert, update, delete or restore, with the
     * changed values only.
     *
     * @param array<string, mixed>      $data
     * @param array<string, mixed>|null $old
     */
    public function crudWrite(string $resource, string $operation, string $id, array $data, ?array $old): ?int
    {
        [$before, $after] = match ($operation) {
            'insert' => [null, $data],
            'update' => self::diff($old ?? [], $data),
            default  => [$old, null],
        };

        if ($operation === 'update' && $after === []) {
            return null; // saved without changes
        }

        return $this->log($this->moduleOf($resource), $operation, [
            'resource'  => $resource,
            'record_id' => $id,
            'summary'   => self::labelOf($data + ($old ?? [])),
            'before'    => $before,
            'after'     => $after,
        ]);
    }

    /**
     * The values that changed: `[$before, $after]` with only the keys whose value differs.
     *
     * @param array<string, mixed> $old
     * @param array<string, mixed> $new
     *
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    public static function diff(array $old, array $new): array
    {
        $before = [];
        $after  = [];

        foreach ($new as $key => $value) {
            $previous = $old[$key] ?? null;

            if (self::same($previous, $value)) {
                continue;
            }

            $before[$key] = $previous;
            $after[$key]  = $value;
        }

        return [$before, $after];
    }

    /**
     * The module that declares a CRUD resource (from the permission catalogue), or '' if unknown.
     */
    public function moduleOf(string $resource): string
    {
        if (! isset($this->modules[$resource])) {
            try {
                $row = $this->db()->table('permissions')->select('module')
                    ->like('name', $resource . '.', 'after')->limit(1)->get()->getRowArray();
                $this->modules[$resource] = is_array($row) ? (string) $row['module'] : '';
            } catch (\Throwable) {
                $this->modules[$resource] = '';
            }
        }

        return $this->modules[$resource];
    }

    /**
     * Turns logging off and on (e.g. while a backup is restored, or in tests that do not care).
     */
    public function enable(bool $enabled = true): void
    {
        $this->enabled = $enabled;
    }

    /**
     * The typed login name for a failed-login entry: kept only when it is a valid email address
     * (a password typed in the wrong field must not reach the log).
     */
    public static function typedEmail(mixed $value): string
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false ? mb_substr($value, 0, 254) : '';
    }

    /**
     * A one-line text for the summary: control characters and line breaks become spaces.
     */
    public static function clean(string $text, int $length = 255): string
    {
        $text = trim((string) preg_replace('/[\x00-\x1F\x7F\x{2028}\x{2029}]+/u', ' ', $text));

        return mb_strlen($text) > $length ? mb_substr($text, 0, $length - 1) . '…' : $text;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function write(string $type, string $module, string $action, array $context): ?int
    {
        if (! $this->enabled) {
            return null;
        }

        if (preg_match(self::CODE, $action) !== 1 || ($module !== '' && preg_match(self::CODE, $module) !== 1)) {
            throw new \InvalidArgumentException('Invalid activity module or action code.');
        }

        try {
            $row = [
                'type'       => $type,
                'user_id'    => array_key_exists('user_id', $context) ? self::intOrNull($context['user_id']) : $this->currentUserId(),
                'module'     => $module,
                'resource'   => self::clean((string) ($context['resource'] ?? ''), 100),
                'action'     => $action,
                'record_id'  => isset($context['record_id']) && $context['record_id'] !== '' ? self::clean((string) $context['record_id'], 64) : null,
                'summary'    => self::clean((string) ($context['summary'] ?? '')),
                'changes'    => self::changesJson($context),
                'ip_address' => null,
                'user_agent' => null,
                'created_at' => Time::now()->format('Y-m-d H:i:s'),
            ];

            $request = service('request');

            // spark commands and tasks have a CLIRequest: no address.
            if ($request instanceof IncomingRequest) {
                $row['ip_address'] = mb_substr($request->getIPAddress(), 0, 45);
                $row['user_agent'] = self::clean((string) $request->getUserAgent());
            }

            $this->db()->table('activity_log')->insert($row);

            return (int) $this->db()->insertID();
        } catch (\Throwable $e) {
            log_message('error', 'Activity entry {module}/{action} not written: {message}', ['module' => $module, 'action' => $action, 'message' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function changesJson(array $context): ?string
    {
        $changes = [];

        foreach (['before', 'after', 'changes'] as $key) {
            if (isset($context[$key]) && is_array($context[$key]) && $context[$key] !== []) {
                $changes[$key] = SecretMasker::mask($context[$key]);
            }
        }

        if ($changes === []) {
            return null;
        }

        $json = (string) json_encode($changes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        return strlen($json) > self::MAX_CHANGES ? (string) json_encode(['truncated' => true]) : $json;
    }

    /**
     * A human label of a record for the summary (title, name, username, email…).
     *
     * @param array<string, mixed> $row
     */
    public static function labelOf(array $row): string
    {
        foreach (['title', 'name', 'username', 'label', 'subject', 'slug', 'email'] as $field) {
            if (isset($row[$field]) && is_scalar($row[$field]) && (string) $row[$field] !== '') {
                return (string) $row[$field];
            }
        }

        return '';
    }

    private static function same(mixed $a, mixed $b): bool
    {
        if (is_scalar($a) && is_scalar($b) || $a === null || $b === null) {
            return (string) (is_bool($a) ? (int) $a : $a) === (string) (is_bool($b) ? (int) $b : $b) && ($a === null) === ($b === null);
        }

        return $a == $b; // arrays (N-N relations): same values
    }

    private static function intOrNull(mixed $value): ?int
    {
        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function currentUserId(): ?int
    {
        try {
            return function_exists('auth') ? self::intOrNull(auth()->id()) : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return BaseConnection<object|resource, object|resource>
     */
    private function db(): BaseConnection
    {
        return $this->db ?? db_connect();
    }
}
