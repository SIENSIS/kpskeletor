<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use Modules\Pages\Settings\HomePageOptions;
use Modules\Settings\Tests\SettingsTestCase;

/**
 * Settings › Pages: the home page is chosen from a list of the pages (dynamic options of a
 * `select` setting, D-20-01); only one of them (or "the theme's home") is accepted.
 *
 * @internal
 */
final class HomePageSettingTest extends SettingsTestCase
{
    protected function tearDown(): void
    {
        service('settings')->forget('Pages.homePage');
        parent::tearDown();
    }

    public function testTheHomePageIsChosenFromTheListOfPages(): void
    {
        $db = db_connect();
        $db->table('pages')->insert(['status' => 'published', 'visibility' => 'public', 'template' => 'default']);
        $about = (int) $db->insertID();
        $db->table('page_translations')->insert(['page_id' => $about, 'locale' => 'ca', 'title' => 'Qui som', 'slug' => 'qui-som']);
        $db->table('pages')->insert(['parent_id' => $about, 'status' => 'draft', 'visibility' => 'public', 'template' => 'default']);
        $team = (int) $db->insertID();
        $db->table('page_translations')->insert(['page_id' => $team, 'locale' => 'ca', 'title' => 'Equip', 'slug' => 'equip']);

        $this->assertSame(['' => lang('Pages.settings.homePageNone'), (string) $about => 'Qui som', (string) $team => '— Equip'], (new HomePageOptions())->options());

        $this->createUser('boss@example.com', 'admin');
        $this->login('boss@example.com');

        $form = html_entity_decode((string) $this->send('GET', 'backend/settings/pages')->response()->getBody());
        $this->assertMatchesRegularExpression('/<select[^>]*name="Pages-homePage"/', $form);
        $this->assertStringContainsString('<option value="' . $about . '">Qui som</option>', $form, 'Titles are shown as they are.');

        $this->send('POST', 'backend/settings/pages', ['Pages-homePage' => (string) $about])->assertRedirectTo(site_url('backend/settings/pages'));
        $this->assertSame((string) $about, (string) setting('Pages.homePage'));

        $this->send('POST', 'backend/settings/pages', ['Pages-homePage' => '999999'])->assertStatus(422);
        $this->send('POST', 'backend/settings/pages', ['Pages-homePage' => 'qui-som'])->assertStatus(422);
        $this->assertSame((string) $about, (string) setting('Pages.homePage'), 'A value that is not a page is refused.');
    }
}
