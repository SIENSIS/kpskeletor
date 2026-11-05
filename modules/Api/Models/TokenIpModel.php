<?php

declare(strict_types=1);

namespace Modules\Api\Models;

use CodeIgniter\I18n\Time;
use CodeIgniter\Model;

/**
 * Last IP address of each API access token (`api_token_ips`).
 */
final class TokenIpModel extends Model
{
    protected $table          = 'api_token_ips';
    protected $primaryKey     = 'token_id';
    protected $returnType     = 'array';
    protected $useAutoIncrement = false;
    protected $allowedFields  = ['token_id', 'user_id', 'ip_address', 'updated_at'];
    protected $useTimestamps  = false;

    /**
     * Stores the address a token was used from (only when it changed).
     */
    public function record(int $tokenId, int $userId, string $ip): void
    {
        $ip  = mb_substr($ip, 0, 45);
        $row = $this->find($tokenId);

        if (is_array($row) && $row['ip_address'] === $ip) {
            return;
        }

        $data = ['user_id' => $userId, 'ip_address' => $ip, 'updated_at' => Time::now()->format('Y-m-d H:i:s')];

        if (is_array($row)) {
            $this->builder()->where('token_id', $tokenId)->update($data);
        } else {
            $this->builder()->insert($data + ['token_id' => $tokenId]);
        }
    }

    /**
     * Last address of each token.
     *
     * @param list<int> $tokenIds
     *
     * @return array<int, string> token id => address
     */
    public function forTokens(array $tokenIds): array
    {
        if ($tokenIds === []) {
            return [];
        }

        $result = [];

        foreach ($this->builder()->whereIn('token_id', $tokenIds)->get()->getResultArray() as $row) {
            $result[(int) $row['token_id']] = (string) $row['ip_address'];
        }

        return $result;
    }
}
