<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RedirectResponse;
use Modules\Auth\Services\Impersonation;

/**
 * The way back of an impersonation (`POST auth/impersonation/stop`, the banner's button): the
 * impersonator gets their own account back. Outside an impersonation it only redirects.
 */
final class ImpersonationController extends Controller
{
    public function stop(): RedirectResponse
    {
        if ((new Impersonation())->stop(Impersonation::END_STOPPED) === null) {
            return redirect()->to(site_url('login'));
        }

        $back = route_to('users-users');

        return redirect()->to(is_string($back) && $back !== '' ? $back : site_url('backend/profile'))->with('message', lang('Auth.impersonation.stopped'));
    }
}
