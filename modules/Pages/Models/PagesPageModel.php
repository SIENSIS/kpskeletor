<?php

declare(strict_types=1);

namespace Modules\Pages\Models;

use Modules\Core\Sections\PageModel;
use Modules\Core\Sections\PageModelContext;

/**
 * Base of the Pages module's page models (`pages.<name>`, D-47-02): texts in
 * `Pages.models.<name>.*` (neutral placeholders in every language), the thumbnail
 * `Assets/img/models/<name>.svg`. Links point at the home page until the editor changes them.
 */
abstract class PagesPageModel extends PageModel
{
    abstract protected function modelName(): string;

    public function key(): string
    {
        return 'pages.' . $this->modelName();
    }

    public function label(): string
    {
        return lang('Pages.models.' . $this->modelName() . '.label');
    }

    public function description(): string
    {
        return lang('Pages.models.' . $this->modelName() . '.description');
    }

    public function thumbnail(): ?string
    {
        helper('asset');

        return asset('pages::img/models/' . $this->modelName() . '.svg');
    }

    public function title(string $locale): string
    {
        return $this->text('title', $locale);
    }

    /**
     * A placeholder text of this model in a language.
     *
     * @param list<int|string> $args
     */
    protected function text(string $key, string $locale, array $args = []): string
    {
        $text = lang('Pages.models.' . $this->modelName() . '.' . $key, $args, $locale);

        return is_string($text) ? $text : '';
    }

    /**
     * Texts of every language from a builder of one language's parts.
     *
     * @param \Closure(string): array<string, mixed> $parts
     *
     * @return array<string, array<string, mixed>>
     */
    protected function texts(PageModelContext $context, \Closure $parts): array
    {
        return $context->texts($parts);
    }

    /**
     * A link to an internal path (the home page by default).
     *
     * @return array{type: string, value: string}
     */
    protected static function link(string $path = '/'): array
    {
        return ['type' => 'url', 'value' => $path];
    }

    /**
     * The common options of a section.
     *
     * @return array<string, string>
     */
    protected static function options(string $background = 'none', string $style = '', string $spacing = 'm', string $width = 'contained'): array
    {
        return ['style' => $style, 'background' => $background, 'spacing' => $spacing, 'width' => $width, 'background_image' => '', 'anchor' => ''];
    }
}
