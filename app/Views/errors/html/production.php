<?php
/**
 * 500 page in production: the active theme's `errors/500` view (Modules\Core\Theme\ErrorPage),
 * with a plain fallback; never shows the exception.
 */
echo Modules\Core\Theme\ErrorPage::render(500);
