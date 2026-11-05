<?php
/**
 * "My sessions": open sessions with remote logout, and the user's API tokens.
 *
 * @var string                     $layout
 * @var list<array<string, mixed>> $sessions
 * @var list<array<string, mixed>> $tokens
 * @var bool                       $canRevoke
 */
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php ob_start() ?>
<p class="small text-body-secondary"><?= esc(lang('Auth.sessions.intro')) ?></p>
<div class="table-responsive">
    <table class="table align-middle mb-0">
        <thead>
            <tr>
                <th scope="col"><?= esc(lang('Auth.sessions.device')) ?></th>
                <th scope="col" class="d-none d-md-table-cell"><?= esc(lang('Auth.sessions.ip')) ?></th>
                <th scope="col" class="d-none d-lg-table-cell"><?= esc(lang('Auth.sessions.started')) ?></th>
                <th scope="col"><?= esc(lang('Auth.sessions.lastSeen')) ?></th>
                <th scope="col" class="text-end"><span class="visually-hidden"><?= esc(lang('Auth.sessions.actions')) ?></span></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($sessions as $row): ?>
                <tr>
                    <td>
                        <i class="bi bi-laptop me-1" aria-hidden="true"></i><?= esc((string) $row['device']) ?>
                        <?php if ($row['current']): ?> <?= ui_badge(lang('Auth.sessions.current'), 'success') ?><?php endif ?>
                        <?php if ((int) $row['remembered'] === 1): ?> <?= ui_badge(lang('Auth.sessions.remembered'), 'info') ?><?php endif ?>
                    </td>
                    <td class="d-none d-md-table-cell"><?= esc((string) ($row['ip_address'] ?? '')) ?></td>
                    <td class="d-none d-lg-table-cell"><?= ui_date($row['created_at'], 'datetime') ?></td>
                    <td><?= ui_date($row['last_seen_at'], 'datetime') ?></td>
                    <td class="text-end">
                        <?php if ($canRevoke && ! $row['current']): ?>
                            <form action="<?= esc(site_url('backend/profile/sessions/' . (int) $row['id'] . '/revoke')) ?>" method="post" class="d-inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= esc(lang('Auth.sessions.confirmRevoke')) ?>"><i class="bi bi-box-arrow-right me-1" aria-hidden="true"></i><?= esc(lang('Auth.sessions.revoke')) ?></button>
                            </form>
                        <?php endif ?>
                    </td>
                </tr>
            <?php endforeach ?>
        </tbody>
    </table>
</div>
<?php $body = (string) ob_get_clean() ?>
<?php ob_start() ?>
<?php if ($canRevoke && count($sessions) > 1): ?>
    <form action="<?= esc(site_url('backend/profile/sessions/revoke-others')) ?>" method="post" class="text-end">
        <?= csrf_field() ?>
        <button type="submit" class="btn btn-outline-danger btn-sm" data-confirm="<?= esc(lang('Auth.sessions.confirmRevokeOthers')) ?>"><i class="bi bi-x-octagon me-1" aria-hidden="true"></i><?= esc(lang('Auth.sessions.revokeOthers')) ?></button>
    </form>
<?php endif ?>
<?php $footer = (string) ob_get_clean() ?>
<div class="mb-4"><?= theme_component('card', ['title' => lang('Auth.sessions.open'), 'icon' => 'bi bi-display', 'body' => $body, 'footer' => $footer]) ?></div>

<?php ob_start() ?>
<?php if ($tokens === []): ?>
    <?= theme_component('empty_state', ['title' => lang('Auth.sessions.noTokens'), 'icon' => 'bi bi-key']) ?>
<?php else: ?>
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th scope="col"><?= esc(lang('Auth.sessions.tokenName')) ?></th>
                    <th scope="col" class="d-none d-md-table-cell"><?= esc(lang('Auth.sessions.started')) ?></th>
                    <th scope="col"><?= esc(lang('Auth.sessions.lastUsed')) ?></th>
                    <th scope="col" class="text-end"><span class="visually-hidden"><?= esc(lang('Auth.sessions.actions')) ?></span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tokens as $token): ?>
                    <tr>
                        <td><?= esc($token['name']) ?> <?= ui_badge($token['personal'] ? lang('Auth.sessions.personalToken') : lang('Auth.sessions.appToken'), $token['personal'] ? 'primary' : 'secondary') ?></td>
                        <td class="d-none d-md-table-cell"><?= ui_date($token['created'], 'datetime') ?></td>
                        <td><?= $token['last_used'] !== null ? ui_date($token['last_used'], 'datetime') : esc(lang('Auth.sessions.never')) ?></td>
                        <td class="text-end">
                            <?php if ($canRevoke): ?>
                                <form action="<?= esc(site_url('backend/profile/tokens/' . (int) $token['id'] . '/revoke')) ?>" method="post" class="d-inline">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= esc(lang('Auth.sessions.confirmRevokeToken')) ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= esc(lang('Auth.sessions.revokeToken')) ?></button>
                                </form>
                            <?php endif ?>
                        </td>
                    </tr>
                <?php endforeach ?>
            </tbody>
        </table>
    </div>
<?php endif ?>
<?php $tokensBody = (string) ob_get_clean() ?>
<?= theme_component('card', ['title' => lang('Auth.sessions.tokens'), 'icon' => 'bi bi-key', 'body' => $tokensBody]) ?>
<?= $this->endSection() ?>
