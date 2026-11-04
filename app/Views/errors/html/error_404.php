<?php
/**
 * 404 page: the active theme's `errors/404` view (Modules\Core\Theme\ErrorPage), with a plain
 * fallback. The exception message is only shown outside production.
 *
 * @var string $message
 */
echo Modules\Core\Theme\ErrorPage::render(404, ENVIRONMENT !== 'production' && isset($message) && $message !== '' ? $message : null);
