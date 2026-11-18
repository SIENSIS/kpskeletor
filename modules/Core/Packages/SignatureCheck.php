<?php

declare(strict_types=1);

namespace Modules\Core\Packages;

use Modules\Core\Security\Minisign;
use Modules\Core\Security\MinisignRefused;

/**
 * Verifies the minisign signature (`<package>.zip.minisig`) of a module or theme package against
 * the trusted publisher keys of `Config\Packages::$publicKeys` (docs/installing-modules.md).
 * The format and the checks are the Core's `Minisign` (the same as release archives,
 * docs/updates.md); this class only turns a refusal into a `PackageRefused` message.
 */
final class SignatureCheck
{
    private readonly Minisign $minisign;

    /**
     * @param list<string> $publicKeys minisign public keys (base64 lines, or whole `.pub` files)
     */
    public function __construct(array $publicKeys)
    {
        $this->minisign = new Minisign($publicKeys);
    }

    /**
     * @throws PackageRefused when the file is not signed by a trusted key
     */
    public function verify(string $file, string $signature): void
    {
        try {
            $this->minisign->verify($file, $signature);
        } catch (MinisignRefused $e) {
            throw new PackageRefused(match ($e->reason) {
                Minisign::NO_KEY      => lang('Core.packages.noPublisherKey'),
                Minisign::FORMAT      => lang('Core.packages.signatureFormat'),
                Minisign::UNKNOWN_KEY => lang('Core.packages.unknownKey', [$e->keyId]),
                Minisign::UNREADABLE  => lang('Core.packages.unreadable'),
                default               => lang('Core.packages.badSignature'),
            });
        }
    }

    /**
     * @return array<string, string> key id (8 bytes) => Ed25519 public key (32 bytes)
     */
    public function keys(): array
    {
        return $this->minisign->keys();
    }
}
