<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use CodeIgniter\Session\SessionInterface;

/**
 * Local arithmetic captcha (decision D-00-53): a PNG drawn with GD, no external service.
 *
 * Only an HMAC of the answer (with a random per-challenge key) is kept in the session; a check
 * compares with `hash_equals()` and removes the challenge whatever the result (single use).
 * When the setting `Auth.captcha` is on it is enforced in every environment.
 */
final class Captcha
{
    public const SESSION_KEY = 'kp_captcha';

    private readonly AuthPolicy $policy;

    public function __construct(?AuthPolicy $policy = null, private readonly ?SessionInterface $session = null)
    {
        $this->policy = $policy ?? new AuthPolicy();
    }

    public function enabled(): bool
    {
        return $this->policy->captcha();
    }

    /**
     * Creates a new challenge (replacing any previous one) and returns its question and answer.
     *
     * @return array{question: string, answer: int}
     */
    public function newChallenge(): array
    {
        $a = random_int(2, 9);
        $b = random_int(1, 9);

        if (random_int(0, 1) === 1 && $a > $b) {
            $question = $a . ' - ' . $b . ' =';
            $answer   = $a - $b;
        } else {
            $question = $a . ' + ' . $b . ' =';
            $answer   = $a + $b;
        }

        $key = bin2hex(random_bytes(16));

        $this->session()->set(self::SESSION_KEY, [
            'key'  => $key,
            'hash' => hash_hmac('sha256', (string) $answer, $key),
        ]);

        return ['question' => $question, 'answer' => $answer];
    }

    /**
     * Checks an answer once: the challenge is removed whether it matches or not.
     */
    public function verify(mixed $input): bool
    {
        $stored = $this->session()->get(self::SESSION_KEY);
        $this->session()->remove(self::SESSION_KEY);

        if (! is_array($stored) || ! is_string($stored['key'] ?? null) || ! is_string($stored['hash'] ?? null)) {
            return false;
        }

        if (! is_string($input) && ! is_int($input)) {
            return false;
        }

        $input = trim((string) $input);

        if (preg_match('/^-?\d{1,3}$/', $input) !== 1) {
            return false;
        }

        return hash_equals($stored['hash'], hash_hmac('sha256', (string) (int) $input, $stored['key']));
    }

    /**
     * A new challenge drawn as a PNG image.
     */
    public function image(): string
    {
        $question = $this->newChallenge()['question'];
        $width    = 150;
        $height   = 50;
        $image    = imagecreatetruecolor($width, $height);

        $background = (int) imagecolorallocate($image, 248, 249, 250);
        imagefilledrectangle($image, 0, 0, $width, $height, $background);

        for ($i = 0; $i < 6; $i++) {
            $noise = (int) imagecolorallocate($image, random_int(150, 210), random_int(150, 210), random_int(150, 210));
            imageline($image, random_int(0, $width), random_int(0, $height), random_int(0, $width), random_int(0, $height), $noise);
        }

        $x = 12;

        foreach (str_split($question) as $char) {
            $color = (int) imagecolorallocate($image, random_int(10, 80), random_int(10, 80), random_int(60, 120));
            imagestring($image, 5, $x, random_int(10, 22), $char, $color);
            $x += random_int(10, 14);
        }

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();
        imagedestroy($image);

        return $png;
    }

    private function session(): SessionInterface
    {
        return $this->session ?? session();
    }
}
