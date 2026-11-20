<?php
/**
 * `backend/devtools/generator/preview`: every file of the plan — a tree with "new" / "changed"
 * badges, the full content of new files and a diff of changed ones (plain, escaped `<pre>`), the
 * permission names, the next steps and the write form (`devtools.write` + re-authentication).
 *
 * @var string                                                   $layout
 * @var \Modules\Core\Generator\Plan                             $plan
 * @var array<string, list<array{type: string, text: string}>>   $diffs
 * @var list<list<string>>                                       $permissions
 */

$changes = $plan->changes();
$badge   = static fn (string $status): string => match ($status) {
    'new'     => '<span class="badge text-bg-success">' . esc(lang('Devtools.generator.preview.new')) . '</span>',
    'changed' => '<span class="badge text-bg-warning">' . esc(lang('Devtools.generator.preview.changed')) . '</span>',
    default   => '<span class="badge text-bg-secondary">' . esc(lang('Devtools.generator.preview.unchanged')) . '</span>',
};
$anchor = static fn (string $path): string => 'file-' . substr(hash('sha256', $path), 0, 12);
$tree   = static function (array $nodes, callable $self) use ($plan, $badge, $anchor): string {
    $html = '<ul class="list-unstyled ps-3 mb-0">';

    foreach ($nodes as $name => $node) {
        if (is_array($node)) {
            $html .= '<li><i class="bi bi-folder2 me-1" aria-hidden="true"></i>' . esc($name) . $self($node, $self) . '</li>';
        } else {
            $file  = $plan->files[$node];
            $html .= '<li><i class="bi bi-file-earmark-code me-1" aria-hidden="true"></i>'
                . ($file->status() === 'unchanged' ? esc($name) : '<a href="#' . $anchor($node) . '">' . esc($name) . '</a>')
                . ' ' . $badge($file->status()) . '</li>';
        }
    }

    return $html . '</ul>';
};
$new = count(array_filter($changes, static fn ($f): bool => $f->status() === 'new'));
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?= $this->include('Modules\Devtools\Views\_banner') ?>
<?php if (session('error') !== null): ?>
    <div class="alert alert-danger" role="alert"><?= esc((string) session('error')) ?></div>
<?php endif ?>
<p><?= esc(lang('Devtools.generator.preview.summary', [$plan->definition->name, $new, count($changes) - $new])) ?></p>

<div class="row g-4">
    <div class="col-12 col-lg-4">
        <section class="card mb-3" aria-labelledby="tree-title">
            <div class="card-header"><h2 class="h6 mb-0" id="tree-title"><?= esc(lang('Devtools.generator.preview.files')) ?></h2></div>
            <div class="card-body small font-monospace" data-generator-tree>
                <i class="bi bi-folder2-open me-1" aria-hidden="true"></i>modules/<?= $tree($plan->tree(), $tree) ?>
            </div>
        </section>
        <section class="card mb-3" aria-labelledby="perm-title">
            <div class="card-header"><h2 class="h6 mb-0" id="perm-title"><?= esc(lang('Devtools.generator.preview.permissions')) ?></h2></div>
            <div class="card-body small">
                <?php foreach ($permissions as $names): ?>
                    <p class="mb-2"><?php foreach ($names as $name): ?><code class="me-2"><?= esc($name) ?></code><?php endforeach ?></p>
                <?php endforeach ?>
            </div>
        </section>
        <section class="card mb-3" aria-labelledby="write-title">
            <div class="card-header"><h2 class="h6 mb-0" id="write-title"><?= esc(lang('Devtools.generator.preview.write')) ?></h2></div>
            <div class="card-body">
                <form method="post" action="<?= esc(backend_url('devtools/generator/write'), 'attr') ?>" data-generator-write>
                    <?= csrf_field() ?>
                    <input type="hidden" name="fingerprint" value="<?= esc($plan->fingerprint(), 'attr') ?>">
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="write-demo" name="demo" value="1" checked>
                        <label class="form-check-label" for="write-demo"><?= esc(lang('Devtools.generator.preview.demo', [$plan->definition->name])) ?></label>
                    </div>
                    <p class="small text-body-secondary"><?= esc(lang('Devtools.generator.preview.writeHelp')) ?></p>
                    <button type="submit" class="btn btn-danger w-100"><i class="bi bi-pencil-square" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.preview.writeButton', [count($changes)])) ?></button>
                </form>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <a class="btn btn-sm btn-outline-secondary" href="<?= esc(backend_url('devtools/generator'), 'attr') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.preview.back')) ?></a>
                    <a class="btn btn-sm btn-outline-secondary" href="<?= esc(backend_url('devtools/generator/definition.json'), 'attr') ?>" download><i class="bi bi-download" aria-hidden="true"></i> <?= esc(lang('Devtools.generator.json.download')) ?></a>
                </div>
            </div>
        </section>
        <section class="card" aria-labelledby="notes-title">
            <div class="card-header"><h2 class="h6 mb-0" id="notes-title"><?= esc(lang('Devtools.generator.preview.next')) ?></h2></div>
            <ul class="list-group list-group-flush small">
                <?php foreach ($plan->notes as $note): ?>
                    <li class="list-group-item"><?= esc($note) ?></li>
                <?php endforeach ?>
            </ul>
        </section>
    </div>
    <div class="col-12 col-lg-8">
        <?php foreach ($changes as $path => $file): ?>
            <details class="card mb-2" id="<?= $anchor($path) ?>" data-generator-file="<?= esc($path, 'attr') ?>"<?= $file->status() === 'changed' ? ' open' : '' ?>>
                <summary class="card-header d-flex flex-wrap align-items-center gap-2">
                    <code class="flex-grow-1 text-break">modules/<?= esc($path) ?></code> <?= $badge($file->status()) ?>
                </summary>
                <?php if ($file->status() === 'changed'): ?>
                    <pre class="mb-0 small p-2" data-generator-diff><?php foreach ($diffs[$path] ?? [] as $line): ?><?php if ($line['type'] === 'skip'): ?><span class="d-block text-body-secondary fst-italic">@@ <?= esc(lang('Devtools.generator.preview.skipped', [$line['text']])) ?> @@</span><?php elseif ($line['type'] === 'add'): ?><span class="d-block bg-success-subtle text-success-emphasis"><span class="visually-hidden"><?= esc(lang('Devtools.generator.preview.added')) ?></span>+ <?= esc($line['text']) ?></span><?php elseif ($line['type'] === 'del'): ?><span class="d-block bg-danger-subtle text-danger-emphasis"><span class="visually-hidden"><?= esc(lang('Devtools.generator.preview.removed')) ?></span>- <?= esc($line['text']) ?></span><?php else: ?><span class="d-block">  <?= esc($line['text']) ?></span><?php endif ?><?php endforeach ?></pre>
                <?php else: ?>
                    <pre class="mb-0 small p-2"><code><?= esc($file->content) ?></code></pre>
                <?php endif ?>
            </details>
        <?php endforeach ?>
    </div>
</div>
<?= $this->endSection() ?>
