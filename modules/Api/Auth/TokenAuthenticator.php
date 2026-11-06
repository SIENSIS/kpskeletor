<?php

declare(strict_types=1);

namespace Modules\Api\Auth;

use CodeIgniter\HTTP\IncomingRequest;
use Kpapy\Auth\ShieldTokenAuthenticator;
use Modules\Api\Models\TokenIpModel;
use Throwable;

/**
 * Shield access tokens (`Authorization: Bearer <token>`, kpapy's authenticator) that also record
 * the IP address each token was last used from, shown next to the token in the profile and in
 * the administrators' list. The row is written only when the address changes; a change is a
 * security entry of the activity log (`token_new_ip`).
 */
final class TokenAuthenticator extends ShieldTokenAuthenticator
{
    public function authenticate(IncomingRequest $request): ?array
    {
        $result = parent::authenticate($request);

        if ($result !== null && is_object($result['token']) && isset($result['token']->id)) {
            try {
                $previous = (new TokenIpModel())->record((int) $result['token']->id, (int) $result['user']->id, $request->getIPAddress());

                if ($previous !== null) {
                    security_log('Api', 'token_new_ip', [
                        'user_id'   => (int) $result['user']->id,
                        'resource'  => 'api',
                        'record_id' => (int) $result['token']->id,
                        'summary'   => (string) ($result['token']->name ?? ''),
                        'before'    => ['ip' => $previous],
                        'after'     => ['ip' => $request->getIPAddress()],
                    ]);
                }
            } catch (Throwable $e) {
                log_message('error', 'The address of API token {id} could not be recorded: {message}', ['id' => $result['token']->id, 'message' => $e->getMessage()]);
            }
        }

        return $result;
    }
}
