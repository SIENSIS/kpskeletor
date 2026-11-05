<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * The captcha image (a new challenge on every request; 404 while `Auth.captcha` is off).
 */
final class CaptchaController extends AuthController
{
    public function image(): ResponseInterface
    {
        if (! $this->captchaService()->enabled()) {
            throw PageNotFoundException::forPageNotFound();
        }

        return $this->response
            ->setHeader('Content-Type', 'image/png')
            ->setHeader('Cache-Control', 'no-store, max-age=0')
            ->setBody($this->captchaService()->image());
    }
}
