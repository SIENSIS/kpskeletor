<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use CodeIgniter\Model;

/**
 * Tracked login sessions (`user_sessions`). Timestamps are written by SessionTracker.
 */
final class UserSessionModel extends Model
{
    protected $table         = 'user_sessions';
    protected $returnType    = 'array';
    protected $allowedFields = [
        'user_id', 'token_hash', 'ip_address', 'user_agent', 'remembered', 'remember_selector',
        'regenerate', 'created_at', 'last_seen_at', 'ended_at', 'end_reason',
    ];

    /**
     * Active sessions of a user, the most recent first.
     *
     * @return list<array<string, mixed>>
     */
    public function activeFor(int $userId): array
    {
        return $this->where('user_id', $userId)->where('ended_at', null)->orderBy('last_seen_at', 'DESC')->orderBy('id', 'DESC')->findAll();
    }
}
