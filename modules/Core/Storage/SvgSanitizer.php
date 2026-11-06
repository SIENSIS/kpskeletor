<?php

declare(strict_types=1);

namespace Modules\Core\Storage;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Sanitises an SVG image with an allow-list (used only when `Core.uploadAllowSvg` is on): only
 * drawing elements and presentation attributes are kept. Scripts, event handlers, `<style>`,
 * `style` attributes, `<foreignObject>`, animations, external references (`href` other than a
 * local `#fragment`), `javascript:` / `data:` URLs and DOCTYPEs (entities) are removed or
 * refused. The result is also served with a sandboxing CSP (files/private) or the upload
 * folder's web server rules (public disk).
 */
final class SvgSanitizer
{
    /** Maximum size of an SVG document (bytes). */
    public const MAX_BYTES = 2_097_152;

    private const ELEMENTS = [
        'svg', 'g', 'defs', 'symbol', 'use', 'title', 'desc', 'metadata',
        'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon',
        'text', 'tspan', 'textpath',
        'lineargradient', 'radialgradient', 'stop', 'pattern', 'clippath', 'mask', 'marker',
        'filter', 'feblend', 'fecolormatrix', 'fecomponenttransfer', 'fecomposite', 'feflood',
        'fegaussianblur', 'femerge', 'femergenode', 'feoffset', 'fefunca', 'fefuncb', 'fefuncg',
        'fefuncr', 'femorphology', 'fedropshadow',
    ];

    private const ATTRIBUTES = [
        'id', 'class', 'version', 'viewbox', 'preserveaspectratio', 'width', 'height', 'x', 'y',
        'x1', 'x2', 'y1', 'y2', 'cx', 'cy', 'r', 'rx', 'ry', 'fx', 'fy', 'd', 'points', 'pathlength',
        'transform', 'fill', 'fill-opacity', 'fill-rule', 'stroke', 'stroke-width', 'stroke-opacity',
        'stroke-linecap', 'stroke-linejoin', 'stroke-miterlimit', 'stroke-dasharray',
        'stroke-dashoffset', 'opacity', 'color', 'display', 'visibility', 'clip-path', 'clip-rule',
        'mask', 'filter', 'marker-start', 'marker-mid', 'marker-end', 'offset', 'stop-color',
        'stop-opacity', 'gradientunits', 'gradienttransform', 'spreadmethod', 'patternunits',
        'patterncontentunits', 'patterntransform', 'clippathunits', 'maskunits', 'maskcontentunits',
        'markerwidth', 'markerheight', 'markerunits', 'refx', 'refy', 'orient', 'font-family',
        'font-size', 'font-weight', 'font-style', 'text-anchor', 'dominant-baseline',
        'letter-spacing', 'word-spacing', 'dx', 'dy', 'rotate', 'textlength', 'lengthadjust',
        'in', 'in2', 'result', 'stddeviation', 'mode', 'type', 'values', 'operator', 'k1', 'k2',
        'k3', 'k4', 'flood-color', 'flood-opacity', 'filterunits', 'primitiveunits', 'radius',
        'tablevalues', 'slope', 'intercept', 'amplitude', 'exponent', 'href', 'xlink:href',
        'xmlns', 'xmlns:xlink', 'xml:space', 'role', 'aria-label', 'aria-hidden', 'focusable',
    ];

    private const SVG_NS = 'http://www.w3.org/2000/svg';

    /**
     * @throws StorageException when the document is not a valid SVG
     */
    public function sanitize(string $svg): string
    {
        if ($svg === '' || strlen($svg) > self::MAX_BYTES || preg_match('/<!DOCTYPE|<!ENTITY/i', $svg) === 1) {
            throw StorageException::invalidSvg();
        }

        $document                     = new DOMDocument();
        $document->preserveWhiteSpace = true;
        $document->resolveExternals   = false;
        $document->substituteEntities = false;

        $previous = libxml_use_internal_errors(true);
        $loaded   = $document->loadXML($svg, LIBXML_NONET | LIBXML_NOCDATA);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->documentElement;

        if (! $loaded || ! $root instanceof DOMElement || strtolower($root->localName ?? '') !== 'svg'
            || ($root->namespaceURI !== null && $root->namespaceURI !== self::SVG_NS)) {
            throw StorageException::invalidSvg();
        }

        $this->clean($root);

        $output = $document->saveXML($root);

        if ($output === false || $output === '') {
            throw StorageException::invalidSvg();
        }

        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . $output . "\n";
    }

    private function clean(DOMElement $element): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            /** @var \DOMAttr $attribute */
            $name  = strtolower($attribute->nodeName);
            $value = $attribute->value;

            if (! in_array($name, self::ATTRIBUTES, true) || ! $this->safeValue($name, $value)) {
                $element->removeAttributeNode($attribute);
            }
        }

        foreach (iterator_to_array($element->childNodes) as $child) {
            /** @var DOMNode $child */
            if ($child instanceof DOMElement) {
                if (! in_array(strtolower($child->localName ?? ''), self::ELEMENTS, true)
                    || ($child->namespaceURI !== null && $child->namespaceURI !== self::SVG_NS)) {
                    $element->removeChild($child);

                    continue;
                }

                $this->clean($child);

                continue;
            }

            if (! in_array($child->nodeType, [XML_TEXT_NODE, XML_CDATA_SECTION_NODE], true)) {
                $element->removeChild($child); // comments, processing instructions
            }
        }
    }

    private function safeValue(string $name, string $value): bool
    {
        $normalised = strtolower((string) preg_replace('/[\s\x00-\x1F]+/', '', $value));

        if (str_contains($normalised, 'javascript:') || str_contains($normalised, 'data:') || str_contains($normalised, 'expression(')) {
            return false;
        }

        if ($name === 'href' || $name === 'xlink:href') {
            return str_starts_with(trim($value), '#');
        }

        // url(...) references only to local fragments (gradients, clip paths, filters).
        if (str_contains($normalised, 'url(')) {
            return preg_match_all('/url\(\s*[\'"]?\s*#/i', $value) === substr_count($normalised, 'url(');
        }

        return true;
    }
}
