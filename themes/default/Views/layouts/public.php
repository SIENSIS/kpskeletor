<?php
/**
 * Public layout: header with the site logo and the `primary` menu location (`menu('primary')`,
 * partial `public_menu`), content with an optional sidebar region, footer with the `footer` menu
 * location, SEO meta tags ($meta) and the
 * cookie notice (theme option), language switch, theme switch. Regions: header, sidebar, footer, home.
 * Zones (D-47-01): `public.after_header` and `public.before_footer` inside `<main>`, filled by
 * the Pages module's reusable blocks (`theme_zone()`); the footer (partial `footer`) shows the
 * footer menu in columns when its items have children.
 * Full-width mode (`$fullWidth`, pages built from sections, D-45-01): no outer container, no
 * sidebar and no vertical padding, so banners reach the edges; each section adds its own container.
 *
 * @var string|null $title
 * @var bool|null   $fullWidth
 */
$font       = preg_replace('/[^a-z0-9-]/', '', (string) theme_option('font'));
$fullWidth  = ! empty($fullWidth);
$hasSidebar = ! $fullWidth && theme()->regions()->has('sidebar') && theme()->hasRegion('public', 'sidebar');
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
        <?= theme_partial('brand', ['brandClass' => 'navbar-brand', 'brandUrl' => site_url('/')]) ?>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#site-menu" aria-controls="site-menu" aria-expanded="false" aria-label="<?= esc(lang('Core.ui.openMenu')) ?>">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="site-menu" data-menu-location="primary">
            <?= theme_partial('public_menu', ['location' => 'primary']) ?>
            <?= theme_region('public', 'header') ?>
            <?= theme_partial('account_link') ?>
        </div>
        <?= theme_partial('language_switch', ['switchClass' => 'site-language-switch ms-md-2']) ?>
        <?= theme_partial('theme_switch', ['switchClass' => 'site-theme-switch ms-md-1']) ?>
    </nav>
</header>
<main id="main" class="site-main flex-grow-1<?= $fullWidth ? ' site-main-full' : ' py-4' ?>" tabindex="-1">
    <?php if (($zone = theme_zone('public.after_header')) !== ''): ?>
        <div class="site-zone site-zone-after-header<?= $fullWidth ? '' : ' mb-4' ?>"><?= $zone ?></div>
    <?php endif ?>
    <?php if ($fullWidth): ?>
        <div class="container site-main-notices">
            <?= $this->include(theme_resolve('partials/flash')) ?>
            <?= $this->include(theme_resolve('partials/page_top')) ?>
        </div>
        <?= $this->renderSection('content') ?>
    <?php else: ?>
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
    <?php endif ?>
    <?php if (($zone = theme_zone('public.before_footer')) !== ''): ?>
        <div class="site-zone site-zone-before-footer"><?= $zone ?></div>
    <?php endif ?>
</main>
<?= theme_partial('footer') ?>
<?= $this->include(theme_resolve('partials/cookie_notice')) ?>
<?= $this->include(theme_resolve('partials/scripts')) ?>
</body>
</html>
