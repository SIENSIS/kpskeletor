<?php
/**
 * Authentication layout: a centered card with the site logo (login, register, 2FA, password
 * reset). Region: footer.
 *
 * @var string|null $title
 */
$font = preg_replace('/[^a-z0-9-]/', '', (string) theme_option('font'));
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
<?= $this->include(theme_resolve('partials/head')) ?>
    <meta name="robots" content="noindex">
</head>
<body class="layout-auth theme-font-<?= esc($font) ?> d-flex align-items-center min-vh-100 py-4">
<main id="main" class="container auth-container">
    <div class="text-center mb-4"><?= theme_partial('brand', ['brandClass' => 'auth-brand fs-4']) ?></div>
    <div class="card shadow-sm">
        <div class="card-body p-4">
            <?php if (! empty($title)): ?><h1 class="h4 mb-3 text-center"><?= esc($title) ?></h1><?php endif ?>
            <?= $this->include(theme_resolve('partials/flash')) ?>
            <?= $this->renderSection('content') ?>
        </div>
    </div>
    <div class="text-center small text-body-secondary mt-3"><?= theme_region('auth', 'footer') ?></div>
</main>
<?= $this->include(theme_resolve('partials/scripts')) ?>
</body>
</html>
