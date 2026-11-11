<?php
/**
 * Revisions of a page (`pages.show`): the list (who, when, what changed), the differences of the
 * selected revision with the previous one, and "restore" (`pages.edit`, POST with CSRF).
 *
 * @var list<array{id: int, user: string|null, action: string, created_at: string, changes: list<string>}> $history
 * @var int                                         $selected
 * @var array<string, array{0: string, 1: string}>  $diff
 * @var bool                                        $canRestore
 * @var string                                      $restoreUrl
 * @var string                                      $pageUrl
 * @var string                                      $backUrl
 * @var array<string, string>                       $labels
 */
$label = static function (string $key) use ($labels): string {
    [$field, $locale] = array_pad(explode('.', $key, 2), 2, null);
    $text             = $labels[$field] ?? $field;

    return $locale === null ? $text : $text . ' (' . $locale . ')';
};
$short = static fn (string $text): string => mb_strlen($text) > 600 ? mb_substr($text, 0, 600) . '…' : $text;
?>
<div class="row g-4">
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0"><?= esc(lang('Pages.revisionsHistory')) ?></h2></div>
            <?php if ($history === []): ?>
                <div class="card-body">
                    <?= theme_component('empty_state', ['icon' => 'bi bi-clock-history', 'title' => lang('Pages.revisionsEmpty'), 'message' => null, 'action' => null, 'compact' => true]) ?>
                </div>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($history as $i => $revision): ?>
                        <div class="list-group-item<?= $revision['id'] === $selected ? ' active-revision bg-body-tertiary' : '' ?>">
                            <div class="d-flex flex-wrap justify-content-between gap-2">
                                <a class="fw-semibold" href="<?= esc($pageUrl . '?revision=' . $revision['id'], 'attr') ?>"<?= $revision['id'] === $selected ? ' aria-current="true"' : '' ?>>
                                    <?= ui_date($revision['created_at'], 'datetime') ?>
                                </a>
                                <span class="small text-body-secondary">
                                    <i class="bi bi-person" aria-hidden="true"></i> <?= esc($revision['user'] ?? lang('Pages.unknownUser')) ?>
                                </span>
                            </div>
                            <div class="small mt-1">
                                <?= ui_badge(lang('Pages.revisionActions.' . (in_array($revision['action'], ['create', 'save', 'restore', 'duplicate'], true) ? $revision['action'] : 'save')), $i === 0 ? 'primary' : 'secondary') ?>
                                <?php if ($revision['changes'] !== []): ?>
                                    <span class="text-body-secondary"><?= esc(implode(', ', array_map($label, array_slice($revision['changes'], 0, 6)))) ?><?= count($revision['changes']) > 6 ? '…' : '' ?></span>
                                <?php endif ?>
                            </div>
                            <?php if ($canRestore && $i > 0): ?>
                                <form method="post" action="<?= esc($restoreUrl, 'attr') ?>" class="mt-2" data-confirm="<?= esc(lang('Pages.restoreConfirm'), 'attr') ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="revision" value="<?= esc((string) $revision['id'], 'attr') ?>">
                                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i> <?= esc(lang('Pages.restore')) ?></button>
                                </form>
                            <?php endif ?>
                        </div>
                    <?php endforeach ?>
                </div>
            <?php endif ?>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-transparent"><h2 class="h6 mb-0"><?= esc(lang('Pages.revisionChanges')) ?></h2></div>
            <div class="card-body">
                <?php if ($diff === []): ?>
                    <p class="text-body-secondary mb-0"><?= esc(lang('Pages.revisionNoChanges')) ?></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-top mb-0 revision-diff">
                            <thead>
                                <tr>
                                    <th scope="col"><?= esc(lang('Pages.revisionField')) ?></th>
                                    <th scope="col"><?= esc(lang('Pages.revisionBefore')) ?></th>
                                    <th scope="col"><?= esc(lang('Pages.revisionAfter')) ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($diff as $key => [$before, $after]): ?>
                                    <tr>
                                        <th scope="row" class="text-nowrap"><?= esc($label($key)) ?></th>
                                        <td class="text-break"><del class="text-danger-emphasis"><?= esc($short($before)) ?></del></td>
                                        <td class="text-break"><ins class="text-success-emphasis"><?= esc($short($after)) ?></ins></td>
                                    </tr>
                                <?php endforeach ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif ?>
            </div>
        </div>
    </div>
</div>
<p class="mt-3"><a class="btn btn-outline-secondary btn-sm" href="<?= esc($backUrl, 'attr') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('Pages.backToList')) ?></a></p>
