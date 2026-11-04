<?php
/**
 * Back-office layout: collapsible sidebar (offcanvas below the lg breakpoint), top bar, page
 * header or breadcrumbs, session messages, the `content` section and a footer.
 * Regions: sidebar (menus), topbar, footer. kpcrudy uses it with the section `content`.
 *
 * @var string|null $title
 */
$font = preg_replace('/[^a-z0-9-]/', '', (string) theme_option('font'));
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<?= $this->include(theme_resolve('partials/head')) ?>
</head>
<body class="layout-backend theme-font-<?= esc($font) ?>">
<a class="visually-hidden-focusable skip-link" href="#main"><?= esc(lang('Core.ui.skipToContent')) ?></a>
<div class="backend-shell">
    <aside class="sidebar offcanvas-lg offcanvas-start" id="backend-sidebar" tabindex="-1" aria-labelledby="backend-sidebar-title">
        <div class="sidebar-header d-flex align-items-center justify-content-between px-3">
            <h2 class="h6 mb-0" id="backend-sidebar-title"><?= theme_partial('brand', ['brandUrl' => base_url('backend'), 'brandClass' => 'sidebar-brand']) ?></h2>
            <button type="button" class="btn-close btn-close-white d-lg-none" data-bs-dismiss="offcanvas" data-bs-target="#backend-sidebar" aria-label="<?= esc(lang('Core.ui.close')) ?>"></button>
        </div>
        <nav class="sidebar-nav flex-grow-1 overflow-y-auto px-2 py-3" aria-label="<?= esc(lang('Core.ui.mainMenu')) ?>">
            <?= theme_region('backend', 'sidebar') ?>
        </nav>
    </aside>
    <div class="backend-main">
        <header class="topbar navbar bg-body border-bottom sticky-top px-3">
            <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#backend-sidebar" aria-controls="backend-sidebar" aria-label="<?= esc(lang('Core.ui.openMenu')) ?>">
                <i class="bi bi-list" aria-hidden="true"></i>
            </button>
            <div class="topbar-region d-flex align-items-center gap-2 flex-grow-1 min-w-0">
                <?= theme_region('backend', 'topbar') ?>
            </div>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <?= theme_partial('user_menu') ?>
            </div>
        </header>
        <main id="main" class="backend-content flex-grow-1" tabindex="-1">
            <?= $this->include(theme_resolve('partials/flash')) ?>
            <?= $this->include(theme_resolve('partials/page_top')) ?>
            <?= $this->renderSection('content') ?>
        </main>
        <footer class="backend-footer border-top small text-body-secondary px-3 py-2">
            <?= theme_region('backend', 'footer') ?>
            <?php if ((string) theme_option('footerText') !== ''): ?><span><?= esc((string) theme_option('footerText')) ?></span><?php endif ?>
        </footer>
    </div>
</div>
<?= $this->include(theme_resolve('partials/scripts')) ?>
</body>
</html>
