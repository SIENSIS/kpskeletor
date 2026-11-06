<?php

declare(strict_types=1);

namespace Modules\Settings\Tests;

use Modules\Core\Theme\ThemeOptions;

/**
 * The actions (test email, clear caches, publish assets) and the theme tab.
 *
 * @internal
 */
final class SettingsActionsTest extends SettingsTestCase
{
    public function testTestEmailIsQueuedForTheUser(): void
    {
        $user = $this->loginWith('settings.update');

        $this->send('GET', 'backend/settings/actions')->assertOK();
        $this->send('POST', 'backend/settings/actions/email')->assertRedirectTo(site_url('backend/settings/actions'));

        $queued = db_connect()->table('mail_queue')->get()->getResultArray();
        $this->assertCount(1, $queued);
        $this->assertSame(['ana@example.com', (int) $user->id, 'pending'], [$queued[0]['to_email'], (int) $queued[0]['user_id'], $queued[0]['status']]);
        $this->assertSame([], $this->sentEmails, 'Never sent inline.');
        $this->assertCount(1, $this->entries('test_email'));
    }

    public function testClearCachesNeedsSettingsUpdate(): void
    {
        $this->loginWith();
        $this->send('POST', 'backend/settings/actions/caches')->assertStatus(403);

        $_SESSION      = [];
        $this->cookies = [];
        $this->createUser('bea@example.com', 'user')->addPermission('settings.update');
        $this->login('bea@example.com');
        $this->send('POST', 'backend/settings/actions/caches')->assertRedirectTo(site_url('backend/settings/actions'));
        $this->assertCount(1, $this->entries('caches_cleared'));
    }

    public function testPublishingAssetsNeedsSettingsSecurity(): void
    {
        $this->loginWith('settings.update');

        $this->send('POST', 'backend/settings/actions/assets')->assertStatus(403);
        $this->assertSame([], $this->entries('assets_published'));
        $this->assertDoesNotMatchRegularExpression('#action="[^"]*actions/assets"#', (string) $this->send('GET', 'backend/settings/actions')->getBody());
    }

    public function testThemeOptionsNeedSettingsCoreAndAreStoredPerTheme(): void
    {
        $this->loginWith('settings.update');
        $this->assertMatchesRegularExpression('/name="option-primaryColor"[^>]*disabled/', (string) $this->send('GET', 'backend/settings/theme')->getBody());
        $this->send('POST', 'backend/settings/theme', ['Theme-backend' => 'default', 'Theme-public' => 'default', 'option-primaryColor' => '#6f42c1'])->assertStatus(403);
        $this->assertSame(0, $this->storedCount('Config\Theme', 'primaryColor', 'theme:default'));

        $_SESSION      = [];
        $this->cookies = [];
        $this->createUser('bea@example.com', 'user')->addPermission('settings.update', 'settings.core');
        $this->login('bea@example.com');

        $form = ['Theme-backend' => 'default', 'Theme-public' => 'default', 'option-primaryColor' => '#6f42c1', 'option-defaultMode' => 'auto', 'option-font' => 'system', 'option-modeSwitch' => '1'];
        $this->send('POST', 'backend/settings/theme?theme=default', $form)->assertRedirect();

        $this->assertSame('#6f42c1', service('settings')->get('Theme.primaryColor', ThemeOptions::context('default')));
        $this->assertSame(0, db_connect()->table('settings')->where('key', 'backend')->countAllResults(), 'The default theme is not stored.');
        $this->assertCount(1, $this->entries('settings_changed'));

        $this->send('POST', 'backend/settings/theme?theme=default', ['option-primaryColor' => 'red;}'] + $form)->assertStatus(422);
        $this->send('POST', 'backend/settings/theme?theme=default', ['Theme-backend' => 'missing'] + $form)->assertStatus(422);
    }
}
