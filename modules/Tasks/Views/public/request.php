<?php
/**
 * The public form "request a to-do task" (tutorial 12) with the always-on captcha (markup of
 * Auth's `_captcha` partial; the layout prints the title with the breadcrumbs, image from `tasks/request/captcha`; component `auth/captcha`).
 *
 * @var string                $layout
 * @var string                $title
 * @var array<string, string> $old
 * @var array<string, string> $errors
 */
$field = static function (string $name) use ($errors): string {
    return isset($errors[$name]) ? ' is-invalid' : '';
};
$error = static function (string $name, string $id) use ($errors): string {
    return isset($errors[$name]) ? '<div class="invalid-feedback" id="' . esc($id, 'attr') . '">' . esc($errors[$name]) . '</div>' : '';
};
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row justify-content-center">
    <div class="col-12 col-lg-8 col-xl-7">
        <p class="text-body-secondary"><?= esc(lang('Tasks.form.intro')) ?></p>
        <?php if ($errors !== []): ?>
            <div class="alert alert-danger" role="alert"><?= esc(lang('Tasks.form.errors')) ?></div>
        <?php endif ?>
        <form method="post" action="<?= esc(site_url('tasks/request'), 'attr') ?>" novalidate>
            <?= csrf_field() ?>
            <div class="row g-3">
                <div class="col-12 col-md-6">
                    <label class="form-label" for="tr-name"><?= esc(lang('Tasks.fields.requesterName')) ?></label>
                    <input class="form-control<?= $field('name') ?>" id="tr-name" name="name" maxlength="120" required autocomplete="name" value="<?= esc($old['name'] ?? '', 'attr') ?>"<?= isset($errors['name']) ? ' aria-describedby="tr-name-error"' : '' ?>>
                    <?= $error('name', 'tr-name-error') ?>
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label" for="tr-email"><?= esc(lang('Tasks.fields.email')) ?></label>
                    <input type="email" class="form-control<?= $field('email') ?>" id="tr-email" name="email" maxlength="190" required autocomplete="email" value="<?= esc($old['email'] ?? '', 'attr') ?>"<?= isset($errors['email']) ? ' aria-describedby="tr-email-error"' : '' ?>>
                    <?= $error('email', 'tr-email-error') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="tr-title"><?= esc(lang('Tasks.fields.title')) ?></label>
                    <input class="form-control<?= $field('title') ?>" id="tr-title" name="title" maxlength="190" required value="<?= esc($old['title'] ?? '', 'attr') ?>"<?= isset($errors['title']) ? ' aria-describedby="tr-title-error"' : '' ?>>
                    <?= $error('title', 'tr-title-error') ?>
                </div>
                <div class="col-12">
                    <label class="form-label" for="tr-details"><?= esc(lang('Tasks.fields.details')) ?></label>
                    <textarea class="form-control<?= $field('details') ?>" id="tr-details" name="details" rows="5" maxlength="5000"<?= isset($errors['details']) ? ' aria-describedby="tr-details-error"' : '' ?>><?= esc($old['details'] ?? '') ?></textarea>
                    <?= $error('details', 'tr-details-error') ?>
                </div>
                <div class="col-12 col-md-6" data-component="auth/captcha">
                    <label for="captcha" class="form-label"><?= esc(lang('Auth.captcha.label')) ?></label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="<?= esc(site_url('tasks/request/captcha'), 'attr') ?>" alt="<?= esc(lang('Auth.captcha.alt'), 'attr') ?>" width="150" height="50" class="border rounded auth-captcha-image" data-captcha-image>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-captcha-reload aria-label="<?= esc(lang('Auth.captcha.reload'), 'attr') ?>" title="<?= esc(lang('Auth.captcha.reload'), 'attr') ?>"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                    </div>
                    <input type="text" inputmode="numeric" class="form-control<?= $field('captcha') ?>" id="captcha" name="captcha" required autocomplete="off" maxlength="4"<?= isset($errors['captcha']) ? ' aria-describedby="tr-captcha-error"' : '' ?>>
                    <?= $error('captcha', 'tr-captcha-error') ?>
                    <div class="form-text"><?= esc(lang('Auth.captcha.help')) ?></div>
                </div>
            </div>
            <p class="small text-body-secondary mt-3"><?= esc(lang('Tasks.form.privacy')) ?></p>
            <button type="submit" class="btn btn-primary"><i class="bi bi-send" aria-hidden="true"></i> <?= esc(lang('Tasks.form.submit')) ?></button>
        </form>
    </div>
</div>
<?= $this->endSection() ?>
