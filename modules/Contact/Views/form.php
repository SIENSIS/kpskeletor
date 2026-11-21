<?php
/**
 * The contact form, shared by the page `contact` and the section `contact.form`
 * (`Modules\Contact\Services\ContactForm::data()`). The real addresses of the recipients never
 * appear here; the honeypot input is hidden from people (visually hidden, out of the tab order,
 * hidden from screen readers); no inline script or style. Override:
 * `themes/<slug>/Views/overrides/Contact/form.php`.
 *
 * @var list<array{id: int, name: string}> $recipients
 * @var string                             $token
 * @var bool                               $captcha
 * @var string|null                        $privacyUrl
 * @var string                             $prefix     id prefix (one per form on the page)
 * @var array<string, string>              $old
 * @var array<string, string>              $errors
 * @var string|null                        $alert
 * @var string                             $action
 */
$id    = static fn (string $name): string => esc($prefix . '-' . $name, 'attr');
$field = static fn (string $name): string => isset($errors[$name]) ? ' is-invalid' : '';
$error = static function (string $name) use ($errors, $id): string {
    return isset($errors[$name]) ? '<div class="invalid-feedback" id="' . $id($name) . '-error">' . esc($errors[$name]) . '</div>' : '';
};
$described = static fn (string $name): string => isset($errors[$name]) ? ' aria-describedby="' . $id($name) . '-error"' : '';
$selected  = $old['recipient'] ?? (count($recipients) === 1 ? (string) $recipients[0]['id'] : '');
?>
<?php if ($recipients === []): ?>
    <p class="text-body-secondary"><?= esc(lang('Contact.form.closed')) ?></p>
<?php else: ?>
    <?php if ($alert !== null): ?>
        <div class="alert alert-danger" role="alert"><?= esc($alert) ?></div>
    <?php elseif ($errors !== []): ?>
        <div class="alert alert-danger" role="alert"><?= esc(lang('Contact.form.errors')) ?></div>
    <?php endif ?>
    <form method="post" action="<?= esc($action, 'attr') ?>" class="kp-contact-form" id="<?= $id('form') ?>" novalidate>
        <?= csrf_field() ?>
        <input type="hidden" name="kp_token" value="<?= esc($token, 'attr') ?>">
        <div class="visually-hidden" aria-hidden="true">
            <label for="<?= $id('website') ?>"><?= esc(lang('Contact.form.honeypot')) ?></label>
            <input type="text" id="<?= $id('website') ?>" name="<?= esc(\Modules\Contact\Controllers\ContactPage::HONEYPOT, 'attr') ?>" value="" tabindex="-1" autocomplete="off">
        </div>
        <div class="row g-3">
            <div class="col-12 col-md-6">
                <label class="form-label" for="<?= $id('name') ?>"><?= esc(lang('Contact.fields.name')) ?></label>
                <input class="form-control<?= $field('name') ?>" id="<?= $id('name') ?>" name="name" maxlength="120" required autocomplete="name" value="<?= esc($old['name'] ?? '', 'attr') ?>"<?= $described('name') ?>>
                <?= $error('name') ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="<?= $id('email') ?>"><?= esc(lang('Contact.fields.email')) ?></label>
                <input type="email" class="form-control<?= $field('email') ?>" id="<?= $id('email') ?>" name="email" maxlength="190" required autocomplete="email" value="<?= esc($old['email'] ?? '', 'attr') ?>"<?= $described('email') ?>>
                <?= $error('email') ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="<?= $id('recipient') ?>"><?= esc(lang('Contact.fields.recipient')) ?></label>
                <select class="form-select<?= $field('recipient') ?>" id="<?= $id('recipient') ?>" name="recipient" required<?= $described('recipient') ?>>
                    <?php if (count($recipients) > 1): ?><option value=""><?= esc(lang('Contact.form.choose')) ?></option><?php endif ?>
                    <?php foreach ($recipients as $recipient): ?>
                        <option value="<?= (int) $recipient['id'] ?>"<?= $selected === (string) $recipient['id'] ? ' selected' : '' ?>><?= esc($recipient['name']) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $error('recipient') ?>
            </div>
            <div class="col-12 col-md-6">
                <label class="form-label" for="<?= $id('subject') ?>"><?= esc(lang('Contact.fields.subject')) ?></label>
                <input class="form-control<?= $field('subject') ?>" id="<?= $id('subject') ?>" name="subject" maxlength="190" required value="<?= esc($old['subject'] ?? '', 'attr') ?>"<?= $described('subject') ?>>
                <?= $error('subject') ?>
            </div>
            <div class="col-12">
                <label class="form-label" for="<?= $id('message') ?>"><?= esc(lang('Contact.fields.message')) ?></label>
                <textarea class="form-control<?= $field('message') ?>" id="<?= $id('message') ?>" name="message" rows="6" maxlength="5000" required<?= $described('message') ?>><?= esc($old['message'] ?? '') ?></textarea>
                <?= $error('message') ?>
            </div>
            <?php if ($captcha): ?>
                <div class="col-12 col-md-6" data-component="auth/captcha">
                    <label for="<?= $id('captcha') ?>" class="form-label"><?= esc(lang('Auth.captcha.label')) ?></label>
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <img src="<?= esc(site_url('contact/captcha'), 'attr') ?>" alt="<?= esc(lang('Auth.captcha.alt'), 'attr') ?>" width="150" height="50" class="border rounded auth-captcha-image" data-captcha-image>
                        <button type="button" class="btn btn-outline-secondary btn-sm" data-captcha-reload aria-label="<?= esc(lang('Auth.captcha.reload'), 'attr') ?>" title="<?= esc(lang('Auth.captcha.reload'), 'attr') ?>"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i></button>
                    </div>
                    <input type="text" inputmode="numeric" class="form-control<?= $field('captcha') ?>" id="<?= $id('captcha') ?>" name="captcha" required autocomplete="off" maxlength="4"<?= $described('captcha') ?>>
                    <?= $error('captcha') ?>
                    <div class="form-text"><?= esc(lang('Auth.captcha.help')) ?></div>
                </div>
            <?php endif ?>
            <div class="col-12">
                <div class="form-check">
                    <input class="form-check-input<?= $field('privacy') ?>" type="checkbox" value="1" id="<?= $id('privacy') ?>" name="privacy" required<?= ($old['privacy'] ?? '') === '1' ? ' checked' : '' ?><?= $described('privacy') ?>>
                    <label class="form-check-label" for="<?= $id('privacy') ?>">
                        <?php if ($privacyUrl !== null): ?>
                            <?= esc(lang('Contact.form.privacyAccept')) ?> <a href="<?= esc($privacyUrl, 'attr') ?>"><?= esc(lang('Contact.form.privacyLink')) ?></a>
                        <?php else: ?>
                            <?= esc(lang('Contact.form.privacyAcceptPlain')) ?>
                        <?php endif ?>
                    </label>
                    <?= $error('privacy') ?>
                </div>
                <p class="small text-body-secondary mt-2 mb-0"><?= esc(lang('Contact.form.privacyInfo', [max(30, (int) setting('Contact.retentionDays'))])) ?></p>
            </div>
        </div>
        <button type="submit" class="btn btn-primary mt-3"><i class="bi bi-send" aria-hidden="true"></i> <?= esc(lang('Contact.form.submit')) ?></button>
    </form>
<?php endif ?>
