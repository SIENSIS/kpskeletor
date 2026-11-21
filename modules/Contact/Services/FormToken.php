<?php

declare(strict_types=1);

namespace Modules\Contact\Services;

use CodeIgniter\I18n\Time;
use Modules\Core\Security\SigningKey;

/**
 * The signed token of the contact form (spam layer 2, the time trap; D-48-02): when the form is
 * shown it carries the time, the recipients the form offers (a section may restrict them) and
 * the path to go back to, signed with an HMAC of a key derived from the application's key
 * (`SigningKey`, context `contact.form`). A message sent less than MIN_SECONDS after the form was
 * shown (a robot) or more than MAX_SECONDS later (a stale or replayed form) is refused, and so is
 * a token that was changed (a forged recipient list or return path).
 */
final class FormToken
{
    public const MIN_SECONDS = 3;
    public const MAX_SECONDS = 7200;

    /**
     * @param list<int> $recipients the recipient ids the form offers ([] = every active one)
     * @param string    $return     internal path (without the language prefix) to go back to
     */
    public static function issue(array $recipients, string $return = '/contact', ?int $now = null): string
    {
        $payload = self::encode([
            't' => $now ?? Time::now()->getTimestamp(),
            'r' => array_map('intval', $recipients),
            'p' => $return,
        ]);

        return $payload . '.' . self::sign($payload);
    }

    /**
     * Checks a posted token.
     *
     * @return array{ok: bool, reason: string, recipients: list<int>, return: string}
     *               reason: '' when ok, else `token_invalid`, `too_fast` or `expired`
     */
    public static function verify(mixed $token, ?int $now = null): array
    {
        $refused = static fn (string $reason): array => ['ok' => false, 'reason' => $reason, 'recipients' => [], 'return' => '/contact'];

        if (! is_string($token) || strlen($token) > 2000 || substr_count($token, '.') !== 1) {
            return $refused('token_invalid');
        }

        [$payload, $signature] = explode('.', $token);

        if ($payload === '' || ! hash_equals(self::sign($payload), $signature)) {
            return $refused('token_invalid');
        }

        $data = json_decode((string) base64_decode(strtr($payload, '-_', '+/'), true), true);

        if (! is_array($data) || ! is_int($data['t'] ?? null) || ! is_array($data['r'] ?? null) || ! is_string($data['p'] ?? null)) {
            return $refused('token_invalid');
        }

        $age = ($now ?? Time::now()->getTimestamp()) - $data['t'];

        if ($age < self::MIN_SECONDS) {
            return $refused('too_fast');
        }

        if ($age > self::MAX_SECONDS) {
            return $refused('expired');
        }

        return [
            'ok'         => true,
            'reason'     => '',
            'recipients' => array_values(array_filter(array_map('intval', $data['r']), static fn (int $id): bool => $id > 0)),
            'return'     => $data['p'],
        ];
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function encode(array $data): string
    {
        return SigningKey::base64((string) json_encode($data, JSON_UNESCAPED_SLASHES));
    }

    private static function sign(string $payload): string
    {
        return SigningKey::base64(hash_hmac('sha256', $payload, SigningKey::derive('contact.form'), true));
    }
}
