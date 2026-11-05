<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use CodeIgniter\Model;

/**
 * Profile fields of a user (`user_profiles`, one row per user).
 */
final class UserProfileModel extends Model
{
    protected $table         = 'user_profiles';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = ['user_id', 'name', 'locale', 'timezone', 'theme_mode', 'avatar', 'birth_date'];

    /**
     * The user's profile; an empty profile when the user has none yet.
     *
     * @return array<string, mixed>
     */
    public function forUser(int $userId): array
    {
        $row = $this->where('user_id', $userId)->first();

        return is_array($row) ? $row : ['user_id' => $userId, 'name' => null, 'locale' => null, 'timezone' => null, 'theme_mode' => null, 'avatar' => null, 'birth_date' => null];
    }

    /**
     * Inserts or updates the user's profile with the given allowed fields.
     *
     * @param array<string, mixed> $fields
     */
    public function saveFor(int $userId, array $fields): void
    {
        $fields = array_intersect_key($fields, array_flip(array_diff($this->allowedFields, ['user_id'])));
        $row    = $this->where('user_id', $userId)->first();

        if (is_array($row)) {
            $this->update($row['id'], $fields);

            return;
        }

        $this->insert(['user_id' => $userId] + $fields);
    }
}
