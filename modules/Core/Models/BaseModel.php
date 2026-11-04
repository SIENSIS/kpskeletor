<?php

declare(strict_types=1);

namespace Modules\Core\Models;

use CodeIgniter\Model;

/**
 * Base model of every module: timestamps, soft deletes and `created_by` / `updated_by`.
 *
 * The author columns are filled only when they are listed in `$allowedFields`; the values sent
 * by the client are always replaced by the current user (or null), so they cannot be forged.
 * Audit hooks (activity log) are added by a later session.
 */
abstract class BaseModel extends Model
{
    protected $returnType     = 'array';
    protected $useTimestamps  = true;
    protected $useSoftDeletes = true;
    protected $dateFormat     = 'datetime';
    protected $createdField   = 'created_at';
    protected $updatedField   = 'updated_at';
    protected $deletedField   = 'deleted_at';

    /** Column that stores the creating user's id ('' to disable). */
    protected string $createdByField = 'created_by';

    /** Column that stores the last updating user's id ('' to disable). */
    protected string $updatedByField = 'updated_by';

    /**
     * Registers the author callbacks first. Subclasses that override initialize() must call
     * parent::initialize().
     */
    protected function initialize(): void
    {
        parent::initialize();

        if (! in_array('stampAuthorOnInsert', $this->beforeInsert, true)) {
            array_unshift($this->beforeInsert, 'stampAuthorOnInsert');
        }

        if (! in_array('stampAuthorOnUpdate', $this->beforeUpdate, true)) {
            array_unshift($this->beforeUpdate, 'stampAuthorOnUpdate');
        }
    }

    /**
     * @param array{data?: array<string, mixed>} $event
     *
     * @return array{data?: array<string, mixed>}
     */
    protected function stampAuthorOnInsert(array $event): array
    {
        if (! isset($event['data'])) {
            return $event;
        }

        $user = $this->currentUserId();

        foreach ([$this->createdByField, $this->updatedByField] as $field) {
            if ($field !== '' && in_array($field, $this->allowedFields, true)) {
                $event['data'][$field] = $user;
            }
        }

        return $event;
    }

    /**
     * @param array{data?: array<string, mixed>} $event
     *
     * @return array{data?: array<string, mixed>}
     */
    protected function stampAuthorOnUpdate(array $event): array
    {
        if (! isset($event['data'])) {
            return $event;
        }

        if ($this->createdByField !== '') {
            unset($event['data'][$this->createdByField]);
        }

        if ($this->updatedByField !== '' && in_array($this->updatedByField, $this->allowedFields, true)) {
            $event['data'][$this->updatedByField] = $this->currentUserId();
        }

        return $event;
    }

    /**
     * The logged-in user's id, or null (console, guests).
     */
    protected function currentUserId(): ?int
    {
        try {
            $id = function_exists('auth') ? auth()->id() : null;
        } catch (\Throwable) {
            return null;
        }

        return is_numeric($id) ? (int) $id : null;
    }
}
