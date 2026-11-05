<?php
/**
 * The user's profile (back office).
 *
 * @var string                            $layout
 * @var \CodeIgniter\Shield\Entities\User $user
 * @var array<string, mixed>              $profile
 * @var list<string>                      $locales
 * @var list<string>                      $timezones
 * @var list<string>                      $modes
 * @var bool                              $canEdit
 * @var array<string, string>             $errors
 */
$value = static fn (string $field, mixed $default): string => (string) (old($field) ?? $default ?? '');
$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$feedback = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback">' . esc($errors[$field]) . '</div>' : '';
$locale = $value('locale', $profile['locale'] ?? service('request')->getLocale());
$zone = $value('timezone', $profile['timezone'] ?? config('App')->appTimezone);
$mode = $value('theme_mode', $profile['theme_mode'] ?? 'auto');
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="row g-4">
    <div class="col-lg-8">
        <?php ob_start() ?>
        <div class="row g-3">
            <div class="col-md-6">
                <label for="name" class="form-label"><?= esc(lang('Auth.profile.name')) ?></label>
                <input type="text" class="form-control<?= $invalid('name') ?>" id="name" name="name" value="<?= esc($value('name', $profile['name'])) ?>" maxlength="150" autocomplete="name">
                <?= $feedback('name') ?>
            </div>
            <div class="col-md-6">
                <label for="email" class="form-label"><?= esc(lang('Auth.profile.email')) ?></label>
                <input type="email" class="form-control<?= $invalid('email') ?>" id="email" name="email" value="<?= esc($value('email', $user->email)) ?>" required maxlength="254" autocomplete="email" aria-describedby="email-help">
                <?= $feedback('email') ?>
                <div id="email-help" class="form-text"><?= esc(lang('Auth.profile.emailHelp')) ?></div>
            </div>
            <div class="col-md-6">
                <label for="locale" class="form-label"><?= esc(lang('Auth.profile.locale')) ?></label>
                <select class="form-select<?= $invalid('locale') ?>" id="locale" name="locale">
                    <?php foreach ($locales as $code): ?>
                        <option value="<?= esc($code) ?>"<?= $code === $locale ? ' selected' : '' ?>><?= esc(lang('Auth.locales.' . $code)) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $feedback('locale') ?>
            </div>
            <div class="col-md-6">
                <label for="timezone" class="form-label"><?= esc(lang('Auth.profile.timezone')) ?></label>
                <select class="form-select<?= $invalid('timezone') ?>" id="timezone" name="timezone">
                    <?php foreach ($timezones as $tz): ?>
                        <option value="<?= esc($tz) ?>"<?= $tz === $zone ? ' selected' : '' ?>><?= esc($tz) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $feedback('timezone') ?>
            </div>
            <div class="col-md-6">
                <label for="theme_mode" class="form-label"><?= esc(lang('Auth.profile.themeMode')) ?></label>
                <select class="form-select<?= $invalid('theme_mode') ?>" id="theme_mode" name="theme_mode">
                    <?php foreach ($modes as $option): ?>
                        <option value="<?= esc($option) ?>"<?= $option === $mode ? ' selected' : '' ?>><?= esc(lang('Auth.modes.' . $option)) ?></option>
                    <?php endforeach ?>
                </select>
                <?= $feedback('theme_mode') ?>
            </div>
            <div class="col-md-6">
                <label for="birth_date" class="form-label"><?= esc(lang('Auth.profile.birthDate')) ?></label>
                <input type="date" class="form-control<?= $invalid('birth_date') ?>" id="birth_date" name="birth_date" value="<?= esc($value('birth_date', $profile['birth_date'])) ?>">
                <?= $feedback('birth_date') ?>
            </div>
            <div class="col-md-6">
                <label for="current_password" class="form-label"><?= esc(lang('Auth.profile.currentPassword')) ?></label>
                <input type="password" class="form-control" id="current_password" name="current_password" autocomplete="current-password" aria-describedby="current-help">
                <div id="current-help" class="form-text"><?= esc(lang('Auth.profile.currentPasswordHelp')) ?></div>
            </div>
        </div>
        <?php $fields = (string) ob_get_clean() ?>
        <?php if ($canEdit): ?>
            <?= theme_component('form_layout', ['action' => site_url('backend/profile'), 'fields' => $fields, 'title' => lang('Auth.profile.details'), 'dirtyGuard' => true]) ?>
        <?php else: ?>
            <?= theme_component('card', ['title' => lang('Auth.profile.details'), 'body' => '<fieldset disabled>' . $fields . '</fieldset>']) ?>
        <?php endif ?>
    </div>
    <div class="col-lg-4">
        <?php ob_start() ?>
        <div class="d-flex align-items-center gap-3 mb-3">
            <?= ui_avatar((string) ($profile['name'] ?? $user->email ?? '?'), null, 'lg') ?>
            <div>
                <div class="fw-semibold"><?= esc((string) ($profile['name'] ?? $user->username ?? '')) ?></div>
                <div class="small text-body-secondary"><?= esc((string) $user->email) ?></div>
            </div>
        </div>
        <p class="small mb-1"><?= esc(lang('Auth.profile.groups')) ?></p>
        <p class="mb-3">
            <?php foreach ($user->getGroups() as $group): ?>
                <?= ui_badge((string) (setting('AuthGroups.groups')[$group]['title'] ?? $group), $group === 'superadmin' ? 'danger' : 'secondary') ?>
            <?php endforeach ?>
        </p>
        <div class="d-grid gap-2">
            <a class="btn btn-outline-primary" href="<?= esc(site_url('backend/profile/password')) ?>"><i class="bi bi-key me-1" aria-hidden="true"></i><?= esc(lang('Auth.profile.changePassword')) ?></a>
            <a class="btn btn-outline-primary" href="<?= esc(site_url('backend/profile/two-factor')) ?>"><i class="bi bi-shield-check me-1" aria-hidden="true"></i><?= esc(lang('Auth.twoFactor.title')) ?></a>
            <a class="btn btn-outline-secondary" href="<?= esc(site_url('backend/profile/sessions')) ?>"><i class="bi bi-laptop me-1" aria-hidden="true"></i><?= esc(lang('Auth.sessions.title')) ?></a>
        </div>
        <?php $side = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('Auth.profile.account'), 'icon' => 'bi bi-person-badge', 'body' => $side]) ?>
    </div>
</div>
<?= $this->endSection() ?>
