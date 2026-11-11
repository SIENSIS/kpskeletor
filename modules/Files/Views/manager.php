<?php
/**
 * The file manager shell (files/file-manager): toolbar, folder tree, drop zone, listing, upload
 * queue and details panel. Contents and actions come from the JSON endpoints.
 *
 * @var string               $layout
 * @var array<string, mixed> $config
 */
$t = static fn (string $key): string => esc(lang('Files.manager.' . $key));
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<div class="file-manager" data-component="files/file-manager" data-options-id="file-manager-config">
    <noscript><div class="alert alert-warning"><?= $t('noscript') ?></div></noscript>
    <div class="fm-toolbar d-flex flex-wrap align-items-center gap-2 mb-3" role="toolbar" aria-label="<?= $t('toolbar') ?>">
        <button type="button" class="btn btn-outline-secondary d-lg-none" data-fm-toggle-tree aria-expanded="false" aria-controls="fm-tree"><i class="bi bi-diagram-3" aria-hidden="true"></i><span class="visually-hidden"><?= $t('folders') ?></span></button>
        <form class="fm-search d-flex flex-wrap gap-2 flex-grow-1" role="search" data-fm-filters>
            <label class="visually-hidden" for="fm-q"><?= $t('search') ?></label>
            <input type="search" class="form-control form-control-sm fm-q" id="fm-q" name="q" placeholder="<?= $t('search') ?>" maxlength="100">
            <label class="visually-hidden" for="fm-type"><?= $t('type') ?></label>
            <select class="form-select form-select-sm w-auto" id="fm-type" name="type">
                <option value=""><?= $t('allTypes') ?></option>
                <?php foreach ([...$config['types'], 'other'] as $type): ?>
                    <option value="<?= esc($type, 'attr') ?>"><?= esc(lang('Files.types.' . $type)) ?></option>
                <?php endforeach ?>
            </select>
            <label class="visually-hidden" for="fm-from"><?= $t('from') ?></label>
            <input type="date" class="form-control form-control-sm w-auto" id="fm-from" name="from" title="<?= $t('from') ?>">
            <label class="visually-hidden" for="fm-to"><?= $t('to') ?></label>
            <input type="date" class="form-control form-control-sm w-auto" id="fm-to" name="to" title="<?= $t('to') ?>">
            <label class="visually-hidden" for="fm-tag"><?= $t('tag') ?></label>
            <input type="text" class="form-control form-control-sm fm-tag" id="fm-tag" name="tag" placeholder="<?= $t('tag') ?>" maxlength="30">
            <label class="visually-hidden" for="fm-sort"><?= $t('sort') ?></label>
            <select class="form-select form-select-sm w-auto" id="fm-sort" name="sort">
                <option value="name"><?= $t('sortName') ?></option>
                <option value="date"><?= $t('sortDate') ?></option>
                <option value="size"><?= $t('sortSize') ?></option>
            </select>
        </form>
        <div class="btn-group btn-group-sm" role="group" aria-label="<?= $t('view') ?>">
            <button type="button" class="btn btn-outline-secondary" data-fm-view="list" aria-pressed="true" title="<?= $t('viewList') ?>"><i class="bi bi-list-ul" aria-hidden="true"></i><span class="visually-hidden"><?= $t('viewList') ?></span></button>
            <button type="button" class="btn btn-outline-secondary" data-fm-view="grid" aria-pressed="false" title="<?= $t('viewGrid') ?>"><i class="bi bi-grid-3x3-gap" aria-hidden="true"></i><span class="visually-hidden"><?= $t('viewGrid') ?></span></button>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary" data-fm-trash><i class="bi bi-trash3 me-1" aria-hidden="true"></i><?= $t('trash') ?></button>
    </div>
    <div class="fm-body">
        <nav class="fm-tree card shadow-sm" id="fm-tree" aria-label="<?= $t('folders') ?>">
            <div class="card-body p-2"><ul class="list-unstyled mb-0" data-fm-tree role="tree"></ul></div>
        </nav>
        <section class="fm-main card shadow-sm" aria-live="polite" data-fm-main>
            <div class="card-header d-flex flex-wrap align-items-center gap-2">
                <nav aria-label="<?= $t('location') ?>" class="flex-grow-1"><ol class="breadcrumb mb-0" data-fm-crumbs></ol></nav>
                <span class="small text-body-secondary" data-fm-quota></span>
                <div class="d-flex flex-wrap gap-2" data-fm-actions>
                    <button type="button" class="btn btn-sm btn-primary d-none" data-fm-upload><i class="bi bi-upload me-1" aria-hidden="true"></i><?= $t('upload') ?></button>
                    <input type="file" class="d-none" multiple data-fm-input aria-hidden="true" tabindex="-1">
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-fm-new-folder><i class="bi bi-folder-plus me-1" aria-hidden="true"></i><?= $t('newFolder') ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-fm-rename-folder><i class="bi bi-pencil me-1" aria-hidden="true"></i><?= $t('renameFolder') ?></button>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-none" data-fm-acl><i class="bi bi-shield-lock me-1" aria-hidden="true"></i><?= $t('permissions') ?></button>
                    <button type="button" class="btn btn-sm btn-outline-danger d-none" data-fm-delete-folder><i class="bi bi-folder-x me-1" aria-hidden="true"></i><?= $t('deleteFolder') ?></button>
                </div>
            </div>
            <div class="card-body fm-drop" data-fm-drop>
                <div class="fm-dropzone d-none" data-fm-dropzone><i class="bi bi-cloud-arrow-up" aria-hidden="true"></i> <?= $t('dropHere') ?></div>
                <ul class="list-unstyled fm-queue mb-2" data-fm-queue aria-label="<?= $t('queue') ?>"></ul>
                <div data-fm-listing><p class="text-body-secondary"><?= $t('chooseFolder') ?></p></div>
            </div>
        </section>
        <aside class="fm-details card shadow-sm d-none" data-fm-details aria-label="<?= $t('details') ?>"></aside>
    </div>
</div>
<script type="application/json" id="file-manager-config"><?= json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<?= $this->endSection() ?>
