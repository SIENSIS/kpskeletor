<?php

declare(strict_types=1);

namespace Modules\Contact\Tests;

use CodeIgniter\Database\Migration;
use CodeIgniter\I18n\Time;
use Config\Database;
use Kpcrudy\Crud;
use Modules\Contact\Config\Module;
use Modules\Contact\Tasks\PurgeMessages;
use Modules\Core\Modules\ManifestValidator;

/**
 * The back office of the Contact module: permissions (forged POSTs included), the record rule
 * (a recipient's group sees only its messages), the operations, escaping of visitors' text,
 * the recipients' addresses, the widget, the retention task, the manifest and the migrations.
 *
 * @internal
 */
final class MessagesTest extends ContactTestCase
{
    public function testManifestIsValid(): void
    {
        $this->assertSame([], (new ManifestValidator())->validate(new Module(), dirname(__DIR__)));
        $this->assertFileExists(dirname(__DIR__) . '/CLAUDE.md');
    }

    public function testScreensNeedTheirPermissions(): void
    {
        $this->send('GET', 'backend/contact/messages')->assertRedirectTo(site_url('login'));

        $this->loginWith();
        $this->send('GET', 'backend/contact/messages')->assertStatus(403);
        $this->send('GET', 'backend/contact/recipients')->assertStatus(403);

        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $this->send('GET', 'backend/contact/messages')->assertOK();
        $this->send('GET', 'backend/contact/recipients')->assertOK();
    }

    public function testARecipientsGroupSeesOnlyItsMessages(): void
    {
        $office = $this->recipient(['ca' => 'Secretaria'], null, 'editor');
        $head   = $this->recipient(['ca' => 'Direcció'], null, 'admin');
        $mine   = $this->stored($office, 'Per a secretaria');
        $other  = $this->stored($head, 'Per a direcció');

        $this->createUser('ed@example.com', 'editor');
        $this->login('ed@example.com');
        $list = $this->body('GET', 'backend/contact/messages');
        $this->assertStringContainsString('Per a secretaria', $list);
        $this->assertStringNotContainsString('Per a direcció', $list);

        $this->send('GET', 'backend/contact/messages/show/' . $this->code($mine))->assertOK();
        $this->assertStringNotContainsString('Per a direcció', $this->body('GET', 'backend/contact/messages/show/' . $this->code($other)), 'A forged id is a missing record.');
        $this->send('POST', 'backend/contact/messages/answer/' . $this->code($other));
        $this->assertSame('new', $this->row($other)['status']);

        // Editors may not delete (no grant), even with a forged POST.
        $this->assertCrudRefused($this->send('POST', 'backend/contact/messages/delete/' . $this->code($mine)), 'backend/contact/messages', 'delete');
        $this->assertNotNull($this->row($mine));

        // The admin (`contact-messages.all`) sees everything.
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $list = $this->body('GET', 'backend/contact/messages');
        $this->assertStringContainsString('Per a secretaria', $list);
        $this->assertStringContainsString('Per a direcció', $list);
    }

    public function testMarkAnsweredAndArchive(): void
    {
        $id   = $this->stored($this->recipient(['ca' => 'Secretaria'], null, 'editor'), 'Consulta');
        $user = $this->createUser('ed@example.com', 'editor');
        $this->login('ed@example.com');

        $this->send('POST', 'backend/contact/messages/answer/' . $this->code($id))->assertOK();
        $row = $this->row($id);
        $this->assertSame('answered', $row['status']);
        $this->assertSame((int) $user->id, (int) $row['answered_by']);
        $this->assertNotNull($row['answered_at']);

        $this->send('POST', 'backend/contact/messages/archive/' . $this->code($id))->assertOK();
        $this->assertSame('archived', $this->row($id)['status']);
        $this->assertSame(1, db_connect()->table('activity_log')->where('module', 'Contact')->where('action', 'answer')->countAllResults());
    }

    public function testOperationsNeedTheirPermissions(): void
    {
        $id = $this->stored($this->recipient(['ca' => 'General']), 'Consulta');
        $this->loginWith('contact-messages.list', 'contact-messages.show', 'contact-messages.all');
        $list = 'backend/contact/messages';

        $this->assertCrudRefused($this->send('POST', $list . '/answer/' . $this->code($id)), $list, 'answer');
        $this->assertCrudRefused($this->send('POST', $list . '/archive/' . $this->code($id)), $list, 'archive');
        $this->assertCrudRefused($this->send('POST', $list . '/delete/' . $this->code($id)), $list, 'delete');
        $this->assertSame('new', $this->row($id)['status']);
    }

    public function testVisitorsTextIsEscapedInTheBackOfficeAndTheEmail(): void
    {
        $id = $this->recipient(['ca' => 'Secretaria'], 'secretaria@example.com');
        $this->send('POST', 'contact', $this->message($id, ['subject' => 'Hola <b>món</b>', 'message' => "<script>alert(1)</script>\n<img src=x onerror=alert(2)> text"]))->assertRedirect();

        $email = (string) db_connect()->table('mail_queue')->where('to_email', 'secretaria@example.com')->get()->getRowArray()['body'];
        $this->assertStringNotContainsString('<script>', $email);
        $this->assertStringNotContainsString('<img src=x', $email);
        $this->assertStringContainsString('&lt;script&gt;', $email);

        $message = (int) db_connect()->table('contact_messages')->get()->getRowArray()['id'];
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $page = (string) $this->send('GET', 'backend/contact/messages/show/' . $this->code($message))->response()->getBody();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $page);
        $this->assertStringNotContainsString('<img src=x', $page);
        $this->assertStringNotContainsString('<b>món</b>', $page);
        $this->assertStringContainsString('mailto:marta%40example.org', html_entity_decode($page, ENT_QUOTES | ENT_HTML5, 'UTF-8'), 'Reply opens the user’s own mail program.');
    }

    public function testRecipientAddressesAreValidated(): void
    {
        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');
        $base = ['kp_tr' => ['ca' => ['name' => 'Consergeria']], 'group_name' => '', 'position' => '3', 'active' => '1'];

        $this->send('POST', 'backend/contact/recipients/add', $base + ['emails' => "bona@example.com\nno-valida"]);
        $this->assertSame(0, db_connect()->table('contact_recipients')->countAllResults());

        $this->send('POST', 'backend/contact/recipients/add', $base + ['emails' => "Bona@Example.com, altra@example.com\nbona@example.com"])->assertRedirect();
        $row = db_connect()->table('contact_recipients')->get()->getRowArray();
        $this->assertSame("bona@example.com\naltra@example.com", $row['emails']);
        $this->assertNull($row['group_name']);

        $this->send('POST', 'backend/contact/recipients/add', $base + ['emails' => '', 'group_name' => 'no-existeix']);
        $this->assertSame([], db_connect()->table('contact_recipients')->where('group_name', 'no-existeix')->get()->getResultArray(), 'Unknown groups are never stored.');
    }

    public function testTheWidgetShowsTheNewMessagesTheUserMayRead(): void
    {
        $office = $this->recipient(['ca' => 'Secretaria'], null, 'editor');
        $head   = $this->recipient(['ca' => 'Direcció'], null, 'admin');
        $this->stored($office, 'Nou per a secretaria');
        $this->stored($head, 'Nou per a direcció');

        $this->createUser('ed@example.com', 'editor');
        $this->login('ed@example.com');
        $html = (string) (new \Modules\Contact\Widgets\NewMessages())->render(['limit' => 5]);
        $this->assertStringContainsString('Nou per a secretaria', $html);
        $this->assertStringNotContainsString('Nou per a direcció', $html);
    }

    public function testTheRetentionTaskDeletesOldMessages(): void
    {
        $id  = $this->recipient();
        $old = $this->stored($id, 'Antic', Time::now()->subDays(400));
        $new = $this->stored($id, 'Recent', Time::now()->subDays(100));

        $result = (new PurgeMessages())->run();

        $this->assertFalse($result->failed());
        $this->assertNull($this->row($old));
        $this->assertNotNull($this->row($new));

        setting()->set('Contact.retentionDays', 60);
        (new PurgeMessages())->run();
        $this->assertNull($this->row($new));
        $this->assertSame(1, db_connect()->table('activity_log')->where('action', 'messages_purged')->countAllResults() >= 1 ? 1 : 0);

        // Never fewer than 30 days, whatever is stored.
        $recent = $this->stored($id, 'Fa 20 dies', Time::now()->subDays(20));
        setting()->set('Contact.retentionDays', 1);
        (new PurgeMessages())->run();
        $this->assertNotNull($this->row($recent));
        setting()->forget('Contact.retentionDays');
    }

    public function testMigrationsGoDownAndUpAgain(): void
    {
        $migrations = [];

        foreach (glob(dirname(__DIR__) . '/Database/Migrations/*.php') ?: [] as $file) {
            $class = 'Modules\Contact\Database\Migrations\\' . preg_replace('/^[\d_-]+/', '', basename($file, '.php'));

            if (! class_exists($class, false)) {
                require_once $file;
            }

            $migrations[] = new $class(Database::forge('tests'));
        }

        $this->assertCount(3, $migrations);
        $tables = ['contact_recipients', 'contact_recipient_translations', 'contact_messages'];

        foreach (array_reverse($migrations) as $migration) {
            \assert($migration instanceof Migration);
            $migration->down();
        }

        $this->db->resetDataCache();

        foreach ($tables as $table) {
            $this->assertFalse($this->db->tableExists($table), $table . ' dropped.');
        }

        foreach ($migrations as $migration) {
            $migration->up();
        }

        $this->db->resetDataCache();

        foreach ($tables as $table) {
            $this->assertTrue($this->db->tableExists($table), $table . ' created again.');
        }
    }

    private function stored(int $recipient, string $subject, ?Time $at = null): int
    {
        $at ??= Time::now();
        db_connect()->table('contact_messages')->insert([
            'recipient_id' => $recipient, 'recipient_name' => 'R', 'name' => 'Marta', 'email' => 'marta@example.org',
            'subject' => $subject, 'message' => 'Text del missatge.', 'locale' => 'ca', 'status' => 'new',
            'created_at' => $at->toDateTimeString(), 'updated_at' => $at->toDateTimeString(),
        ]);

        return (int) db_connect()->insertID();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function row(int $id): ?array
    {
        $row = db_connect()->table('contact_messages')->where('id', $id)->get()->getRowArray();

        return is_array($row) ? $row : null;
    }

    private function code(int $id): string
    {
        return Crud::maskId($id, 'contact_messages');
    }
}
