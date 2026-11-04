<?php

declare(strict_types=1);

namespace Modules\Core\Frontend;

use Closure;
use CodeIgniter\HTTP\IncomingRequest;
use Modules\Core\Theme\ThemeOptions;

/**
 * Light, dark or auto colour mode of the current page (Bootstrap 5.3 colour modes).
 *
 * Order: the mode forced by the theme option `forceMode`; the logged-in user's preference (the
 * profile, once the Users module stores it); the `kp_theme` cookie written by the switch; the
 * theme option `defaultMode` (`auto`). The server prints `data-bs-theme` for an explicit mode,
 * so the page never flashes the wrong colours; `auto` is resolved before the first paint by
 * `core::js/theme-mode.js`.
 */
final class ThemeMode
{
    public const MODES = ['light', 'dark', 'auto'];

    public const COOKIE = 'kp_theme';

    /** @var Closure(): ?string */
    private readonly Closure $cookie;

    /** @var Closure(): ?string */
    private readonly Closure $preference;

    /**
     * @param (Closure(): ?string)|null $cookie     reads the kp_theme cookie
     * @param (Closure(): ?string)|null $preference reads the logged-in user's stored choice
     */
    public function __construct(
        private readonly ThemeOptions $options,
        ?Closure $cookie = null,
        ?Closure $preference = null,
    ) {
        $this->cookie = $cookie ?? static function (): ?string {
            $request = service('request');
            $value   = $request instanceof IncomingRequest ? $request->getCookie(self::COOKIE) : null;

            return is_string($value) ? $value : null;
        };
        $this->preference = $preference ?? static fn (): ?string => null;
    }

    /**
     * The mode forced by the theme (`light` or `dark`), or null.
     */
    public function forced(): ?string
    {
        $forced = $this->options->get('forceMode');

        return in_array($forced, ['light', 'dark'], true) ? $forced : null;
    }

    public function current(): string
    {
        $forced = $this->forced();

        if ($forced !== null) {
            return $forced;
        }

        foreach ([($this->preference)(), ($this->cookie)(), $this->options->get('defaultMode')] as $mode) {
            if (is_string($mode) && in_array($mode, self::MODES, true)) {
                return $mode;
            }
        }

        return 'auto';
    }

    /**
     * Whether the layouts show the switch (theme option `modeSwitch`, and no forced mode).
     */
    public function switchVisible(): bool
    {
        return $this->forced() === null && $this->options->get('modeSwitch') !== false;
    }

    /**
     * Attributes of the <html> element: ` data-theme-mode="auto"`, or
     * ` data-bs-theme="dark" data-theme-mode="dark"` for an explicit mode.
     */
    public function attributes(?string $mode = null): string
    {
        $mode ??= $this->current();
        $mode = in_array($mode, self::MODES, true) ? $mode : 'auto';

        return ($mode === 'auto' ? '' : ' data-bs-theme="' . $mode . '"') . ' data-theme-mode="' . $mode . '"';
    }
}
