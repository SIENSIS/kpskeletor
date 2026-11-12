<?php

declare(strict_types=1);

namespace Modules\Core\Security;

use HTMLPurifier;
use HTMLPurifier_Config;

/**
 * Sanitises rich text (news bodies, pages, event descriptions…) with HTMLPurifier before it is
 * stored and again when it is shown (specification "Security baseline": rich text through
 * HTMLPurifier). Allowed: paragraphs, headings, lists,
 * emphasis, links (`http`, `https`, `mailto`; external ones open with `rel="noopener noreferrer"`),
 * images of the site itself only (relative URLs or the site's hosts: SiteImagesOnly, D-17-08), quotes,
 * code, tables. Scripts, event handlers, styles, iframes, forms and
 * `javascript:`/`data:` URLs are removed.
 *
 * Alignment (`style="text-align: …"`, `align`, the editor's `ql-align-*`) is kept as one of the
 * classes `text-center`, `text-right`, `text-justify` on paragraphs, headings, list items, quotes
 * and table cells (AlignmentToClass); no other class and no `style` attribute survive.
 */
final class HtmlSanitizer
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
        $config->set('HTML.DefinitionID', 'kpskeletor-rich-text');
        $config->set('HTML.DefinitionRev', 2);
        $config->set('HTML.Allowed', 'p[class],br,hr,h2[class],h3[class],h4[class],h5[class],h6[class],strong,b,em,i,u,s,sub,sup,'
            . 'blockquote[class],pre,code,ul,ol,li[class],a[href|title|target],img[src|alt|width|height],'
            . 'table,thead,tbody,tr,th[colspan|rowspan|class],td[colspan|rowspan|class],caption');
        $config->set('Attr.AllowedClasses', array_values(array_unique(AlignmentToClass::CLASSES)));
        $config->set('URI.AllowedSchemes', ['http' => true, 'https' => true, 'mailto' => true]);
        $config->set('Attr.AllowedFrameTargets', ['_blank']);
        $config->set('HTML.TargetNoopener', true);
        $config->set('HTML.TargetNoreferrer', true);
        $config->set('AutoFormat.RemoveEmpty', true);

        $config->set('URI.DefinitionID', 'kpskeletor-site-images');
        $config->set('URI.DefinitionRev', 1);
        $uri = $config->maybeGetRawURIDefinition();

        if ($uri !== null) {
            $uri->addFilter(new SiteImagesOnly(), $config);
        }

        $definition = $config->maybeGetRawHTMLDefinition();

        if ($definition !== null) {
            $definition->info_attr_transform_pre['kp-alignment'] = new AlignmentToClass();
        }

        return self::$purifier = new HTMLPurifier($config);
    }
}
