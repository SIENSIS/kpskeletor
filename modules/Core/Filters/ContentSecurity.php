<?php

declare(strict_types=1);

namespace Modules\Core\Filters;

use Closure;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\ContentSecurityPolicy;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Settings\SecurityRules;
use Throwable;

/**
 * Global filter (`cspmodules`): adds the Content-Security-Policy sources that enabled modules
 * declare in their manifest (`$csp`, validated by `modules:sync`: never `unsafe-inline`,
 * `unsafe-eval`, `*`, `http:` or `data:`) to the strict default policy of
 * Config\ContentSecurityPolicy. Sources added to `script-src`/`style-src` also go to
 * `script-src-elem`/`style-src-elem`, which the browser checks first. `'none'` is never added
 * (it could only weaken nothing and breaks the other sources).
 *
 * It also adds the extra origins an administrator stored in Settings › Security
 * (`Core.cspScriptSrc`, `Core.cspImgSrc`…, see SETTINGS), checked again here with
 * SecurityRules::isAllowedCspSource() (no keywords or schemes; no wildcards for scripts and
 * styles) so a value written another way cannot weaken the policy.
 */
final class ContentSecurity implements FilterInterface
{
    /** Directive => method of CodeIgniter's ContentSecurityPolicy. */
    private const METHODS = [
        'default-src'     => ['addDefaultSrc'],
        'script-src'      => ['addScriptSrc', 'addScriptSrcElem'],
        'style-src'       => ['addStyleSrc', 'addStyleSrcElem'],
        'img-src'         => ['addImageSrc'],
        'font-src'        => ['addFontSrc'],
        'connect-src'     => ['addConnectSrc'],
        'media-src'       => ['addMediaSrc'],
        'object-src'      => ['addObjectSrc'],
        'frame-src'       => ['addFrameSrc'],
        'child-src'       => ['addChildSrc'],
        'worker-src'      => ['addWorkerSrc'],
        'manifest-src'    => ['addManifestSrc'],
        'form-action'     => ['addFormAction'],
        'frame-ancestors' => ['addFrameAncestor'],
    ];

    /** Directive => setting with the extra origins of Settings › Security. */
    public const SETTINGS = [
        'script-src'  => 'Core.cspScriptSrc',
        'style-src'   => 'Core.cspStyleSrc',
        'img-src'     => 'Core.cspImgSrc',
        'font-src'    => 'Core.cspFontSrc',
        'connect-src' => 'Core.cspConnectSrc',
        'frame-src'   => 'Core.cspFrameSrc',
        'media-src'   => 'Core.cspMediaSrc',
    ];

    /**
     * @param list<string>|null $arguments
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        try {
            $sources = (new DeclarationRegistry())->cspSources();
        } catch (Throwable) {
            return null; // not installed yet: the default policy applies
        }

        self::apply(service('csp'), $sources);
        self::apply(service('csp'), self::settingSources());

        return null;
    }

    /**
     * The origins stored in Settings › Security, only those SecurityRules accepts.
     *
     * @param (Closure(string): mixed)|null $read reads a setting (tests); defaults to setting()
     *
     * @return array<string, list<string>> directive => origins
     */
    public static function settingSources(?Closure $read = null): array
    {
        $read ??= static function (string $name): mixed {
            try {
                return setting($name);
            } catch (Throwable) {
                return null;
            }
        };

        $sources = [];

        foreach (self::SETTINGS as $directive => $name) {
            $strict = in_array($directive, ['script-src', 'style-src'], true);

            foreach (SecurityRules::lines($read($name)) as $origin) {
                if (SecurityRules::isAllowedCspSource($origin, $strict)) {
                    $sources[$directive][] = $origin;
                }
            }
        }

        return $sources;
    }

    /**
     * @param list<string>|null $arguments
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    /**
     * @param array<string, list<string>> $sources directive => sources
     */
    public static function apply(ContentSecurityPolicy $csp, array $sources): void
    {
        foreach ($sources as $directive => $list) {
            foreach ($list as $source) {
                $source = trim($source);

                if ($source === '' || in_array(strtolower($source), [...ManifestValidator::FORBIDDEN_CSP_SOURCES, "'none'"], true)) {
                    continue;
                }

                foreach (self::METHODS[$directive] ?? [] as $method) {
                    $csp->{$method}($source);
                }
            }
        }
    }
}
