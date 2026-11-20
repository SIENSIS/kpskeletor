<?php

declare(strict_types=1);

namespace Modules\Devtools\Packages;

/**
 * A signed package: public facts only (file, key id, public key line, SHA-256, trusted comment).
 */
final class SignResult
{
    public function __construct(
        public readonly string $file,
        public readonly string $keyId,
        public readonly string $publicKey,
        public readonly string $sha256,
        public readonly string $trustedComment,
    ) {
    }

    /**
     * @return array{file: string, keyId: string, publicKey: string, sha256: string, trustedComment: string}
     */
    public function toArray(): array
    {
        return [
            'file'           => $this->file,
            'keyId'          => $this->keyId,
            'publicKey'      => $this->publicKey,
            'sha256'         => $this->sha256,
            'trustedComment' => $this->trustedComment,
        ];
    }
}
