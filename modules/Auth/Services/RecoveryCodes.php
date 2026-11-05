<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\I18n\Time;
use Modules\Auth\Models\RecoveryCodeModel;

/**
 * 2FA recovery codes: 10 single-use codes of 10 characters (`xxxxx-xxxxx`, 50 random bits each)
 * shown once; only their SHA-256 hash is stored. Generating them replaces the previous set.
 */
final class RecoveryCodes
{
    public const COUNT = 10;

    /** Without 0/O and 1/I/L, so they are easy to type from paper. */
    private const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    private readonly RecoveryCodeModel $model;

    public function __construct(?RecoveryCodeModel $model = null)
    {
        $this->model = $model ?? model(RecoveryCodeModel::class);
    }

    /**
     * Replaces the user's codes with a new set and returns them (the only time they are visible).
     *
     * @return list<string>
     */
    public function generate(int $userId): array
    {
        $codes = [];
        $now   = Time::now()->format('Y-m-d H:i:s');

        while (count($codes) < self::COUNT) {
            $code         = $this->randomCode();
            $codes[$code] = $code;
        }

        $db = db_connect();
        $db->transStart();
        $this->model->where('user_id', $userId)->delete();

        foreach ($codes as $code) {
            $this->model->insert(['user_id' => $userId, 'code_hash' => self::hash(self::normalise($code)), 'created_at' => $now]);
        }

        $db->transComplete();

        return array_values($codes);
    }

    /**
     * Uses a code: true (and marks it used) when it is one of the user's unused codes.
     */
    public function consume(int $userId, string $code): bool
    {
        $normalised = self::normalise($code);

        if (strlen($normalised) !== 10) {
            return false;
        }

        $row = $this->model->where('user_id', $userId)->where('code_hash', self::hash($normalised))->where('used_at', null)->first();

        if (! is_array($row)) {
            return false;
        }

        // Conditional update: two requests with the same code cannot both succeed.
        $builder = $this->model->builder()->where('id', $row['id'])->where('used_at', null);
        $builder->update(['used_at' => Time::now()->format('Y-m-d H:i:s')]);

        return $builder->db()->affectedRows() === 1;
    }

    public function remaining(int $userId): int
    {
        return $this->model->remaining($userId);
    }

    public function delete(int $userId): void
    {
        $this->model->where('user_id', $userId)->delete();
    }

    /**
     * Upper case without separators or spaces; `abcde-fghij` and `ABCDEFGHIJ` are the same code.
     */
    public static function normalise(string $code): string
    {
        return strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    private static function hash(string $normalised): string
    {
        return hash('sha256', $normalised);
    }

    private function randomCode(): string
    {
        $max  = strlen(self::ALPHABET) - 1;
        $code = '';

        for ($i = 0; $i < 10; $i++) {
            $code .= self::ALPHABET[random_int(0, $max)];
        }

        return substr($code, 0, 5) . '-' . substr($code, 5);
    }
}
