<?php
/**
 * "My API tokens": the new token (once), the creation form and the user's tokens.
 *
 * @var string                     $layout
 * @var list<array<string, mixed>> $tokens
 * @var list<string>               $scopes      Permissions the user may give a token
 * @var list<int>                  $days
 * @var int                        $defaultDays
 * @var string|null                $newToken
 */
$oldScopes = (array) (old('scopes') ?? []);
$oldScope  = old('scope') ?? 'all';
$oldDays   = (int) (old('days') ?? $defaultDays);
?>
<?= $this->extend($layout) ?>
<?= $this->section('content') ?>
<?php if (is_string($newToken) && $newToken !== ''): ?>
    <div class="alert alert-success" role="status">
        <p class="mb-2"><i class="bi bi-key me-1" aria-hidden="true"></i><strong><?= esc(lang('Api.personal.newToken')) ?></strong></p>
        <label for="api-new-token" class="form-label small"><?= esc(lang('Api.personal.copyNow')) ?></label>
        <input type="text" class="form-control font-monospace" id="api-new-token" value="<?= esc($newToken, 'attr') ?>" readonly autocomplete="off" spellcheck="false">
    </div>
<?php endif ?>

<div class="row g-4">
    <div class="col-xl-5">
        <?php ob_start() ?>
        <p class="small text-body-secondary"><?= esc(lang('Api.personal.intro')) ?></p>
        <div class="mb-3">
            <label for="api-token-name" class="form-label"><?= esc(lang('Api.personal.name')) ?></label>
            <input type="text" class="form-control" id="api-token-name" name="name" maxlength="64" required value="<?= esc((string) old('name'), 'attr') ?>" aria-describedby="api-token-name-help">
            <div id="api-token-name-help" class="form-text"><?= esc(lang('Api.personal.nameHelp')) ?></div>
        </div>
        <div class="mb-3">
            <label for="api-token-days" class="form-label"><?= esc(lang('Api.personal.expiry')) ?></label>
            <select class="form-select" id="api-token-days" name="days">
                <?php foreach ($days as $d): ?>
                    <option value="<?= $d ?>"<?= $d === $oldDays ? ' selected' : '' ?>><?= esc(lang('Api.personal.days', [$d])) ?></option>
                <?php endforeach ?>
            </select>
        </div>
        <fieldset class="mb-3">
            <legend class="form-label fs-6"><?= esc(lang('Api.personal.scopes')) ?></legend>
            <div class="form-check">
                <input class="form-check-input" type="radio" name="scope" id="api-scope-all" value="all"<?= $oldScope === 'all' ? ' checked' : '' ?>>
                <label class="form-check-label" for="api-scope-all"><?= esc(lang('Api.personal.scopeAll')) ?></label>
            </div>
            <div class="form-check mb-2">
                <input class="form-check-input" type="radio" name="scope" id="api-scope-some" value="some"<?= $oldScope === 'some' ? ' checked' : '' ?>>
                <label class="form-check-label" for="api-scope-some"><?= esc(lang('Api.personal.scopeSome')) ?></label>
            </div>
            <div class="border rounded p-2 ms-4">
                <?php foreach ($scopes as $i => $scope): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="scopes[]" id="api-scope-<?= $i ?>" value="<?= esc($scope, 'attr') ?>"<?= in_array($scope, $oldScopes, true) ? ' checked' : '' ?>>
                        <label class="form-check-label font-monospace small" for="api-scope-<?= $i ?>"><?= esc($scope) ?></label>
                    </div>
                <?php endforeach ?>
            </div>
            <div class="form-text"><?= esc(lang('Api.personal.scopesHelp')) ?></div>
        </fieldset>
        <p class="small text-body-secondary mb-0"><i class="bi bi-shield-lock me-1" aria-hidden="true"></i><?= esc(lang('Api.personal.reauthNote')) ?></p>
        <?php $fields = (string) ob_get_clean() ?>
        <?= theme_component('form_layout', [
            'action'      => site_url('backend/profile/api-tokens'),
            'fields'      => $fields,
            'title'       => lang('Api.personal.create'),
            'submitLabel' => lang('Api.personal.createButton'),
        ]) ?>
    </div>
    <div class="col-xl-7">
        <?php ob_start() ?>
        <?php if ($tokens === []): ?>
            <?= theme_component('empty_state', ['title' => lang('Api.personal.none'), 'icon' => 'bi bi-key']) ?>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle mb-0">
                    <thead>
                        <tr>
                            <th scope="col"><?= esc(lang('Api.tokens.name')) ?></th>
                            <th scope="col" class="d-none d-md-table-cell"><?= esc(lang('Api.tokens.scopes')) ?></th>
                            <th scope="col"><?= esc(lang('Api.tokens.lastUsed')) ?></th>
                            <th scope="col" class="d-none d-lg-table-cell"><?= esc(lang('Api.tokens.expires')) ?></th>
                            <th scope="col" class="text-end"><span class="visually-hidden"><?= esc(lang('Api.tokens.actions')) ?></span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tokens as $token): ?>
                            <tr>
                                <td><?= esc((string) $token['name']) ?> <?= ui_badge($token['personal'] ? lang('Api.tokens.personal') : lang('Api.tokens.app'), $token['personal'] ? 'primary' : 'secondary') ?></td>
                                <td class="d-none d-md-table-cell small font-monospace"><?= esc(implode(', ', $token['scopes'])) ?></td>
                                <td>
                                    <?= $token['last_used_at'] !== null ? ui_date($token['last_used_at'], 'datetime') : esc(lang('Api.tokens.neverUsed')) ?>
                                    <?php if ($token['ip'] !== ''): ?><div class="small text-body-secondary"><?= esc($token['ip']) ?></div><?php endif ?>
                                </td>
                                <td class="d-none d-lg-table-cell"><?= $token['expires_at'] !== null ? ui_date($token['expires_at'], 'datetime') : esc(lang('Api.tokens.never')) ?></td>
                                <td class="text-end">
                                    <form action="<?= esc(site_url('backend/profile/api-tokens/' . (int) $token['id'] . '/revoke')) ?>" method="post" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="<?= esc(lang('Api.personal.confirmRevoke'), 'attr') ?>"><i class="bi bi-trash me-1" aria-hidden="true"></i><?= esc(lang('Api.personal.revoke')) ?></button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach ?>
                    </tbody>
                </table>
            </div>
        <?php endif ?>
        <?php $body = (string) ob_get_clean() ?>
        <?= theme_component('card', ['title' => lang('Api.personal.list'), 'icon' => 'bi bi-key', 'body' => $body]) ?>
    </div>
</div>
<?= $this->endSection() ?>
