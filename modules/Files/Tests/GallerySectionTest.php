<?php

declare(strict_types=1);

namespace Modules\Files\Tests;

use CodeIgniter\I18n\Time;
use Modules\Core\Sections\SectionData;
use Modules\Pages\Services\PageSections;

/**
 * Gallery sections `files.gallery` (session 46, D-46-04): a folder of the public disk is shown to
 * everyone who reads the page; a folder of the private disk only to signed-in visitors with the
 * `view` right of the folder ACL, never to guests; SVG files are never shown; the editor may only
 * choose a folder they may view.
 *
 * @internal
 */
final class GallerySectionTest extends FilesTestCase
{
    private function page(string $slug): int
    {
        $now = Time::now()->toDateTimeString();
        db_connect()->table('pages')->insert(['parent_id' => null, 'position' => 0, 'template' => 'default', 'layout' => 'sections', 'status' => 'published', 'publish_at' => Time::now()->subHours(1)->toDateTimeString(), 'visibility' => 'public', 'noindex' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $id = (int) db_connect()->insertID();
        db_connect()->table('page_translations')->insert(['page_id' => $id, 'locale' => service('request')->getLocale(), 'title' => ucfirst($slug), 'slug' => $slug, 'summary' => '', 'body' => '', 'meta_title' => '', 'meta_description' => '', 'canonical' => '']);

        return $id;
    }

    private function gallery(int $page, string $folder, string $heading): int
    {
        return (new PageSections())->save($page, new SectionData('files.gallery', ['folder' => $folder, 'columns' => '4', 'limit' => 24, 'order' => 'name', 'captions' => true], ['ca' => ['heading' => $heading, 'intro' => ''], 'es' => ['heading' => $heading, 'intro' => ''], 'en' => ['heading' => $heading, 'intro' => '']], ['style' => 'grid']));
    }

    private function body(string $path): string
    {
        return html_entity_decode((string) $this->send('GET', $path)->response()->getBody(), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    public function testPublicFoldersForEveryonePrivateFoldersOnlyWithTheAcl(): void
    {
        $editor = $this->createUser('ed@example.com', 'editor');
        $this->createUser('usu@example.com', 'user');
        $this->folder('public', 'shared', []);
        $this->folder('public', 'shared/Fotos', [['group', 'editor', 'view,upload']]);
        $this->folder('private', 'shared', []);
        $this->folder('private', 'shared/Interna', [['group', 'editor', 'view,upload']]);
        $this->upload($editor, 'public', 'shared/Fotos', self::png(20, 10), 'Pati.png');
        $this->upload($editor, 'private', 'shared/Interna', self::png(20, 10), 'Secret.png');

        $page = $this->page('galeria');
        $this->gallery($page, 'public:shared/Fotos', 'Fotos del pati');
        $this->gallery($page, 'private:shared/Interna', 'Fotos internes');

        $guest = $this->body('galeria');
        $this->assertStringContainsString('Fotos del pati', $guest);
        $this->assertStringContainsString('Pati', $guest);
        $this->assertStringContainsString('data-component="files/gallery"', $guest);
        $this->assertStringNotContainsString('Fotos internes', $guest, 'A private folder never appears to a guest.');
        $this->assertStringNotContainsString('Secret', $guest);

        $this->login('usu@example.com');
        $this->assertStringNotContainsString('Secret', $this->body('galeria'), 'Nor to a user without the view right.');

        $this->login('ed@example.com');
        $body = $this->body('galeria');
        $this->assertStringContainsString('Fotos internes', $body, 'The folder ACL lets the editor see it.');
        $this->assertStringContainsString('files/private/', $body);
    }

    public function testTheEditorChoosesOnlyFoldersTheyMayView(): void
    {
        $this->createUser('ed@example.com', 'editor');
        $this->folder('private', 'shared', []);
        $this->folder('private', 'shared/Direccio', [['group', 'admin', 'view']]);
        $this->folder('public', 'shared', []);
        $this->folder('public', 'shared/Fotos', [['group', 'editor', 'view']]);
        $page = $this->page('galeria');
        $this->login('ed@example.com');

        $form = $this->body('backend/pages/' . $page . '/sections/new/files.gallery');
        $this->assertStringContainsString('value="public:shared/Fotos"', $form);
        $this->assertStringNotContainsString('shared/Direccio', $form, 'Folders the editor may not view are not offered.');

        $forged = $this->send('POST', 'backend/pages/' . $page . '/sections/save', ['type' => 'files.gallery', 'f' => ['folder' => 'private:shared/Direccio']]);
        $this->assertSame(422, $forged->response()->getStatusCode());
        $this->assertSame(422, $this->send('POST', 'backend/pages/' . $page . '/sections/save', ['type' => 'files.gallery', 'f' => ['folder' => 'private:users/1']])->response()->getStatusCode());
        $this->assertTrue($this->send('POST', 'backend/pages/' . $page . '/sections/save', ['type' => 'files.gallery', 'f' => ['folder' => 'public:shared/Fotos']])->isRedirect());
        $this->assertSame(1, (new PageSections())->count($page));
    }
}
