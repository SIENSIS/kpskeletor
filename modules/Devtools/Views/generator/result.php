<?php
/**
 * `backend/devtools/generator/result`: what the generator wrote and the output of the commands
 * it ran, with the next steps.
 *
 * @var string                                                                                        $layout
 * @var array{module: string, written: list<string>, log: list<string>, ok: bool, notes: list<string>} $result
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Devtools\Views\_banner') ?>
<div class="alert <?= $result['ok'] ? 'alert-success' : 'alert-warning' ?>" role="status" data-generator-result>
    <?= esc(lang($result['ok'] ? 'Devtools.generator.result.ok' : 'Devtools.generator.result.syncFailed', [$result['module']])) ?>
</div>
<div class="row g-4">
    <div class="col-12 col-lg-6">
        <section class="card mb-3" aria-labelledby="next-title">
            <div class="card-header"><h2 class="h6 mb-0" id="next-title"><?= esc(lang('Devtools.generator.preview.next')) ?></h2></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($result['notes'] as $note): ?>
                    <li class="list-group-item"><?= esc($note) ?></li>
                <?php endforeach ?>
            </ul>
            <div class="card-body">
                <pre class="bg-body-tertiary border rounded p-2 small mb-0"><code>vendor/bin/phpunit modules/<?= esc($result['module']) ?>

php spark permissions:check
vendor/bin/phpstan analyse modules/<?= esc($result['module']) ?></code></pre>
            </div>
        </section>
        <section class="card" aria-labelledby="written-title">
            <div class="card-header"><h2 class="h6 mb-0" id="written-title"><?= esc(lang('Devtools.generator.result.files', [count($result['written'])])) ?></h2></div>
            <ul class="list-group list-group-flush small font-monospace">
                <?php foreach ($result['written'] as $path): ?>
                    <li class="list-group-item">modules/<?= esc($path) ?></li>
                <?php endforeach ?>
            </ul>
        </section>
    </div>
    <div class="col-12 col-lg-6">
        <section class="card" aria-labelledby="log-title">
            <div class="card-header"><h2 class="h6 mb-0" id="log-title"><?= esc(lang('Devtools.generator.result.log')) ?></h2></div>
            <pre class="mb-0 small p-2"><code><?= esc(implode("\n", $result['log'])) ?></code></pre>
        </section>
    </div>
</div>
<p class="mt-4"><a class="btn btn-outline-primary" href="<?= esc(backend_url('devtools/generator'), 'attr') ?>"><i class="bi bi-magic" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.result.again')) ?></a></p>
<?= $this->endSection() ?>
