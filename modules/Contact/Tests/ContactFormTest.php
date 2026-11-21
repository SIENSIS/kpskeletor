<?php

declare(strict_types=1);

namespace Modules\Contact\Tests;

use Modules\Contact\Services\FormToken;

/**
 * The public contact form (D-48-01, D-48-02): a valid message is stored and reaches only its
 * recipient (the members of its group and its addresses, from the site's address with the
 * visitor in `Reply-To`, nothing to the visitor); each spam layer refuses and logs its reason
 * with a generic message; forged recipients, header injection and CSRF are refused.
 *
 * @internal
 */
final class ContactFormTest extends ContactTestCase
{
    public function testThePageShowsTheActiveRecipientsWithoutTheirAddresses(): void
    {
        $this->recipient(['ca' => 'Secretaria', 'es' => 'Secretaría'], 'secretaria@example.com', null, true, 1);
        $this->recipient(['ca' => 'Direcció'], 'direccio@example.com', null, true, 2);
        $this->recipient(['ca' => 'Antic'], null, null, false);

        $page = $this->body('GET', 'contact');
        $this->assertStringContainsString('Secretaria', $page);
        $this->assertStringContainsString('Direcció', $page);
        $this->assertStringNotContainsString('Antic', $page);
        $this->assertStringNotContainsString('@example.com', $page, 'Addresses never appear on the page.');
        $this->assertStringContainsString('name="kp_token"', $page);
        $this->assertStringContainsString('name="website"', $page);
        $this->assertStringContainsString('contact/captcha', $page, 'The captcha is always asked by default.');
        $this->assertStringContainsString('name="privacy"', $page);

        $this->assertStringContainsString('Secretaría', $this->body('GET', 'es/contact'));
    }

    public function testAValidMessageIsStoredAndReachesOnlyItsRecipient(): void
    {
        $office = $this->recipient(['ca' => 'Secretaria'], "secretaria@example.com\nconserge@example.com", 'editor');
        $this->recipient(['ca' => 'Direcció'], null, 'admin');
        $editor = $this->createUser('ed@example.com', 'editor');
        $admin  = $this->createUser('boss@example.com', 'admin');
        $user   = $this->createUser('pau@example.com', 'user');

        $this->send('POST', 'contact', $this->message($office))->assertRedirectTo(site_url('contact'));

        $row = db_connect()->table('contact_messages')->get()->getRowArray();
        $this->assertSame('Horari de matrícula', $row['subject']);
        $this->assertSame('Secretaria', $row['recipient_name']);
        $this->assertSame('new', $row['status']);
        $this->assertArrayNotHasKey('ip_address', $row, 'No IP address is stored.');

        $notified = array_map('intval', array_column(db_connect()->table('notifications')->get()->getResultArray(), 'user_id'));
        $this->assertSame([(int) $editor->id], $notified, 'Only the members of the recipient’s group.');
        $this->assertNotContains((int) $admin->id, $notified);
        $this->assertNotContains((int) $user->id, $notified);

        $queue  = db_connect()->table('mail_queue')->orderBy('id')->get()->getResultArray();
        $direct = array_values(array_filter($queue, static fn (array $mail): bool => $mail['user_id'] === null));
        $this->assertSame(['secretaria@example.com', 'conserge@example.com'], array_column($direct, 'to_email'));
        $this->assertSame(['marta@example.org', 'marta@example.org'], array_column($direct, 'reply_to'), 'The visitor only in Reply-To.');
        $this->assertNotContains('marta@example.org', array_column($queue, 'to_email'), 'No automatic reply to the visitor.');

        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'message_received')->countAllResults());
        $this->assertSame([], $this->refusals());
    }

    public function testARecipientWithoutGroupOrAddressesNotifiesTheHoldersOfAll(): void
    {
        $nobody = $this->recipient(['ca' => 'General']);
        $admin  = $this->createUser('boss@example.com', 'admin');
        $this->createUser('ed@example.com', 'editor');

        $this->send('POST', 'contact', $this->message($nobody))->assertRedirect();

        $this->assertSame([(int) $admin->id], array_map('intval', array_column(db_connect()->table('notifications')->get()->getResultArray(), 'user_id')));
    }

    public function testTheHoneypotRefuses(): void
    {
        $id = $this->recipient();

        $page = $this->body('POST', 'contact', $this->message($id, ['website' => 'https://spam.example']));

        $this->assertStringContainsString($this->ca('Contact.form.refused'), $page);
        $this->assertSame(0, $this->messageCount());
        $this->assertSame(['honeypot'], $this->refusals());
    }

    public function testTheTimeTrapRefusesTooFastExpiredMissingAndForgedTokens(): void
    {
        $id     = $this->recipient();
        $forged = explode('.', $this->token());
        $cases  = [
            'too_fast'      => $this->token([], '/contact', 1),
            'expired'       => $this->token([], '/contact', FormToken::MAX_SECONDS + 60),
            'token_invalid' => '',
        ];

        foreach ($cases as $reason => $token) {
            $page = $this->body('POST', 'contact', $this->message($id, ['kp_token' => $token]));
            $this->assertStringContainsString($this->ca('Contact.form.refused'), $page, $reason);
        }

        // A token whose payload was changed (an older time, another list) keeps its old signature.
        $payload = rtrim(strtr(base64_encode((string) json_encode(['t' => time() - 10, 'r' => [], 'p' => '/contact'])), '+/', '-_'), '=');
        $this->body('POST', 'contact', $this->message($id, ['kp_token' => $payload . '.' . $forged[1]]));

        $this->assertSame(0, $this->messageCount());
        $this->assertSame(['too_fast', 'expired', 'token_invalid', 'token_invalid'], $this->refusals());
    }

    public function testTheCaptchaIsRequiredAndSingleUse(): void
    {
        $id = $this->recipient();

        $this->assertStringContainsString($this->ca('Contact.form.captchaWrong'), $this->body('POST', 'contact', $this->message($id, ['captcha' => '7'])));
        $this->assertStringContainsString($this->ca('Contact.form.captchaWrong'), $this->body('POST', 'contact', ['captcha' => ''] + $this->message($id)));

        $missing = $this->message($id);
        unset($missing['captcha']);
        $this->body('POST', 'contact', $missing);

        $this->assertSame(0, $this->messageCount());
        $this->assertSame(['captcha', 'captcha', 'captcha'], $this->refusals());

        $this->send('POST', 'contact', $this->message($id))->assertRedirect();
        $this->assertSame(1, $this->messageCount());
        // The challenge was used by that message: the same answer again is refused.
        $again = $this->message($id, ['email' => 'altra@example.org']);
        unset($_SESSION[\Modules\Auth\Services\Captcha::SESSION_KEY]);
        $this->assertStringContainsString($this->ca('Contact.form.captchaWrong'), $this->body('POST', 'contact', $again));
        $this->assertSame(1, $this->messageCount());
    }

    public function testInSuspiciousModeTheCaptchaIsAskedAfterAMessage(): void
    {
        setting()->set('Contact.captcha', 'suspicious');
        $id = $this->recipient();

        $this->assertStringNotContainsString('contact/captcha', $this->body('GET', 'contact'));
        $first = $this->message($id);
        unset($first['captcha']);
        $this->send('POST', 'contact', $first)->assertRedirect();
        $this->assertSame(1, $this->messageCount());

        $this->assertStringContainsString('contact/captcha', $this->body('GET', 'contact'), 'Same IP within the hour: suspicious.');
        $second = $this->message($id, ['email' => 'altra@example.org']);
        unset($second['captcha']);
        $this->assertStringContainsString($this->ca('Contact.form.captchaWrong'), $this->body('POST', 'contact', $second));
        $this->assertSame(1, $this->messageCount());

        setting()->forget('Contact.captcha');
    }

    public function testValidationErrorsAreShownWithTheValues(): void
    {
        $id   = $this->recipient();
        $page = $this->body('POST', 'contact', $this->message($id, ['email' => 'no-és-un-correu', 'privacy' => '', 'message' => 'curt']));

        $this->assertStringContainsString($this->ca('Contact.form.privacyRequired'), $page);
        $this->assertStringContainsString('no-és-un-correu', $page);
        $this->assertStringContainsString('is-invalid', $page);
        $this->assertSame(0, $this->messageCount());
        $this->assertSame([], $this->refusals(), 'Mistakes of people are not refusals.');
    }

    public function testForgedRecipientsAreRefused(): void
    {
        $office   = $this->recipient(['ca' => 'Secretaria']);
        $head     = $this->recipient(['ca' => 'Direcció']);
        $inactive = $this->recipient(['ca' => 'Antic'], null, null, false);

        // Inactive, unknown, and not in the list a section offered (the list is in the signed token).
        $this->body('POST', 'contact', $this->message($inactive));
        $this->body('POST', 'contact', $this->message(999));
        $this->body('POST', 'contact', $this->message($head, ['kp_token' => $this->token([$office], '/escriu-nos')]));

        $this->assertSame(0, $this->messageCount());
        $this->assertSame(['recipient', 'recipient', 'recipient'], $this->refusals());

        $this->send('POST', 'contact', $this->message($office, ['kp_token' => $this->token([$office], '/escriu-nos')]))->assertRedirectTo(site_url('escriu-nos'));
        $this->assertSame(1, $this->messageCount());
    }

    public function testHeaderInjectionIsRefused(): void
    {
        $id = $this->recipient(['ca' => 'Secretaria'], 'secretaria@example.com');

        $this->body('POST', 'contact', $this->message($id, ['name' => "Marta\r\nBcc: victim@example.net"]));
        $this->body('POST', 'contact', $this->message($id, ['subject' => "Hola\nBcc: victim@example.net"]));
        $this->body('POST', 'contact', $this->message($id, ['email' => "marta@example.org\r\nBcc: victim@example.net"]));

        $this->assertSame(0, $this->messageCount());
        $this->assertSame(0, db_connect()->table('mail_queue')->countAllResults());
        $this->assertSame(['header_injection', 'header_injection'], array_slice($this->refusals(), 0, 2));
        $this->assertNotContains('victim@example.net', array_column(db_connect()->table('mail_queue')->get()->getResultArray(), 'to_email'));
    }

    public function testContentRulesRefuseLinksBlockedWordsAndOtherScripts(): void
    {
        $id = $this->recipient();
        setting()->set('Contact.blockedWords', ['Casino', 'préstec ràpid']);

        $this->body('POST', 'contact', $this->message($id, ['message' => 'Mireu https://a.example i http://b.example i www.c.example ara.']));
        $this->body('POST', 'contact', $this->message($id, ['message' => 'Ofereixo un PRESTEC RAPID sense interessos.']));
        $this->body('POST', 'contact', $this->message($id, ['subject' => 'Millor CASINO']));

        setting()->set('Contact.latinScriptOnly', true);
        $this->body('POST', 'contact', $this->message($id, ['message' => 'Здравствуйте, это очень выгодное предложение для вас.']));

        $this->assertSame(0, $this->messageCount());
        $this->assertSame(['too_many_links', 'blocked_word', 'blocked_word', 'script'], $this->refusals());

        // Two links are allowed by default.
        $this->send('POST', 'contact', $this->message($id, ['message' => 'Vegeu https://a.example i https://b.example, gràcies.']))->assertRedirect();
        $this->assertSame(1, $this->messageCount());

        setting()->forget('Contact.blockedWords');
        setting()->forget('Contact.latinScriptOnly');
    }

    public function testTheRateLimitCountsPerIpAndPerEmail(): void
    {
        $id = $this->recipient();
        setting()->set('Contact.rateLimit', 2);

        $this->send('POST', 'contact', $this->message($id))->assertRedirect();
        $this->send('POST', 'contact', $this->message($id, ['email' => 'b@example.org']))->assertRedirect();
        $response = $this->send('POST', 'contact', $this->message($id, ['email' => 'c@example.org']));
        $response->assertStatus(429);
        $this->assertStringContainsString($this->ca('Contact.form.refused'), html_entity_decode((string) $response->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $this->assertSame(2, $this->messageCount());

        // Another IP, the same email: refused by the email's counter.
        service('superglobals')->setServer('REMOTE_ADDR', '192.0.2.99');
        $this->send('POST', 'contact', $this->message($id, ['email' => 'b@example.org']))->assertRedirect();
        $this->send('POST', 'contact', $this->message($id, ['email' => 'b@example.org']))->assertStatus(429);
        $this->assertSame(3, $this->messageCount());
        $this->assertSame(['rate_limit', 'rate_limit'], $this->refusals());

        setting()->forget('Contact.rateLimit');
    }

    public function testThePostNeedsTheCsrfTokenAndTheFormsLimit(): void
    {
        $id = $this->recipient();

        try {
            $this->send('POST', 'contact', ['csrf_test_name' => 'wrong'] + $this->message($id));
            $this->fail('A wrong CSRF token is refused.');
        } catch (\CodeIgniter\Security\Exceptions\SecurityException) {
            $this->assertSame(0, $this->messageCount());
        }

        $routes = service('routes');
        $routes->loadRoutes();
        $this->assertContains('ratelimit:forms', $routes->getFiltersForRoute('contact', 'POST'));
    }

    public function testTheCaptchaImageIsServed(): void
    {
        $response = $this->send('GET', 'contact/captcha');

        $response->assertOK();
        $this->assertStringContainsString('image/png', $response->response()->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('no-store', $response->response()->getHeaderLine('Cache-Control'));
    }
}
