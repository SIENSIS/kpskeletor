<?php

declare(strict_types=1);

namespace Modules\Core\Entities;

use CodeIgniter\Entity\Entity;

/**
 * Base entity of every module (dates and author columns follow BaseModel's conventions).
 */
abstract class BaseEntity extends Entity
{
    /** @var list<string> */
    protected $dates = ['created_at', 'updated_at', 'deleted_at'];

    /** @var array<string, string> */
    protected $casts = [
        'id'         => '?integer',
        'created_by' => '?integer',
        'updated_by' => '?integer',
    ];
}
