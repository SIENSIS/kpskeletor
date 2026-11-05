<?php

declare(strict_types=1);

namespace Modules\Api\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\Shield\Entities\User;
use Config\Kpapy as KpapyConfig;
use Kpapy\Auth\Context;
use Kpapy\Http\ApiException;
use Kpapy\Http\Responder;
use Kpapy\Resource;
use Modules\Auth\Models\UserProfileModel;
use Modules\Auth\Services\TwoFactor;
use Throwable;

/**
 * `GET api/v1/me`: the profile of the token's user (any valid token; it never returns secrets):
 * masked id (the same code as in the back office, context `users`), name, email, language,
 * time zone, colour mode, avatar URL (session 23), groups, 2FA method and the token's own name,
 * scopes and expiry.
 */
final class Me extends Controller
{
    public function index(): ResponseInterface
    {
        $config    = config(KpapyConfig::class);
        $responder = new Responder($config, $this->response);

        try {
            $user = Context::user();

            if (! $user instanceof User) {
                throw ApiException::unauthorized();
            }

            $profile = (new UserProfileModel())->forUser((int) $user->id);
            $token   = Context::token();

            return $responder->item([
                'id'         => (new Resource($config))->setTable('users')->encodeId((int) $user->id),
                'username'   => $user->username,
                'email'      => $user->email,
                'name'       => $profile['name'] ?? null,
                'locale'     => $profile['locale'] ?? null,
                'timezone'   => $profile['timezone'] ?? null,
                'theme_mode' => $profile['theme_mode'] ?? null,
                'avatar_url' => null,
                'groups'     => array_values($user->getGroups() ?? []),
                'two_factor' => (new TwoFactor())->method((int) $user->id),
                'token'      => $token === null ? null : [
                    'name'       => (string) $token->name,
                    'scopes'     => array_values((array) $token->scopes),
                    'expires_at' => $token->expires instanceof \DateTimeInterface ? $token->expires->format(DATE_ATOM) : null,
                ],
            ], ['self' => site_url('api/v1/me')]);
        } catch (ApiException $e) {
            return $responder->problem($e);
        } catch (Throwable $e) {
            return $responder->exception($e);
        }
    }
}
