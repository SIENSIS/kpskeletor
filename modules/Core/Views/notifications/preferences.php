<?php
/**
 * Notification preferences: one row per type (grouped by module) and a checkbox per channel.
 *
 * @var string                                                   $layout
 * @var array<string, array<string, array<string, mixed>>>       $groups   module => class => info
 * @var list<string>                                             $channels
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php if ($groups === []): ?>
    <?= theme_component('empty_state', ['title' => lang('Core.notifications.noTypes'), 'icon' => 'bi bi-bell-slash']) ?>
<?php else: ?>
<form action="<?= esc(site_url('backend/profile/notifications')) ?>" method="post">
    <?= csrf_field() ?>
    <p class="small text-body-secondary"><?= esc(lang('Core.notifications.preferencesIntro')) ?></p>
    <?php foreach ($groups as $module => $types): ?>
        <?php ob_start() ?>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col"><?= esc(lang('Core.notifications.type')) ?></th>
                        <?php foreach ($channels as $channel): ?>
                            <th scope="col" class="text-center"><?= esc(lang('Core.notifications.channels.' . $channel)) ?></th>
                        <?php endforeach ?>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($types as $class => $info): ?>
                        <tr>
                            <td>
                                <i class="<?= esc($info['icon'], 'attr') ?> me-2" aria-hidden="true"></i><?= esc($info['title']) ?>
                                <?php if ($info['mandatory']): ?> <?= ui_badge(lang('Core.notifications.mandatory'), 'secondary') ?><?php endif ?>
                                <?php if ($info['description'] !== ''): ?><div class="small text-body-secondary"><?= esc($info['description']) ?></div><?php endif ?>
                                <?php if (! $info['mandatory']): ?><input type="hidden" name="types[]" value="<?= esc($class, 'attr') ?>"><?php endif ?>
                            </td>
                            <?php foreach ($channels as $channel): ?>
                                <td class="text-center">
                                    <input class="form-check-input" type="checkbox"
                                        name="channels[<?= esc($class, 'attr') ?>][]" value="<?= esc($channel, 'attr') ?>"
                                        aria-label="<?= esc(lang('Core.notifications.channels.' . $channel) . ': ' . $info['title']) ?>"
                                        <?= in_array($channel, $info['channels'], true) ? 'checked' : '' ?>
                                        <?= $info['mandatory'] ? 'disabled' : '' ?>>
                                </td>
                            <?php endforeach ?>
                        </tr>
                    <?php endforeach ?>
                </tbody>
            </table>
        </div>
        <?php $body = (string) ob_get_clean() ?>
        <div class="mb-4"><?= theme_component('card', ['title' => $module, 'icon' => 'bi bi-box', 'body' => $body]) ?></div>
    <?php endforeach ?>
    <div class="d-flex gap-2">
        <button type="submit" class="btn btn-primary"><i class="bi bi-check2 me-1" aria-hidden="true"></i><?= esc(lang('Core.ui.save')) ?></button>
        <a class="btn btn-outline-secondary" href="<?= esc(site_url('backend/notifications')) ?>"><?= esc(lang('Core.notifications.title')) ?></a>
    </div>
</form>
<?php endif ?>
<?= $this->endSection() ?>
