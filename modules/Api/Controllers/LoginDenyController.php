<?php

declare(strict_types=1);

namespace Modules\Api\Controllers;

use CodeIgniter\Events\Events;
use Config\Kpapy as KpapyConfig;
use Kpapy\Auth\Challenges;
use Modules\Core\Controllers\FrontendController;

/**
 * "It was not me": the link of the sign-in code email (`auth/api-login/<challenge>`). The page
 * names the device, the address and the time and asks for confirmation; the POST (CSRF) closes
 * the challenge, so the code no longer works. No login is needed: the 192-bit challenge id
 * in the link is the proof, and it can only stop a sign-in.
 */
final class LoginDenyController extends FrontendController
{
    protected function layout(): string
    {
        return theme_layout('auth');
    }

    public function show(string $id): string
    {
        $challenge = $this->challenges()->find($id);

        return $this->render('Modules\Api\Views\deny', [
            'title'     => lang('Api.deny.title'),
            'challenge' => $challenge,
            'id'        => $id,
            'denied'    => false,
        ]);
    }

    public function deny(string $id): string
    {
        $challenges = $this->challenges();
        $challenge  = $challenges->find($id);
        $denied     = $challenge !== null && $challenges->deny($id, (int) $challenge['user_id']);

        if ($denied) {
            log_message('notice', 'User {id} denied an API sign-in from {ip}.', ['id' => $challenge['user_id'], 'ip' => $challenge['ip_address']]);
            Events::trigger('kpapy.auth.denied', ['user_id' => (int) $challenge['user_id'], 'challenge' => $id]);
        }

        return $this->render('Modules\Api\Views\deny', [
            'title'     => lang('Api.deny.title'),
            'challenge' => null,
            'id'        => $id,
            'denied'    => $denied,
        ]);
    }

    private function challenges(): Challenges
    {
        return new Challenges(config(KpapyConfig::class));
    }
}
