<?php

declare(strict_types=1);

namespace Modules\Events\Tests;

use CodeIgniter\I18n\Time;
use Modules\Api\Tests\ApiTestCase;
use Modules\Core\Permissions\CrudPermissions;
use Modules\Core\Tests\ContentLocalesTrait;
use Modules\Events\Services\EventRepository;

/**
 * The events API follows the site's languages (session 37): a fourth language is answered with
 * the fallback to the site's default language (Spanish here), not to Catalan.
 *
 * @internal
 */
final class ContentLocalesApiTest extends ApiTestCase
{
    use ContentLocalesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
        $this->useContentLocales();
    }

    public function testTheApiAnswersInTheNewLanguageWithTheDefaultFallback(): void
    {
        $start = Time::tomorrow()->setHour(10);
        $db    = db_connect();
        $db->table('events')->insert([
            'starts_at' => $start->toDateTimeString(), 'ends_at' => $start->addHours(1)->toDateTimeString(), 'all_day' => 0,
            'visibility' => 'public', 'status' => 'confirmed', 'sequence' => 0,
            'created_at' => Time::now()->toDateTimeString(), 'updated_at' => Time::now()->toDateTimeString(),
        ]);
        $id = (int) $db->insertID();
        $db->table('event_translations')->insertBatch([
            ['event_id' => $id, 'locale' => 'ca', 'title' => 'Títol', 'description' => ''],
            ['event_id' => $id, 'locale' => 'es', 'title' => 'Título', 'description' => ''],
        ]);

        $user = $this->createUser('ana@example.com', 'user');
        $data = $this->json($this->api('GET', 'api/v1/events/' . EventRepository::code($id) . '?lang=fr', null, $this->tokenFor($user)))['data'];
        $this->assertSame('Título', $data['title'], 'No French text: the default language, not Catalan.');

        $public = $this->json($this->api('GET', 'api/v1/public/events/' . EventRepository::code($id), null, null, ['Accept-Language' => 'fr']))['data'];
        $this->assertSame('Título', $public['title']);
    }
}
