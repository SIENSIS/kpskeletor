<?php

declare(strict_types=1);

namespace Config;

use Modules\Core\Security\MaskKey;

/**
 * kpapy configuration (every option and its default: vendor/siensis/kpapy/src/Config/Kpapy.php).
 *
 * Session 09 only shares the mask key with kpcrudy (`app.maskKey`), so an id has the same code in
 * the back office and in the API; session 10 ("kpapy integration") completes it (resolver,
 * deny by default, tokens, upload path…).
 */
class Kpapy extends \Kpapy\Config\Kpapy
{
    /** `app.maskKey`, shared with kpcrudy (set by the constructor). */
    public string $maskKey = '';

    public function __construct()
    {
        parent::__construct();

        $this->maskKey = MaskKey::value();
    }
}
