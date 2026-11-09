<?php
/**
 * Readers page of a post (`news-posts.readers`): how many users read it and who, newest first.
 *
 * @var string                                          $title
 * @var int                                             $count
 * @var list<array{username: string, read_at: string}> $readers
 * @var string                                          $backUrl
 */
?>
<div class="card shadow-sm">
    <div class="card-body">
        <p class="fs-5 mb-3"><i class="bi bi-eye" aria-hidden="true"></i> <?= esc(lang('News.readersCount', [$count])) ?></p>
        <?php if ($readers === []): ?>
            <?= theme_component('empty_state', ['icon' => 'bi bi-eye-slash', 'title' => lang('News.readersEmpty'), 'message' => null, 'action' => null, 'compact' => true]) ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= esc(lang('News.reader')) ?></th>
                            <th scope="col"><?= esc(lang('News.readAt')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($readers as $reader): ?>
                            <tr>
                                <td><?= esc($reader['username']) ?></td>
                                <td><?= ui_date($reader['read_at'], 'datetime') ?></td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
    </div>
    <div class="card-footer bg-transparent">
        <a class="btn btn-outline-secondary btn-sm" href="<?= esc($backUrl, 'attr') ?>"><i class="bi bi-arrow-left" aria-hidden="true"></i> <?= esc(lang('News.backToList')) ?></a>
    </div>
</div>
