<?php
/**
 * `backend/devtools/packages`: build, download and sign module and theme packages (session 43).
 *
 * @var string                                                                                                                         $layout
 * @var list<\Modules\Devtools\Packages\PackageSource>                                                                                 $sources
 * @var list<array{file: string, size: int, time: int, sha256: bool, minisig: bool, info: array<string, string>|null}>                  $packages
 * @var array{file: string, name: string, type: string, version: string, files: int, size: int, sha256: string, skipped: list<string>, warnings: list<string>}|null $result
 * @var bool                                                                                                                           $canWrite
 */
helper('number');
$copy = esc(json_encode(['copied' => lang('Devtools.recipes.copied')], JSON_THROW_ON_ERROR), 'attr');
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Devtools\Views\_banner') ?>
<p class="text-body-secondary"><?= esc(lang('Devtools.packages.help')) ?></p>

<?php if (is_array($result)): ?>
    <section class="card border-success mb-4" aria-labelledby="result-title" data-package-result>
        <div class="card-header"><h2 class="h6 mb-0" id="result-title"><i class="bi bi-check-circle text-success" aria-hidden="true"></i> <?= esc(lang('Devtools.packages.result.title', [$result['file']])) ?></h2></div>
        <div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-sm-3"><?= esc(lang('Devtools.packages.result.validated')) ?></dt>
                <dd class="col-sm-9"><?= esc(lang('Devtools.packages.result.summary', [$result['name'], $result['version'], $result['files'], number_to_size($result['size'])])) ?></dd>
                <dt class="col-sm-3">SHA-256</dt>
                <dd class="col-sm-9 font-monospace text-break"><?= esc($result['sha256']) ?></dd>
                <?php if ($result['skipped'] !== []): ?>
                    <dt class="col-sm-3"><?= esc(lang('Devtools.packages.result.skipped')) ?></dt>
                    <dd class="col-sm-9 font-monospace"><?= esc(implode(', ', $result['skipped'])) ?></dd>
                <?php endif ?>
            </dl>
            <?php foreach ($result['warnings'] as $warning): ?>
                <div class="alert alert-warning small mt-3 mb-0" role="status"><?= esc($warning) ?></div>
            <?php endforeach ?>
        </div>
    </section>
<?php endif ?>

<section class="card mb-4" aria-labelledby="sources-title">
    <div class="card-header"><h2 class="h6 mb-0" id="sources-title"><?= esc(lang('Devtools.packages.sources')) ?></h2></div>
    <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col"><?= esc(lang('Devtools.packages.name')) ?></th>
                    <th scope="col"><?= esc(lang('Devtools.packages.type')) ?></th>
                    <th scope="col"><?= esc(lang('Devtools.packages.version')) ?></th>
                    <th scope="col"><span class="visually-hidden"><?= esc(lang('Devtools.packages.build')) ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sources as $source): ?>
                    <tr data-package-source="<?= esc($source->name, 'attr') ?>">
                        <th scope="row" class="fw-semibold"><?= esc($source->name) ?></th>
                        <td><?= ui_badge(lang('Devtools.packages.types.' . $source->type), $source->isModule() ? 'primary' : 'info') ?></td>
                        <td class="font-monospace"><?= esc($source->version) ?></td>
                        <td class="text-end">
                            <?php if ($canWrite): ?>
                                <form method="post" action="<?= esc(backend_url('devtools/packages/build'), 'attr') ?>" class="d-flex flex-wrap gap-2 justify-content-end">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="type" value="<?= esc($source->type, 'attr') ?>">
                                    <input type="hidden" name="name" value="<?= esc($source->name, 'attr') ?>">
                                    <label class="visually-hidden" for="bump-<?= esc($source->type . '-' . $source->name, 'attr') ?>"><?= esc(lang('Devtools.packages.bump.label')) ?></label>
                                    <select class="form-select form-select-sm w-auto" name="bump" id="bump-<?= esc($source->type . '-' . $source->name, 'attr') ?>">
                                        <?php foreach (['none', 'patch', 'minor', 'major'] as $level): ?>
                                            <option value="<?= esc($level, 'attr') ?>"><?= esc(lang('Devtools.packages.bump.' . $level)) ?></option>
                                        <?php endforeach ?>
                                    </select>
                                    <button type="submit" class="btn btn-sm btn-primary"><i class="bi bi-box-seam" aria-hidden="true"></i> <?= esc(lang('Devtools.packages.build')) ?></button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
    <div class="card-footer small text-body-secondary"><?= esc(lang('Devtools.packages.coreHelp')) ?></div>
</section>

<section class="card mb-4" aria-labelledby="built-title">
    <div class="card-header"><h2 class="h6 mb-0" id="built-title"><?= esc(lang('Devtools.packages.built.title')) ?></h2></div>
    <?php if ($packages === []): ?>
        <div class="card-body text-body-secondary"><?= esc(lang('Devtools.packages.built.none')) ?></div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($packages as $i => $package): ?>
                <li class="list-group-item" data-built-package="<?= esc($package['file'], 'attr') ?>">
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
                        <strong class="font-monospace text-break"><?= esc($package['file']) ?></strong>
                        <span class="text-body-secondary small"><?= esc(number_to_size($package['size'])) ?> · <?= ui_date(date('c', $package['time']), 'datetime') ?></span>
                        <?php if ($package['minisig']): ?>
                            <?= ui_badge(lang('Devtools.packages.built.signed', [$package['info']['keyId'] ?? '?']), 'success', 'bi bi-patch-check') ?>
                        <?php else: ?>
                            <?= ui_badge(lang('Devtools.packages.built.unsigned'), 'secondary') ?>
                        <?php endif ?>
                    </div>
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <a class="btn btn-sm btn-outline-secondary" href="<?= esc(backend_url('devtools/packages/download/' . $package['file']), 'attr') ?>"><i class="bi bi-download" aria-hidden="true"></i> .zip</a>
                        <?php if ($package['sha256']): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= esc(backend_url('devtools/packages/download/' . $package['file'] . '.sha256'), 'attr') ?>"><i class="bi bi-download" aria-hidden="true"></i> .sha256</a>
                        <?php endif ?>
                        <?php if ($package['minisig']): ?>
                            <a class="btn btn-sm btn-outline-secondary" href="<?= esc(backend_url('devtools/packages/download/' . $package['file'] . '.minisig'), 'attr') ?>"><i class="bi bi-download" aria-hidden="true"></i> .minisig</a>
                        <?php endif ?>
                        <?php if ($canWrite): ?>
                            <a class="btn btn-sm btn-outline-primary" href="<?= esc(backend_url('devtools/packages/sign') . '?package=' . rawurlencode($package['file']), 'attr') ?>"><i class="bi bi-pen" aria-hidden="true"></i> <?= esc(lang('Devtools.packages.sign.button')) ?></a>
                        <?php endif ?>
                    </div>
                    <?php if ($package['minisig'] && ($package['info']['publicKey'] ?? '') !== ''): ?>
                        <p class="small mb-1"><?= esc(lang('Devtools.packages.built.publicKey')) ?></p>
                        <div class="d-flex gap-2 align-items-start mb-2">
                            <pre class="bg-body-tertiary border rounded p-2 small mb-0 flex-grow-1 text-break"><code id="pub-<?= $i ?>"><?= esc($package['info']['publicKey']) ?></code></pre>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-component="devtools/copy" data-copy-target="pub-<?= $i ?>" data-options="<?= $copy ?>"><i class="bi bi-clipboard" aria-hidden="true"></i><span class="visually-hidden"> <?= esc(lang('Devtools.recipes.copy')) ?></span></button>
                        </div>
                    <?php endif ?>
                    <details class="small">
                        <summary><?= esc(lang('Devtools.packages.built.elsewhere')) ?></summary>
                        <div class="d-flex gap-2 align-items-start mt-2">
                            <pre class="bg-body-tertiary border rounded p-2 mb-0 flex-grow-1 text-break"><code id="cmd-<?= $i ?>">minisign -Sm <?= esc($package['file']) ?> -t '<?= esc(preg_replace('/^(.+)-(\d+\.\d+\.\d+[^.]*)\.zip$/', '$1 $2', $package['file'])) ?>'</code></pre>
                            <button type="button" class="btn btn-sm btn-outline-secondary" data-component="devtools/copy" data-copy-target="cmd-<?= $i ?>" data-options="<?= $copy ?>"><i class="bi bi-clipboard" aria-hidden="true"></i><span class="visually-hidden"> <?= esc(lang('Devtools.recipes.copy')) ?></span></button>
                        </div>
                    </details>
                </li>
            <?php endforeach ?>
        </ul>
    <?php endif ?>
    <div class="card-footer small text-body-secondary"><?= esc(lang('Devtools.packages.built.folder')) ?></div>
</section>
<?= $this->endSection() ?>
