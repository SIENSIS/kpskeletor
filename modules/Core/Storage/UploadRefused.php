<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use RuntimeException;

/**
 * An upload refused by the upload rules. `reason()` is a code (`type`, `forbidden`, `mime`,
 * `too_large`, `empty`, `invalid`, `image`, `svg`, `content`); `getMessage()` is the translated
 * message for the user (`Core.storage.refused.<reason>`), which never contains the file's
 * contents.
 */
final class UploadRefused extends RuntimeException
{
    public const REASONS = ['invalid', 'empty', 'too_large', 'type', 'forbidden', 'mime', 'image', 'svg', 'content'];

    /**
     * @param list<string|int> $args
     */
    public function __construct(private readonly string $reason, array $args = [])
    {
        parent::__construct(lang('Core.storage.refused.' . $reason, $args));
    }

    public function reason(): string
    {
        return $this->reason;
    }
}
