<?php
/**
 * Print layout: no navigation and no scripts; the site name and the title on top, the print date
 * at the bottom (reports, invoices, lists).
 *
 * @var string|null $title
 */
$font = preg_replace('/[^a-z0-9-]/', '', (string) theme_option('font'));
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>"<?= theme_mode()->attributes('light') ?>>
<head>
<?= $this->include(theme_resolve('partials/head')) ?>
    <meta name="robots" content="noindex">
</head>
<body class="layout-print theme-font-<?= esc($font) ?>">
<main id="main" class="print-page container-fluid py-3">
    <header class="d-flex justify-content-between align-items-end border-bottom pb-2 mb-3">
        <span class="fw-semibold"><?= esc(lang('Core.app.name')) ?></span>
        <?php if (! empty($title)): ?><h1 class="h4 mb-0"><?= esc($title) ?></h1><?php endif ?>
    </header>
    <?= $this->renderSection('content') ?>
    <footer class="border-top pt-2 mt-3 small text-body-secondary"><?= esc(lang('Core.ui.printedOn')) ?> <?= ui_date(CodeIgniter\I18n\Time::now(), 'datetime') ?></footer>
</main>
</body>
</html>
