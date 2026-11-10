<?php
/**
 * Web installer: the form. Passwords are never sent back.
 *
 * @var string                                  $base
 * @var Modules\Core\Install\InstallOptions     $options
 * @var array<string, string>                   $errors
 * @var string                                  $message
 * @var bool                                    $secure
 * @var bool                                    $rootWritable
 * @var string                                  $envPath
 * @var int                                     $minPassword
 */

$invalid = static fn (string $field): string => isset($errors[$field]) ? ' is-invalid' : '';
$error   = static fn (string $field): string => isset($errors[$field]) ? '<div class="invalid-feedback" id="' . esc($field) . '-error">' . esc($errors[$field]) . '</div>' : '';
$aria    = static fn (string $field): string => isset($errors[$field]) ? ' aria-invalid="true" aria-describedby="' . esc($field) . '-error"' : '';
?>
<?= view('Modules\Core\Views\install\_head', ['base' => $base, 'title' => lang('Install.web.title')]) ?>
<main class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9 col-xl-8">
            <h1 class="h3 mb-1"><i class="bi bi-box-seam me-2" aria-hidden="true"></i><?= esc(lang('Install.web.title')) ?></h1>
            <p class="text-body-secondary"><?= esc(lang('Install.web.intro')) ?></p>

            <?php if (! $secure) : ?>
                <div class="alert alert-warning" role="alert"><i class="bi bi-shield-exclamation me-1" aria-hidden="true"></i><strong><?= esc(lang('Install.web.httpsTitle')) ?></strong> <?= esc(lang('Install.web.httpsWarning')) ?></div>
            <?php endif ?>

            <?php if (! $rootWritable) : ?>
                <div class="alert alert-info" role="status"><i class="bi bi-info-circle me-1" aria-hidden="true"></i><?= esc(lang('Install.web.rootNotWritable', [$envPath])) ?></div>
            <?php endif ?>

            <?php if ($message !== '' || $errors !== []) : ?>
                <div class="alert alert-danger" role="alert"><?= esc($message !== '' ? $message : lang('Install.error.invalid')) ?></div>
            <?php endif ?>

            <form method="post" action="" novalidate>
                <?= csrf_field() ?>

                <div class="card mb-3">
                    <div class="card-header fw-semibold"><?= esc(lang('Install.web.site')) ?></div>
                    <div class="card-body row g-3">
                        <div class="col-12 col-md-8">
                            <label for="baseURL" class="form-label"><?= esc(lang('Install.form.baseURL')) ?></label>
                            <input type="url" class="form-control<?= $invalid('baseURL') ?>" id="baseURL" name="baseURL" value="<?= esc($options->baseURL) ?>" required<?= $aria('baseURL') ?>>
                            <?= $error('baseURL') ?>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="environment" class="form-label"><?= esc(lang('Install.form.environment')) ?></label>
                            <select class="form-select<?= $invalid('environment') ?>" id="environment" name="environment"<?= $aria('environment') ?>>
                                <?php foreach (Modules\Core\Install\InstallOptions::ENVIRONMENTS as $environment) : ?>
                                    <option value="<?= esc($environment) ?>"<?= $options->environment === $environment ? ' selected' : '' ?>><?= esc(lang('Install.form.env' . ucfirst($environment))) ?></option>
                                <?php endforeach ?>
                            </select>
                            <?= $error('environment') ?>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header fw-semibold"><?= esc(lang('Install.web.database')) ?></div>
                    <div class="card-body row g-3">
                        <div class="col-12">
                            <span class="form-label d-block"><?= esc(lang('Install.form.dbDriver')) ?></span>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="dbDriver" id="dbDriverMysql" value="MySQLi"<?= $options->isSqlite() ? '' : ' checked' ?>>
                                <label class="form-check-label" for="dbDriverMysql"><?= esc(lang('Install.form.mysql')) ?></label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="dbDriver" id="dbDriverSqlite" value="SQLite3"<?= $options->isSqlite() ? ' checked' : '' ?>>
                                <label class="form-check-label" for="dbDriverSqlite"><?= esc(lang('Install.form.sqlite')) ?></label>
                            </div>
                            <?= isset($errors['dbDriver']) ? '<div class="text-danger small">' . esc($errors['dbDriver']) . '</div>' : '' ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="dbName" class="form-label"><?= esc(lang('Install.form.dbName')) ?></label>
                            <input type="text" class="form-control<?= $invalid('dbName') ?>" id="dbName" name="dbName" value="<?= esc($options->dbName) ?>" required<?= $aria('dbName') ?>>
                            <div class="form-text"><?= esc(lang('Install.form.dbNameHelp')) ?></div>
                            <?= $error('dbName') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="dbPrefix" class="form-label"><?= esc(lang('Install.form.dbPrefix')) ?></label>
                            <input type="text" class="form-control<?= $invalid('dbPrefix') ?>" id="dbPrefix" name="dbPrefix" value="<?= esc($options->dbPrefix) ?>"<?= $aria('dbPrefix') ?>>
                            <?= $error('dbPrefix') ?>
                        </div>
                        <p class="col-12 small text-body-secondary mb-0"><?= esc(lang('Install.form.mysqlOnly')) ?></p>
                        <div class="col-12 col-md-8">
                            <label for="dbHost" class="form-label"><?= esc(lang('Install.form.dbHost')) ?></label>
                            <input type="text" class="form-control<?= $invalid('dbHost') ?>" id="dbHost" name="dbHost" value="<?= esc($options->dbHost) ?>"<?= $aria('dbHost') ?>>
                            <?= $error('dbHost') ?>
                        </div>
                        <div class="col-12 col-md-4">
                            <label for="dbPort" class="form-label"><?= esc(lang('Install.form.dbPort')) ?></label>
                            <input type="number" class="form-control<?= $invalid('dbPort') ?>" id="dbPort" name="dbPort" min="1" max="65535" value="<?= esc((string) $options->dbPort) ?>"<?= $aria('dbPort') ?>>
                            <?= $error('dbPort') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="dbUser" class="form-label"><?= esc(lang('Install.form.dbUser')) ?></label>
                            <input type="text" class="form-control<?= $invalid('dbUser') ?>" id="dbUser" name="dbUser" value="<?= esc($options->dbUser) ?>" autocomplete="off"<?= $aria('dbUser') ?>>
                            <?= $error('dbUser') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="dbPassword" class="form-label"><?= esc(lang('Install.form.dbPassword')) ?></label>
                            <input type="password" class="form-control<?= $invalid('dbPassword') ?>" id="dbPassword" name="dbPassword" autocomplete="off"<?= $aria('dbPassword') ?>>
                            <?= $error('dbPassword') ?>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header fw-semibold"><?= esc(lang('Install.web.superadmin')) ?></div>
                    <div class="card-body row g-3">
                        <div class="col-12 col-md-6">
                            <label for="adminUsername" class="form-label"><?= esc(lang('Install.form.username')) ?></label>
                            <input type="text" class="form-control<?= $invalid('adminUsername') ?>" id="adminUsername" name="adminUsername" value="<?= esc($options->adminUsername) ?>" autocomplete="username" required<?= $aria('adminUsername') ?>>
                            <?= $error('adminUsername') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="adminEmail" class="form-label"><?= esc(lang('Install.form.email')) ?></label>
                            <input type="email" class="form-control<?= $invalid('adminEmail') ?>" id="adminEmail" name="adminEmail" value="<?= esc($options->adminEmail) ?>" autocomplete="email" required<?= $aria('adminEmail') ?>>
                            <?= $error('adminEmail') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="adminPassword" class="form-label"><?= esc(lang('Install.form.password')) ?></label>
                            <input type="password" class="form-control<?= $invalid('adminPassword') ?>" id="adminPassword" name="adminPassword" autocomplete="new-password" minlength="<?= esc((string) $minPassword) ?>" required<?= $aria('adminPassword') ?>>
                            <div class="form-text"><?= esc(lang('Install.form.passwordHelp', [$minPassword])) ?></div>
                            <?= $error('adminPassword') ?>
                        </div>
                        <div class="col-12 col-md-6">
                            <label for="adminPasswordConfirm" class="form-label"><?= esc(lang('Install.form.passwordConfirm')) ?></label>
                            <input type="password" class="form-control<?= $invalid('adminPasswordConfirm') ?>" id="adminPasswordConfirm" name="adminPasswordConfirm" autocomplete="new-password" required<?= $aria('adminPasswordConfirm') ?>>
                            <?= $error('adminPasswordConfirm') ?>
                        </div>
                        <p class="col-12 small text-body-secondary mb-0"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i><?= esc(lang('Install.web.twoFactor')) ?></p>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <div class="form-check">
                            <input class="form-check-input<?= $invalid('demo') ?>" type="checkbox" name="demo" id="demo" value="1"<?= $options->demo !== null ? ' checked' : '' ?><?= $aria('demo') ?>>
                            <label class="form-check-label" for="demo"><?= esc(lang('Install.form.demo')) ?></label>
                            <?= $error('demo') ?>
                        </div>
                        <div class="form-text"><?= esc(lang('Install.form.demoHelp')) ?></div>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-lg"><i class="bi bi-check2-circle me-1" aria-hidden="true"></i><?= esc(lang('Install.web.submit')) ?></button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
