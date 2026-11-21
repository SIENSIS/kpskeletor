<?php

declare(strict_types=1);

namespace Modules\Contact\Tests;

use CodeIgniter\I18n\Time;
use Modules\Auth\Services\Captcha;
use Modules\Contact\Services\FormToken;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Tests\ContentLocalesTrait;
use Modules\Dashboard\Tests\DashboardTestCase;

/**
 * Base of the Contact tests: the Auth fixture, every manifest's declarations (the Contact widget
 * included), recipients, a signed form token from the past (the time trap), a captcha challenge
 * with a known answer and the messages refused (security log).
 */
abstract class ContactTestCase extends DashboardTestCase
{
    use ContentLocalesTrait;

    protected array $widgetModules = ['Core', 'Dashboard', 'Users', 'Contact'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useShippedContentLocales();
        CrudPermissions::reset();
        cache()->clean();
    }

    protected function tearDown(): void
    {
        Time::setTestNow();
        parent::tearDown();
    }

    /**
     * A recipient with its names.
     *
     * @param array<string, string> $names
     */
    protected function recipient(array $names = ['ca' => 'Secretaria'], ?string $emails = null, ?string $group = null, bool $active = true, int $position = 0): int
    {
        db_connect()->table('contact_recipients')->insert([
            'emails' => $emails, 'group_name' => $group, 'position' => $position, 'active' => $active ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $id = (int) db_connect()->insertID();

        foreach ($names as $locale => $name) {
            db_connect()->table('contact_recipient_translations')->insert(['recipient_id' => $id, 'locale' => $locale, 'name' => $name]);
        }

        return $id;
    }

    /**
     * A token issued `$age` seconds ago.
     *
     * @param list<int> $only
     */
    protected function token(array $only = [], string $return = '/contact', int $age = 10): string
    {
        return FormToken::issue($only, $return, time() - $age);
    }

    /**
     * A captcha challenge whose answer is 5 in the session.
     */
    protected function challenge(int $answer = 5): void
    {
        $_SESSION[Captcha::SESSION_KEY] = ['key' => 'test-key', 'hash' => hash_hmac('sha256', (string) $answer, 'test-key')];
    }

    /**
     * A valid post (with a challenge in the session) for a recipient; `$values` override.
     *
     * @param array<string, string> $values
     *
     * @return array<string, string>
     */
    protected function message(int $recipient, array $values = []): array
    {
        $this->challenge();

        return $values + [
            'name'      => 'Marta Riera',
            'email'     => 'marta@example.org',
            'recipient' => (string) $recipient,
            'subject'   => 'Horari de matrícula',
            'message'   => 'Bon dia, voldria saber l’horari de matrícula.',
            'privacy'   => '1',
            'captcha'   => '5',
            'website'   => '',
            'kp_token'  => $this->token(),
        ];
    }

    /**
     * A text in the site's default language (the language of pages without a prefix).
     */
    protected function ca(string $key): string
    {
        return lang($key, [], 'ca');
    }

    protected function messageCount(): int
    {
        return db_connect()->table('contact_messages')->countAllResults();
    }

    /**
     * Reasons of the refusals logged.
     *
     * @return list<string>
     */
    protected function refusals(): array
    {
        $reasons = [];

        foreach (db_connect()->table('activity_log')->where('module', 'Contact')->where('action', 'message_refused')->orderBy('id')->get()->getResultArray() as $row) {
            $this->assertSame('security', $row['type']);
            $reasons[] = (string) (json_decode((string) $row['changes'], true)['changes']['reason'] ?? '');
        }

        return $reasons;
    }

    /**
     * The page as text with its HTML entities decoded.
     *
     * @param array<string, mixed> $params
     */
    protected function body(string $method, string $path, array $params = []): string
    {
        return html_entity_decode((string) $this->send($method, $path, $params)->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
