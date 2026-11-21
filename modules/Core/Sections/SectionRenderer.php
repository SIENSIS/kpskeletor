<?php

declare(strict_types=1);

namespace Modules\Core\Sections;

use Modules\Core\Filters\ContentSecurity;
use Throwable;

/**
 * Renders a list of sections in order (docs/sections.md, "Rendering"): hidden sections and
 * sections of a type that is no longer offered (a disabled module) are left out; each one is
 * wrapped in a `<section>` whose classes come from its options (`SectionOptions::classes()`),
 * with its anchor as id, the background image as a decorative `<img>` (no inline styles: strict
 * CSP) and its content in the theme's `.container` unless its width is `full`. A section whose
 * type renders nothing (e.g. an embed whose host is no longer allowed) is left out; in a preview,
 * the type's `warnings()` are shown above it. The CSP sources a shown section needs
 * (`SectionType::cspSources()`) are added to this response's policy only.
 */
final class SectionRenderer
{
    public function __construct(private readonly SectionRegistry $registry = new SectionRegistry())
    {
    }

    /**
     * @param list<SectionData> $sections
     */
    public function render(array $sections, RenderContext $context): string
    {
        $html  = '';
        $index = 0;

        foreach ($sections as $section) {
            if ($section->hidden) {
                continue;
            }

            $one = $this->renderOne($section, $context->at($index));

            if ($one !== '') {
                $html .= $one;
                $index++;
            }
        }

        return $html;
    }

    public function renderOne(SectionData $section, RenderContext $context): string
    {
        $type = $this->registry->get($section->type);

        if ($type === null) {
            return '';
        }

        try {
            $content  = $type->render($section, $context);
            $warnings = $context->preview ? $type->warnings($section, $context->user) : [];
        } catch (Throwable $e) {
            log_message('error', 'Section {type} could not be rendered: {message}', ['type' => $section->type, 'message' => $e->getMessage()]);

            return '';
        }

        // Problems only the editor sees (the preview); a section with nothing to show is left out.
        if ($warnings !== []) {
            $content = theme_view('Modules\Core\Views\sections\warnings', ['warnings' => $warnings]) . $content;
        }

        if (trim($content) === '') {
            return '';
        }

        ContentSecurity::apply(service('csp'), $type->cspSources($section));

        $options = SectionOptions::normalise($section->options, $type);
        $dark    = in_array($options['background'], SectionOptions::DARK_BACKGROUNDS, true);
        $image   = $options['background'] === 'image' ? $this->backgroundImage($options['background_image']) : '';

        return '<section class="' . esc(SectionOptions::classes($options, $type), 'attr') . '"'
            . ($options['anchor'] !== '' ? ' id="' . esc($options['anchor'], 'attr') . '"' : '')
            . ($dark ? ' data-bs-theme="dark"' : '')
            . ' data-section-type="' . esc($type->key(), 'attr') . '">'
            . $image
            . '<div class="kp-section-inner ' . ($options['width'] === 'full' ? 'container-fluid' : 'container') . '">'
            . $content
            . '</div></section>' . "\n";
    }

    private function backgroundImage(string $key): string
    {
        $file = $key !== '' ? service('storage')->find($key) : null;

        if ($file === null || ! $file->isImage() || $file->disk !== 'public') {
            return '';
        }

        return '<img class="kp-section-bg" src="' . esc(service('storage')->url($file), 'attr') . '" alt=""'
            . ($file->width !== null ? ' width="' . (int) $file->width . '" height="' . (int) $file->height . '"' : '')
            . ' loading="lazy" decoding="async"><span class="kp-section-overlay" aria-hidden="true"></span>';
    }
}
