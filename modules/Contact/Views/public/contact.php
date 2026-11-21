<?php
/**
 * The page `contact` (the layout prints the title with the breadcrumbs). Override:
 * `themes/<slug>/Views/overrides/Contact/public/contact.php`.
 *
 * @var string               $layout
 * @var array<string, mixed> $form   ContactForm::data()
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8 col-xl-7">
        <p class="text-body-secondary"><?= esc(lang('Contact.page.intro')) ?></p>
        <?= theme_view('Modules\Contact\Views\form', $form) ?>
    </div>
</div>
<?= $this->endSection() ?>
