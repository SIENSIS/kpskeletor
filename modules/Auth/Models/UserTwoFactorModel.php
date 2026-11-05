<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use CodeIgniter\Model;

/**
 * Each user's 2FA method and encrypted TOTP secret (`user_two_factor`).
 */
final class UserTwoFactorModel extends Model
{
    protected $table         = 'user_two_factor';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'method', 'totp_secret', 'totp_last_step', 'enabled_at', 'updated_at'];

    /**
     * @return array<string, mixed>|null
     */
    public function forUser(int $userId): ?array
    {
        $row = $this->where('user_id', $userId)->first();

        return is_array($row) ? $row : null;
    }
}
