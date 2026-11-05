<?php

declare(strict_types=1);

namespace Modules\Api\Controllers;

use CodeIgniter\HTTP\ResponseInterface;
use Modules\Api\Models\TokenIpModel;
use Modules\Api\Services\PersonalTokens;
use Modules\Core\Controllers\BackendController;

/**
 * Every user's API access tokens (resource `api`, kpcrudy): list and details (`api.list`,
 * `api.show`) and revocation (`api.delete`, deletes the token). Tokens are created only by their
 * owner (profile or app login); nothing here shows a secret.
 */
final class Tokens extends BackendController
{
    public function index(): ResponseInterface|string
    {
        $crud = $this->crud('api')
            ->setTable('auth_identities')
            ->where('type', 'access_token')
            ->setSubject(lang('Api.tokens.token'), lang('Api.tokens.title'))
            ->setRelation('user_id', 'users', 'username')
            ->columns(['user_id', 'name', 'kind', 'extra', 'last_used_at', 'ip', 'expires'])
            ->showFields(['user_id', 'name', 'kind', 'extra', 'created_at', 'last_used_at', 'ip', 'expires'])
            ->searchFields(['name', 'user_id'])
            ->unsortable(['kind', 'extra', 'ip'])
            ->displayAs([
                'user_id'      => lang('Api.tokens.user'),
                'name'         => lang('Api.tokens.name'),
                'kind'         => lang('Api.tokens.kind'),
                'extra'        => lang('Api.tokens.scopes'),
                'created_at'   => lang('Api.tokens.created'),
                'last_used_at' => lang('Api.tokens.lastUsed'),
                'ip'           => lang('Api.tokens.ip'),
                'expires'      => lang('Api.tokens.expires'),
            ])
            ->callbackColumn('kind', static fn ($value, array $row): string => ui_badge(
                PersonalTokens::isPersonal($row) ? lang('Api.tokens.personal') : lang('Api.tokens.app'),
                PersonalTokens::isPersonal($row) ? 'primary' : 'secondary',
            ))
            ->callbackColumn('extra', static fn ($value): string => esc(implode(', ', PersonalTokens::scopesOf($value))))
            ->callbackColumn('ip', static fn ($value, array $row): string => esc((new TokenIpModel())->forTokens([(int) ($row['id'] ?? 0)])[(int) ($row['id'] ?? 0)] ?? ''))
            ->callbackColumn('expires', static fn ($value): string => $value === null || $value === '' ? esc(lang('Api.tokens.never')) : ui_date((string) $value, 'datetime'))
            ->defaultOrder('last_used_at', 'desc')
            ->unsetAdd()->unsetEdit()->unsetExport()
            ->afterDelete(static function (string $id, array $row): void {
                log_message('notice', 'API token {token} of user {user} revoked by user {by}.', ['token' => $id, 'user' => $row['user_id'] ?? 0, 'by' => auth()->id() ?? 0]);
            });

        return $this->renderCrud($crud);
    }
}
