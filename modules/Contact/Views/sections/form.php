<?php
/**
 * Section `contact.form`: a heading, an introduction and the contact form (the recipients may be
 * restricted by the section). Override: `themes/<slug>/Views/overrides/Contact/sections/form.php`.
 *
 * @var array<string, mixed> $values heading, intro, recipients
 * @var array<string, mixed> $form   ContactForm::data()
 */
?>
<div class="row justify-content-center kp-contact-section"><div class="col-12 col-lg-9 col-xl-8">
    <?= theme_view('Modules\Core\Views\sections\heading', ['heading' => (string) $values['heading'], 'intro' => (string) $values['intro'], 'centered' => false]) ?>
    <?= theme_view('Modules\Contact\Views\form', $form) ?>
</div></div>
