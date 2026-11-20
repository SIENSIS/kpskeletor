<?php
/**
 * The form of one section, generated from its type's schema (`SectionFormView`), with the
 * unsaved-changes guard.
 *
 * @var string                              $layout
 * @var \Modules\Core\Sections\SectionType  $type
 * @var int|null                            $sectionId
 * @var string                              $fields  HTML of the fields
 * @var string                              $action
 * @var string                              $backUrl
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<form method="post" action="<?= esc($action, 'attr') ?>" class="card shadow-sm kp-section-editor" data-dirty-guard novalidate>
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= (int) $sectionId ?>">
    <input type="hidden" name="type" value="<?= esc($type->key(), 'attr') ?>">
    <div class="card-body">
        <?php if ($type->description() !== ''): ?>
            <p class="text-body-secondary small"><?= esc($type->description()) ?></p>
        <?php endif ?>
        <?= $fields ?>
    </div>
    <div class="card-footer form-actions d-flex flex-wrap gap-2 justify-content-end">
        <a class="btn btn-outline-secondary" href="<?= esc($backUrl, 'attr') ?>"><?= esc(lang('Core.ui.cancel')) ?></a>
        <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1" aria-hidden="true"></i><?= esc(lang('Core.ui.save')) ?></button>
    </div>
</form>
<?= $this->endSection() ?>
