<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use CodeIgniter\I18n\Time;
use Kpcrudy\Crud;
use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Permissions\CrudPermissions;

/**
 * Base of the Pages feature tests: the Auth fixture (every module migrated, catalogue and default
 * grants synced, requests with session and CSRF), helpers that write pages directly (`page()`),
 * mask ids and read pages back.
 */
abstract class PagesTestCase extends AuthTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        CrudPermissions::reset();
        service('theme')->reset();
    }

    protected function tearDown(): void
    {
        service('settings')->forget('Pages.homePage');
        parent::tearDown();
    }

    /**
     * Inserts a page; defaults: a published public root page, Catalan text, slug from the title.
     *
     * @param array<string, mixed>                $values columns of `pages`
     * @param array<string, array<string, mixed>> $texts  extra languages: locale => fields
     * @param list<string>                        $groups group names (visibility `groups`)
     */
    protected function page(string $title, array $values = [], array $texts = [], array $groups = []): int
    {
        $now = Time::now();
        $db  = db_connect();

        $db->table('pages')->insert($values + [
            'parent_id'  => null,
            'position'   => 0,
            'template'   => 'default',
            'status'     => 'published',
            'publish_at' => $now->subHours(1)->toDateTimeString(),
            'visibility' => 'public',
            'noindex'    => 0,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]);
        $id = (int) $db->insertID();

        $texts = ['ca' => ['title' => $title, 'slug' => \Modules\Core\Text\Slugger::slug($title)]] + $texts;

        foreach ($texts as $locale => $fields) {
            $db->table('page_translations')->insert($fields + [
                'page_id' => $id, 'locale' => $locale, 'summary' => 'Resum de ' . $fields['title'], 'body' => '<p>Text de ' . $fields['title'] . '</p>',
                'meta_title' => '', 'meta_description' => '', 'canonical' => '',
            ]);
        }

        foreach ($groups as $group) {
            $row = $db->table('groups')->where('name', $group)->get()->getRowArray();
            $this->assertIsArray($row, "Group {$group} exists.");
            $db->table('page_groups')->insert(['page_id' => $id, 'group_id' => (int) $row['id']]);
        }

        return $id;
    }

    protected function code(int $id): string
    {
        return Crud::maskId($id, 'pages');
    }

    /**
     * @return array<string, mixed>
     */
    protected function row(int $id): array
    {
        $row = db_connect()->table('pages')->where('id', $id)->get()->getRowArray();
        $this->assertIsArray($row);

        return $row;
    }

    /**
     * @return array<string, mixed>
     */
    protected function text(int $id, string $locale = 'ca'): array
    {
        $row = db_connect()->table('page_translations')->where('page_id', $id)->where('locale', $locale)->get()->getRowArray();
        $this->assertIsArray($row, "Text {$locale} of page {$id}.");

        return $row;
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

    /**
     * The form fields of a new page (Catalan only).
     *
     * @param array<string, mixed> $ca
     * @param array<string, mixed> $values
     *
     * @return array<string, mixed>
     */
    protected function form(array $ca, array $values = []): array
    {
        return $values + [
            'kp_tr' => ['ca' => $ca + ['title' => '', 'slug' => '', 'summary' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '', 'canonical' => '']],
            'parent_id' => '', 'position' => '0', 'template' => 'default', 'visibility' => 'public', 'publish_at' => '', 'noindex' => '0',
        ];
    }

    protected function editor(): void
    {
        $this->createUser('ed@example.com', 'editor');
        $this->login('ed@example.com');
    }
}
