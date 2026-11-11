<?php
/**
 * Public layout: header with the site logo and the `primary` menu location (`menu('primary')`,
 * partial `public_menu`), content with an optional sidebar region, footer with the `footer` menu
 * location, SEO meta tags ($meta) and the
 * cookie notice (theme option), theme switch. Regions: header, sidebar, footer, home.
 *
 * @var string|null $title
 */
$font       = preg_replace('/[^a-z0-9-]/', '', (string) theme_option('font'));
$hasSidebar = theme()->regions()->has('sidebar') && theme()->hasRegion('public', 'sidebar');
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>"<?= theme_mode()->attributes() ?>>
<head>
<?= $this->include(theme_resolve('partials/head')) ?>
</head>
<body class="layout-public theme-font-<?= esc($font) ?> d-flex flex-column min-vh-100">
<a class="visually-hidden-focusable skip-link" href="#main"><?= esc(lang('Core.ui.skipToContent')) ?></a>
<header class="site-header border-bottom bg-body">
    <nav class="navbar navbar-expand-md container" aria-label="<?= esc(lang('Core.ui.mainMenu')) ?>">
        <?= theme_partial('brand', ['brandClass' => 'navbar-brand']) ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#site-menu" aria-controls="site-menu" aria-expanded="false" aria-label="<?= esc(lang('Core.ui.openMenu')) ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="site-menu" data-menu-location="primary">
            <?= theme_partial('public_menu', ['location' => 'primary']) ?>
            <?= theme_region('public', 'header') ?>
        </div>
        <?= theme_partial('theme_switch', ['switchClass' => 'site-theme-switch ms-md-2']) ?>
    </nav>
</header>
<main id="main" class="site-main flex-grow-1 py-4" tabindex="-1">
    <div class="container">
        <?= $this->include(theme_resolve('partials/flash')) ?>
        <?= $this->include(theme_resolve('partials/page_top')) ?>
        <?php if ($hasSidebar): ?>
            <div class="row g-4">
                <div class="col-lg-8"><?= $this->renderSection('content') ?></div>
                <aside class="col-lg-4 site-sidebar"><?= theme_region('public', 'sidebar') ?></aside>
            </div>
        <?php else: ?>
            <?= $this->renderSection('content') ?>
        <?php endif ?>
    </div>
</main>
<footer class="site-footer border-top py-4 mt-auto">
    <div class="container d-flex flex-wrap justify-content-between gap-3 small text-body-secondary">
        <div data-menu-location="footer"><?= theme_partial('public_menu', ['location' => 'footer']) ?><?= theme_region('public', 'footer') ?></div>
        <div>
            <?php if ((string) theme_option('footerText') !== ''): ?>
                <?= esc((string) theme_option('footerText')) ?>
            <?php else: ?>
                &copy; <?= date('Y') ?> <?= esc(site_name()) ?>
            <?php endif ?>
        </div>
    </div>
</footer>
<?= $this->include(theme_resolve('partials/cookie_notice')) ?>
<?= $this->include(theme_resolve('partials/scripts')) ?>
</body>
</html>
