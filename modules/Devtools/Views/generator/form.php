<?php
/**
 * `backend/devtools/generator`: the code generator's wizard (Modules\Devtools\Controllers\Generator).
 * One form whose names follow the definition's JSON; every button posts it (the draft is kept in
 * the session) and works without JavaScript. Steps: 1 module, then per table 2 table and
 * columns, 3 demo data, 4 CRUD, 5 API, 6 menu and permissions; then the preview.
 *
 * @var string                     $layout
 * @var array<string, mixed>       $data        the definition (ModuleDefinition::toArray())
 * @var array<string, string>      $errors      path => message
 * @var bool                       $showErrors
 * @var array<string, string>      $modules     installed module => version
 * @var list<string>               $groups
 * @var list<string>               $tables      tables of the database (foreign keys)
 * @var list<list<string>>         $permissions permission names per table
 */


$module   = $data['module'];
$existing = (bool) ($module['existing'] ?? false);
$locales  = \Modules\Core\Generator\Definition\Texts::LOCALES;
$error    = static fn (string $path): ?string => $errors[$path] ?? null;
$invalid  = static fn (string $path): string => isset($errors[$path]) ? ' is-invalid' : '';
$feedback = static fn (string $path): string => isset($errors[$path]) ? '<div class="invalid-feedback d-block">' . esc($errors[$path]) . '</div>' : '';
$recipe   = static fn (string $id): string => '<a class="small position-relative" href="' . esc(backend_url('devtools/recipes') . '#recipe-' . $id, 'attr') . '"><i class="bi bi-journal-code" aria-hidden="true"></i> ' . esc(lang('Devtools.generator.recipe')) . '<span class="visually-hidden">: ' . esc(lang('Devtools.recipes.' . $id . '.title')) . '</span></a>';
$checked  = static fn (bool $on): string => $on ? ' checked' : '';
$label    = static fn (array $texts, string $locale): string => (string) ($texts[$locale] ?? '');
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Devtools\Views\_banner') ?>
<p class="text-body-secondary"><?= esc(lang('Devtools.generator.intro')) ?></p>

<?php if ($showErrors && $errors !== []): ?>
    <div class="alert alert-danger" role="alert" data-generator-errors>
        <p class="fw-semibold mb-1"><?= esc(lang('Devtools.generator.hasErrors', [count($errors)])) ?></p>
        <ul class="mb-0 small">
            <?php foreach ($errors as $path => $message): ?>
                <li><code><?= esc($path) ?></code>: <?= esc($message) ?></li>
            <?php endforeach ?>
        </ul>
    </div>
<?php elseif ($showErrors): ?>
    <div class="alert alert-success" role="status"><?= esc(lang('Devtools.generator.valid')) ?></div>
<?php endif ?>

<nav class="mb-3" aria-label="<?= esc(lang('Devtools.generator.steps'), 'attr') ?>">
    <ol class="nav nav-pills flex-wrap gap-1 small list-unstyled mb-0" data-generator-steps>
        <li class="nav-item"><a class="nav-link py-1 px-2" href="#step-module">1. <?= esc(lang('Devtools.generator.step.module')) ?></a></li>
        <?php foreach ($data['tables'] as $t => $table): ?>
            <li class="nav-item"><a class="nav-link py-1 px-2" href="#table-<?= $t ?>"><?= esc(lang('Devtools.generator.tableN', [$t + 1])) ?>: <code><?= esc($table['name'] !== '' ? $table['name'] : '…') ?></code></a></li>
        <?php endforeach ?>
        <li class="nav-item"><a class="nav-link py-1 px-2" href="#step-preview">7. <?= esc(lang('Devtools.generator.step.preview')) ?></a></li>
    </ol>
</nav>

<form method="post" action="<?= esc(backend_url('devtools/generator'), 'attr') ?>" data-component="devtools/generator" data-options="<?= esc(json_encode(['setNull' => lang('Core.generator.errors.setNullNotNull', ['…']), 'prefix' => lang('Core.generator.errors.tablePrefix', ['…'])], JSON_THROW_ON_ERROR), 'attr') ?>" novalidate>
    <?= csrf_field() ?>

    <section class="card mb-4" id="step-module" aria-labelledby="step-module-title">
        <div class="card-header d-flex flex-wrap align-items-center gap-2">
            <h2 class="h5 mb-0 flex-grow-1" id="step-module-title">1. <?= esc(lang('Devtools.generator.step.module')) ?></h2>
        </div>
        <div class="card-body">
            <p class="small"><a href="<?= esc(backend_url('devtools/docs/creating-a-module'), 'attr') ?>"><i class="bi bi-book" aria-hidden="true"></i> docs/creating-a-module.md</a></p>
            <div class="btn-group mb-3" role="radiogroup" aria-label="<?= esc(lang('Devtools.generator.module.mode'), 'attr') ?>">
                <input type="radio" class="btn-check" name="module[mode]" id="mode-new" value="new"<?= $checked(! $existing) ?> data-generator-mode>
                <label class="btn btn-outline-primary" for="mode-new"><?= esc(lang('Devtools.generator.module.new')) ?></label>
                <input type="radio" class="btn-check" name="module[mode]" id="mode-existing" value="existing"<?= $checked($existing) ?> data-generator-mode>
                <label class="btn btn-outline-primary" for="mode-existing"><?= esc(lang('Devtools.generator.module.existing')) ?></label>
            </div>
            <div class="row g-3">
                <div class="col-12 col-md-6" data-generator-when="new">
                    <label class="form-label" for="module-name"><?= esc(lang('Devtools.generator.module.name')) ?></label>
                    <input class="form-control<?= $invalid('module.name') ?>" id="module-name" name="module[name]" value="<?= esc($existing ? '' : $module['name'], 'attr') ?>" pattern="[A-Z][A-Za-z0-9]*" autocomplete="off" aria-describedby="module-name-help" data-generator-module-name>
                    <div class="form-text" id="module-name-help"><?= esc(lang('Devtools.generator.module.nameHelp')) ?></div>
                    <?= $feedback('module.name') ?>
                </div>
                <div class="col-12 col-md-6" data-generator-when="existing">
                    <label class="form-label" for="module-existing"><?= esc(lang('Devtools.generator.module.existingName')) ?></label>
                    <select class="form-select<?= $invalid('module.name') ?>" id="module-existing" name="module[existingName]">
                        <?php foreach (array_keys($modules) as $name): ?>
                            <option value="<?= esc($name, 'attr') ?>"<?= $existing && $module['name'] === $name ? ' selected' : '' ?>><?= esc($name) ?></option>
                        <?php endforeach ?>
                    </select>
                    <div class="form-text"><?= esc(lang('Devtools.generator.module.existingHelp')) ?></div>
                </div>
                <div class="col-12 col-md-6" data-generator-when="new">
                    <label class="form-label" for="module-author"><?= esc(lang('Devtools.generator.module.author')) ?></label>
                    <input class="form-control<?= $invalid('module.author') ?>" id="module-author" name="module[author]" value="<?= esc((string) ($module['author'] ?? ''), 'attr') ?>" autocomplete="off">
                    <?= $feedback('module.author') ?>
                </div>
                <fieldset class="col-12" data-generator-when="new">
                    <legend class="form-label fs-6"><?= esc(lang('Devtools.generator.module.description')) ?></legend>
                    <div class="row g-2">
                        <?php foreach ($locales as $locale): ?>
                            <div class="col-12 col-lg-4">
                                <div class="input-group">
                                    <span class="input-group-text text-uppercase" id="module-description-<?= $locale ?>-label"><?= $locale ?></span>
                                    <input class="form-control" name="module[description][<?= $locale ?>]" value="<?= esc($label((array) ($module['description'] ?? []), $locale), 'attr') ?>" aria-labelledby="module-description-<?= $locale ?>-label">
                                </div>
                            </div>
                        <?php endforeach ?>
                    </div>
                </fieldset>
                <fieldset class="col-12" data-generator-when="new">
                    <legend class="form-label fs-6"><?= esc(lang('Devtools.generator.module.requires')) ?></legend>
                    <div class="d-flex flex-wrap gap-3">
                        <?php foreach ($modules as $name => $version): ?>
                            <?php if ($name === 'Core' || $name === ($module['name'] ?? '')) {
                                continue;
                            } ?>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="requires-<?= esc($name, 'attr') ?>" name="module[requires][]" value="<?= esc($name, 'attr') ?>"<?= $checked(isset($module['requires'][$name])) ?>>
                                <label class="form-check-label" for="requires-<?= esc($name, 'attr') ?>"><?= esc($name) ?> <span class="text-body-secondary small"><?= esc($version) ?></span></label>
                            </div>
                        <?php endforeach ?>
                    </div>
                    <?= $feedback('module.requires') ?>
                </fieldset>
            </div>
        </div>
    </section>

    <?php foreach ($data['tables'] as $t => $table): ?>
        <?= \Config\Services::renderer(null, null, false)->setData([
            't' => $t, 'table' => $table, 'module' => $module, 'errors' => $errors, 'groups' => $groups, 'tables' => $tables,
            'permissions' => $permissions[$t] ?? [], 'locales' => $locales, 'others' => $data['tables'],
        ])->render('Modules\Devtools\Views\generator\_table') ?>
    <?php endforeach ?>

    <?= $feedback('tables') ?>
    <div class="mb-4">
        <button type="submit" class="btn btn-outline-primary" name="action" value="addTable" formnovalidate data-generator-add-table>
            <i class="bi bi-plus-lg" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.addTable')) ?>
        </button>
    </div>

    <section class="card mb-4" id="step-preview" aria-labelledby="step-preview-title">
        <div class="card-header"><h2 class="h5 mb-0" id="step-preview-title">7. <?= esc(lang('Devtools.generator.step.preview')) ?></h2></div>
        <div class="card-body">
            <p class="small text-body-secondary"><?= esc(lang('Devtools.generator.previewHelp')) ?></p>
            <?php if (isset($errors['plan'])): ?>
                <div class="alert alert-danger"><?= esc($errors['plan']) ?></div>
            <?php endif ?>
            <div class="d-flex flex-wrap gap-2">
                <button type="submit" class="btn btn-primary" name="action" value="preview" data-generator-preview>
                    <i class="bi bi-eye" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.preview.button')) ?>
                </button>
                <button type="submit" class="btn btn-outline-secondary" name="action" value="check" formnovalidate>
                    <i class="bi bi-check2-square" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.check')) ?>
                </button>
                <button type="submit" class="btn btn-outline-secondary" name="action" value="save" formnovalidate>
                    <i class="bi bi-save" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.save')) ?>
                </button>
                <button type="submit" class="btn btn-outline-danger ms-auto" name="action" value="reset" formnovalidate data-confirm="<?= esc(lang('Devtools.generator.resetConfirm'), 'attr') ?>">
                    <i class="bi bi-x-circle" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.reset')) ?>
                </button>
            </div>
        </div>
    </section>
</form>

<section class="card mb-4" aria-labelledby="definition-title">
    <div class="card-header"><h2 class="h6 mb-0" id="definition-title"><?= esc(lang('Devtools.generator.json.title')) ?></h2></div>
    <div class="card-body">
        <p class="small text-body-secondary"><?= esc(lang('Devtools.generator.json.help')) ?></p>
        <p><a class="btn btn-sm btn-outline-secondary" href="<?= esc(backend_url('devtools/generator/definition.json'), 'attr') ?>" download><i class="bi bi-download" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.json.download')) ?></a></p>
        <form method="post" action="<?= esc(backend_url('devtools/generator/load'), 'attr') ?>" enctype="multipart/form-data" class="row g-2 align-items-end">
            <?= csrf_field() ?>
            <div class="col-12 col-md-5">
                <label class="form-label small" for="definition-file"><?= esc(lang('Devtools.generator.json.file')) ?></label>
                <input class="form-control form-control-sm" type="file" id="definition-file" name="file" accept="application/json,.json">
            </div>
            <div class="col-12 col-md-5">
                <label class="form-label small" for="definition-json"><?= esc(lang('Devtools.generator.json.paste')) ?></label>
                <textarea class="form-control form-control-sm font-monospace" id="definition-json" name="json" rows="1"></textarea>
            </div>
            <div class="col-12 col-md-2">
                <button type="submit" class="btn btn-sm btn-outline-primary w-100"><i class="bi bi-upload" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.json.load')) ?></button>
            </div>
        </form>
    </div>
</section>
<?= $this->endSection() ?>
