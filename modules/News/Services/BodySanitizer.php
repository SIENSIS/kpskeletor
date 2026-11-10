<?php

declare(strict_types=1);

namespace Modules\News\Services;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitises the HTML body of a post with HTMLPurifier before it is stored (specification
 * "Security baseline": rich text through HTMLPurifier). Allowed: paragraphs, headings, lists,
 * emphasis, links (`http`, `https`, `mailto`; external ones open with `rel="noopener noreferrer"`),
 * images over `https`, quotes, code, tables. Scripts, event handlers, styles, iframes, forms and
 * `javascript:`/`data:` URLs are removed.
 */
final class BodySanitizer
{
    private static ?HTMLPurifier $purifier = null;

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        return trim(self::purifier()->purify($html));
    }

    private static function purifier(): HTMLPurifier
    {
        if (self::$purifier !== null) {
            return self::$purifier;
        }

        $config = HTMLPurifier_Config::createDefault();
        $cache  = WRITEPATH . 'cache/htmlpurifier';

        if (! is_dir($cache)) {
            @mkdir($cache, 0750, true);
        }

        $config->set('Cache.SerializerPath', is_dir($cache) && is_writable($cache) ? $cache : null);
        $config->set('Core.Encoding', 'UTF-8');
        $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
        $config->set('HTML.Allowed', 'p,br,hr,h2,h3,h4,h5,h6,strong,b,em,i,u,s,sub,sup,blockquote,pre,code,'
            . 'ul,ol,li,a[href|title|target],img[src|alt|width|height],'
            . 'table,thead,tbody,tr,th[colspan|rowspan],td[colspan|rowspan],caption');
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        return self::$purifier = new HTMLPurifier($config);
    }
}
