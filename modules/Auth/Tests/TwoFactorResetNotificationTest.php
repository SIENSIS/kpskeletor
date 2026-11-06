<?php

declare(strict_types=1);

namespace Modules\Auth\Tests;

use Modules\Auth\Notifications\TwoFactorReset;
use Modules\Auth\Services\TwoFactor;
use Tests\Support\Scheduler\DeclaresFixtures;

/**
 * An administrator's 2FA reset notifies the user (mandatory type: in the application and by
 * email, in the user's language, whatever their preferences).
 *
 * @internal
 */
final class TwoFactorResetNotificationTest extends TwoFactorTestCase
{
    use DeclaresFixtures;

    public function testTheUserIsNotifiedOfAnAdminReset(): void
    {
        $this->declareFixtures();
        $user = $this->createUser();
        $this->enrolTotp($user);
        db_connect()->table('user_profiles')->insert(['user_id' => $user->id, 'locale' => 'ca']);
        db_connect()->table('notification_preferences')->insert(['user_id' => $user->id, 'type' => TwoFactorReset::class, 'channels' => '']);

        (new TwoFactor())->reset($user, 99);

        $notification = db_connect()->table('notifications')->where('user_id', $user->id)->get()->getRowArray();
        $this->assertIsArray($notification);
        $this->assertSame(TwoFactorReset::class, $notification['type']);
        $this->assertSame('Auth', $notification['module']);
        $this->assertSame(['by' => 99], json_decode((string) $notification['data'], true));

        $mail = db_connect()->table('mail_queue')->where('user_id', $user->id)->get()->getRowArray();
        $this->assertIsArray($mail, 'Mandatory: emailed even though the user turned the channels off.');
        $this->assertSame('S\'ha restablert la vostra autenticació en dos passos', $mail['subject']);
        $this->assertStringContainsString(site_url('backend/profile/two-factor'), (string) $mail['body']);
        $this->assertTrue(TwoFactorReset::mandatory());
    }
}
