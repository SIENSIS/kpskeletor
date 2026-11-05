<?php

declare(strict_types=1);

namespace Modules\Auth\Services;

use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use CodeIgniter\I18n\Time;
use DateTimeImmutable;
use OTPHP\TOTP as OtpTotp;
use Psr\Clock\ClockInterface;

/**
 * Time-based one-time passwords (RFC 6238: 6 digits, 30 seconds, SHA-1, the defaults every
 * authenticator app understands) with `spomky-labs/otphp`, and the enrolment QR code drawn
 * locally as SVG with `chillerlan/php-qrcode` (no external service).
 *
 * `match()` accepts the current time step and one step before or after (±30 seconds of clock
 * drift) and returns the step that matched, so the caller can refuse a step already used.
 */
final class Totp implements ClockInterface
{
    public const PERIOD = 30;

    /** Accepted drift, in time steps. */
    public const DRIFT = 1;

    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('@' . Time::now()->getTimestamp());
    }

    /**
     * A new random Base32 secret (160 bits).
     */
    public function newSecret(): string
    {
        return OtpTotp::generate($this, 20)->getSecret();
    }

    /**
     * The time step of a code that is valid now (±DRIFT), or null.
     */
    public function match(string $secret, string $code): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';

        if (preg_match('/^\d{6}$/', $code) !== 1 || $secret === '') {
            return null;
        }

        $totp = OtpTotp::createFromSecret($secret, $this);
        $now  = Time::now()->getTimestamp();
        $step = intdiv($now, self::PERIOD);

        for ($offset = -self::DRIFT; $offset <= self::DRIFT; $offset++) {
            $at = ($step + $offset) * self::PERIOD;

            if ($at >= 0 && hash_equals($totp->at($at), $code)) {
                return $step + $offset;
            }
        }

        return null;
    }

    /**
     * The code for a moment (tests and the demo only).
     */
    public function codeAt(string $secret, int $timestamp): string
    {
        return OtpTotp::createFromSecret($secret, $this)->at($timestamp);
    }

    /**
     * The `otpauth://` URI that authenticator apps read from the QR code.
     */
    public function provisioningUri(string $secret, string $account, string $issuer): string
    {
        $totp = OtpTotp::createFromSecret($secret, $this);
        $totp->setLabel($account !== '' ? $account : 'user');
        $totp->setIssuer($issuer !== '' ? str_replace(':', ' ', $issuer) : 'kpskeletor');

        return $totp->getProvisioningUri();
    }

    /**
     * The QR code of a URI as a `data:image/svg+xml;base64,…` URI, for an `<img>` (allowed by the
     * CSP's `img-src data:`; the SVG never runs inside the page).
     */
    public function qrDataUri(string $uri): string
    {
        $options = new QROptions([
            'outputType'       => QROutputInterface::MARKUP_SVG,
            'outputBase64'     => true,
            'svgAddXmlHeader'  => false,
            'drawLightModules' => false,
            'addQuietzone'     => true,
        ]);

        return (new QRCode($options))->render($uri);
    }
}
