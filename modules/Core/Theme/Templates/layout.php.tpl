<?php
/**
 * Layout "{{Layout}}" of the theme {{Name}} (standalone theme: replace it with your own HTML).
 *
 * @var string|null $title
 */
?>
<!doctype html>
<html lang="<?= esc(service('request')->getLocale()) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= esc($title ?? lang('Core.app.name')) ?></title>
<?php foreach (theme()->styles() as $href): ?>
    <link rel="stylesheet" href="<?= esc($href) ?>">
<?php endforeach ?>
</head>
<body class="layout-{{Layout}}">
<main id="main"><?= $this->renderSection('content') ?></main>
<?php foreach (theme()->scripts() as $src): ?>
    <script src="<?= esc($src) ?>" defer></script>
<?php endforeach ?>
</body>
</html>
