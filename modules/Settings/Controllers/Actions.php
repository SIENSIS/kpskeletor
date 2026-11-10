<?php

declare(strict_types=1);

namespace Modules\Settings\Controllers;

use CodeIgniter\HTTP\RedirectResponse;
use Modules\Core\Cache\CacheClearer;
use Modules\Core\Controllers\BackendController;
use Modules\Core\Frontend\AssetPublisher;
use Modules\Core\Mail\MailQueue;
use Modules\Core\Modules\CacheInvalidator;
use Modules\Settings\Services\SettingsTabs;
use Throwable;

/**
 * The maintenance actions of the Settings module (docs/settings.md), each logged:
 *
 * - send a test email to the logged-in user (`settings.update`): queued in `mail_queue` and sent
 *   by the scheduled task SendQueuedEmails, like every non-security email (D-00-15);
 * - clear caches (`settings.update`): the Core's cache scopes (permissions, menus, settings,
 *   widgets, assets, modules) and the theme registry; never the whole cache store, which also
 *   holds the rate limiter's counters;
 * - publish assets (`settings.security` + re-authentication): modules' and themes' assets into
 *   `public/assets/…` and `public/themes/` only (D-00-33), like `php spark assets:publish`.
 */
final class Actions extends BackendController
{
    public function index(): string
    {
        $this->breadcrumb(lang('Settings.title'), site_url('backend/settings'))->breadcrumb(lang('Settings.tabs.actions'));

        return $this->render('Modules\Settings\Views\actions', [
            'title'       => lang('Settings.title'),
            'icon'        => 'bi bi-gear',
            'tabs'        => (new SettingsTabs())->build('actions', $this->can('settings.security')),
            'email'       => (string) auth()->user()?->email,
            'pending'     => $this->pendingEmails(),
            'canPublish'  => $this->can('settings.security'),
        ]);
    }

    public function testEmail(): RedirectResponse
    {
        $user  = auth()->user();
        $email = (string) $user?->email;

        if ($email === '') {
            return $this->back('error', lang('Settings.actions.noEmail'));
        }

        $subject = lang('Settings.actions.emailSubject', [site_name()]);
        $body    = view('Modules\Settings\Views\emails\test', ['subject' => $subject, 'site' => site_name()], ['debug' => false]);

        if (! (new MailQueue())->push($email, $subject, $body, (int) $user->id)) {
            return $this->back('error', lang('Settings.actions.emailFailed'));
        }

        activity_log('Settings', 'test_email', ['resource' => 'settings', 'summary' => $email]);

        return $this->back('success', lang('Settings.actions.emailQueued', [$email]));
    }

    public function clearCaches(): RedirectResponse
    {
        (new CacheClearer())->clear();

        activity_log('Settings', 'caches_cleared', ['resource' => 'settings', 'changes' => ['scopes' => CacheInvalidator::SCOPES]]);

        return $this->back('success', lang('Settings.actions.cachesCleared'));
    }

    public function publishAssets(): RedirectResponse
    {
        try {
            $changes = (new AssetPublisher())->publish();
        } catch (Throwable $e) {
            security_log('Settings', 'assets_publish_failed', ['resource' => 'settings', 'summary' => $e->getMessage()]);

            return $this->back('error', lang('Settings.actions.assetsFailed'));
        }

        (new CacheInvalidator())->invalidate(['assets']);
        security_log('Settings', 'assets_published', ['resource' => 'settings', 'changes' => ['owners' => $changes]]);

        return $this->back('success', lang('Settings.actions.assetsPublished', [array_sum(array_diff_key($changes, ['manifest' => 0]))]));
    }

    private function pendingEmails(): int
    {
        try {
            return (new MailQueue())->pendingCount();
        } catch (Throwable) {
            return 0;
        }
    }

    private function back(string $type, string $message): RedirectResponse
    {
        return redirect()->to(site_url('backend/settings/actions'))->with($type, $message);
    }
}
