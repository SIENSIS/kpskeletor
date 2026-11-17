<?php
/**
 * One card of the board. `board.js` builds the same markup when it redraws the columns.
 *
 * @var array<string, mixed> $card
 * @var string               $status
 */
?>
<li class="card shadow-sm tasks-board-card<?= $card['overdue'] ? ' is-overdue' : '' ?>" data-board-card="<?= esc($card['id'], 'attr') ?>" data-move-url="<?= esc($card['moveUrl'], 'attr') ?>"<?= $card['canMove'] ? ' draggable="true"' : '' ?>>
    <div class="card-body p-2">
        <div class="d-flex justify-content-between gap-2">
            <h3 class="h6 mb-1 text-break">
                <?php if ($card['url'] !== null): ?>
                    <a href="<?= esc($card['url'], 'attr') ?>"><?= esc($card['title']) ?></a>
                <?php else: ?>
                    <?= esc($card['title']) ?>
                <?php endif ?>
            </h3>
            <span class="flex-shrink-0 align-self-start"><?= ui_badge(lang('Tasks.priorities.' . $card['priority']), ['high' => 'danger', 'normal' => 'secondary', 'low' => 'light'][$card['priority']] ?? 'secondary') ?></span>
        </div>
        <div class="small text-body-secondary d-flex flex-wrap gap-2">
            <?php if ($card['project'] !== null): ?><span><i class="bi bi-folder2" aria-hidden="true"></i> <?= esc($card['project']) ?></span><?php endif ?>
            <?php if ($card['due'] !== null): ?><span class="<?= $card['overdue'] ? 'text-danger fw-semibold' : '' ?>"><i class="bi bi-calendar-event" aria-hidden="true"></i> <?= ui_date($card['due'], 'date') ?></span><?php endif ?>
            <?php if ($card['assignees'] !== []): ?><span><i class="bi bi-people" aria-hidden="true"></i> <?= esc(implode(', ', $card['assignees'])) ?></span><?php endif ?>
        </div>
        <?php if ($card['labels'] !== []): ?>
            <div class="d-flex flex-wrap gap-1 mt-1">
                <?php foreach ($card['labels'] as $label): ?><span class="badge rounded-pill text-bg-light border"><?= esc($label) ?></span><?php endforeach ?>
            </div>
        <?php endif ?>
        <?php if ($card['canMove']): ?>
            <form method="post" action="<?= esc($card['moveUrl'], 'attr') ?>" class="d-flex gap-1 mt-2" data-board-move>
                <?= csrf_field() ?>
                <label class="visually-hidden" for="move-<?= esc($card['id'], 'attr') ?>"><?= esc(lang('Tasks.board.moveTo')) ?></label>
                <select class="form-select form-select-sm" id="move-<?= esc($card['id'], 'attr') ?>" name="status">
                    <?php foreach (\Modules\Tasks\Services\TaskRepository::STATUSES as $option): ?>
                        <option value="<?= esc($option, 'attr') ?>"<?= $option === $status ? ' selected' : '' ?>><?= esc(lang('Tasks.statuses.' . $option)) ?></option>
                    <?php endforeach ?>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-primary"><?= esc(lang('Tasks.board.move')) ?></button>
            </form>
        <?php endif ?>
    </div>
</li>
