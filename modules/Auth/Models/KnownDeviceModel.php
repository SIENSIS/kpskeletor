<?php

declare(strict_types=1);

namespace Modules\Auth\Models;

use CodeIgniter\Model;

/**
 * Devices a user has signed in from (`known_devices`), by the hash of the device cookie.
 */
final class KnownDeviceModel extends Model
{
    protected $table         = 'known_devices';
    protected $returnType    = 'array';
    protected $allowedFields = ['user_id', 'device_hash', 'user_agent', 'ip_address', 'first_seen_at', 'last_seen_at'];
}
