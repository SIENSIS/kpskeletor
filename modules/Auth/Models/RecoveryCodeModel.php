<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use CodeIgniter\Model;

/**
 * Hashed single-use 2FA recovery codes (`user_recovery_codes`).
 */
final class RecoveryCodeModel extends Model
{
    protected $table         = 'user_recovery_codes';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'code_hash', 'used_at', 'created_at'];

    public function remaining(int $userId): int
    {
        return $this->where('user_id', $userId)->where('used_at', null)->countAllResults();
    }
}
