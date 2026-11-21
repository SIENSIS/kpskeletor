<?php

declare(strict_types=1);

namespace Modules\Pages\Sections;

use CodeIgniter\Shield\Entities\User;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\Fields\BooleanField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\Fields\TextareaField;
use Modules\Core\Sections\Fields\TextField;
use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionValidationException;
use Modules\Pages\Embeds\EmbedHosts;

/**
 * Embedded content `pages.embed` (D-46-01; docs/security.md, "Embedded content"): a page of
 * another site (a video, a virtual tour, a map) in an iframe.
 *
 * - Only `https://` addresses whose host is in Settings › Security › allowed embed hosts
 *   (`Pages.embedHosts`, empty by default). Another host is refused with a message that names it
 *   and says an administrator must add it (with a link to the setting for `settings.security`).
 * - The page's CSP gets `frame-src` for that origin only, and only on pages that show it.
 * - The iframe has a required title, `loading="lazy"`, a minimal `sandbox`
 *   (`allow-scripts allow-same-origin allow-popups allow-presentation`, never forms, top
 *   navigation or downloads) and `referrerpolicy="strict-origin-when-cross-origin"`.
 * - **Click to load** (on by default): nothing is requested from the other site until the visitor
 *   clicks (`pages/embed` builds the iframe then; without JavaScript, a link opens it).
 * - A section whose host was later removed from the list shows the warning in the editor and the
 *   preview and renders nothing on the public page (no broken iframe).
 */
final class EmbedSection extends PagesSectionType
{
    public const RATIOS = ['16x9', '4x3', '1x1', '21x9'];

    /** What the iframe may do (no forms, no top navigation, no downloads). */
    public const SANDBOX = 'allow-scripts allow-same-origin allow-popups allow-presentation';

    public function __construct(private readonly ?EmbedHosts $hosts = null)
    {
    }

    protected function typeName(): string
    {
        return 'embed';
    }

    public function icon(): string
    {
        return 'bi bi-window';
    }

    public function backgrounds(): array
    {
        return ['none', 'light', 'dark', 'primary'];
    }

    protected function schema(): array
    {
        return [
            TextField::make('heading')->label('Pages.sectionTypes.fields.heading')->translated()->maxLength(120),
            TextareaField::make('intro')->label('Pages.sectionTypes.fields.intro')->translated()->maxLength(400),
            TextField::make('url')->label('Pages.embed.fields.url')->required()->maxLength(EmbedHosts::MAX_URL)->help('Pages.embed.help.url'),
            TextField::make('title')->label('Pages.embed.fields.title')->translated()->required()->maxLength(150)->help('Pages.embed.help.title'),
            SelectField::make('ratio')->label('Pages.embed.fields.ratio')->options([
                '16x9' => 'Pages.embed.ratios.16x9',
                '4x3'  => 'Pages.embed.ratios.4x3',
                '1x1'  => 'Pages.embed.ratios.1x1',
                '21x9' => 'Pages.embed.ratios.21x9',
            ]),
            BooleanField::make('click_to_load')->label('Pages.embed.fields.clickToLoad')->default(true)->help('Pages.embed.help.clickToLoad'),
            BooleanField::make('fullscreen')->label('Pages.embed.fields.fullscreen')->default(true),
        ];
    }

    private function embedHosts(): EmbedHosts
    {
        return $this->hosts ?? new EmbedHosts();
    }

    /**
     * The address must be `https://` on an allowed host; another host is named in the message.
     */
    public function check(SectionData $section, FieldContext $context): void
    {
        $url  = (string) ($section->values['url'] ?? '');
        $host = EmbedHosts::host($url);

        if ($host === null) {
            throw SectionValidationException::at('url', lang('Pages.embed.errors.url'));
        }

        if (! $this->embedHosts()->allows($host)) {
            throw SectionValidationException::at('url', lang('Pages.embed.errors.host', [$host]), EmbedHosts::settingLink($context->user));
        }
    }

    public function warnings(SectionData $section, ?User $user = null): array
    {
        $url  = (string) ($section->values['url'] ?? '');
        $host = EmbedHosts::host($url);

        if ($host !== null && $this->embedHosts()->allows($host)) {
            return [];
        }

        $warning = ['message' => lang('Pages.embed.errors.removed', [$host ?? $url])];
        $link    = EmbedHosts::settingLink($user);

        return [$link === null ? $warning : $warning + ['link' => $link]];
    }

    public function cspSources(SectionData $section): array
    {
        $origin = $this->embedHosts()->origin((string) ($section->values['url'] ?? ''));

        return $origin === null ? [] : ['frame-src' => [$origin]];
    }

    public function render(SectionData $section, RenderContext $context): string
    {
        $url = trim((string) ($section->values['url'] ?? ''));

        if ($this->embedHosts()->origin($url) === null) {
            return ''; // not allowed (any more): nothing, not a broken iframe
        }

        return parent::render($section, $context);
    }

    public function summary(SectionData $section, string $locale): string
    {
        $host = EmbedHosts::host((string) ($section->values['url'] ?? ''));

        return trim(parent::summary($section, $locale) . ($host !== null ? ' · ' . $host : ''), ' ·');
    }
}
