<?php

declare(strict_types=1);

namespace Modules\Contact\Services;

/**
 * What the form view needs, for the contact page and the `contact.form` section: the recipients
 * offered (a section may restrict them), a fresh signed token, whether the captcha is asked, and
 * the privacy link.
 */
final class ContactForm
{
    public function __construct(
        private readonly Recipients $recipients = new Recipients(),
        private readonly SenderHistory $history = new SenderHistory(),
    ) {
    }

    /**
     * @param list<int>             $only   recipient ids offered ([] = every active one)
     * @param array<string, string> $old    values to show again
     * @param array<string, string> $errors field => message
     *
     * @return array<string, mixed>
     */
    public function data(array $only, string $return, string $prefix = 'contact', array $old = [], array $errors = [], ?string $alert = null, bool $forceCaptcha = false): array
    {
        return [
            'recipients' => $this->recipients->active(service('request')->getLocale(), $only),
            'token'      => FormToken::issue($only, $return),
            'captcha'    => $forceCaptcha || $this->captchaRequired(),
            'privacyUrl' => self::privacyUrl(),
            'prefix'     => $prefix,
            'old'        => $old,
            'errors'     => $errors,
            'alert'      => $alert,
            'action'     => site_url('contact'),
        ];
    }

    /**
     * Whether this visitor must answer the captcha: always (the default), or in the mode
     * "only when suspicious" when their IP (or email) already sent or was refused within the hour.
     */
    public function captchaRequired(?string $email = null): bool
    {
        if (setting('Contact.captcha') !== 'suspicious') {
            return true;
        }

        return session('contact_suspicious') === true || $this->history->suspicious(service('request')->getIPAddress(), $email);
    }

    public static function privacyUrl(): ?string
    {
        $path = trim((string) setting('Contact.privacyPage'));

        return preg_match('#^/(?!/)\S*$#', $path) === 1 ? site_url(ltrim($path, '/')) : null;
    }
}
