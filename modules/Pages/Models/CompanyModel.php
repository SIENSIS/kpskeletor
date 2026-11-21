<?php

declare(strict_types=1);

namespace Modules\Pages\Models;

use Modules\Core\Sections\PageModelContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Security\HtmlSanitizer;

/**
 * "Empresa" (`pages.company`, D-47-01): a hero banner with a wavy edge and a cut-out image,
 * icon links, cards, testimonials on a dark band, an image with text and a call to action.
 * Neutral placeholder texts and images only.
 */
final class CompanyModel extends PagesPageModel
{
    protected function modelName(): string
    {
        return 'company';
    }

    public function icon(): string
    {
        return 'bi bi-building';
    }

    public function sections(PageModelContext $context): array
    {
        $sections = [];

        $sections[] = new SectionData('pages.hero', [
            'primary'         => self::link(),
            'primary_style'   => 'light',
            'secondary'       => self::link(),
            'secondary_style' => 'outline-light',
            'background'      => ['key' => $context->image('model-company-hero.png', 1600, 700, [40, 30, 110], [120, 60, 200]), 'decorative' => true],
            'overlay'         => 'primary',
            'side_image'      => ['key' => $context->image('model-company-figure.png', 520, 600, [70, 130, 220], [70, 130, 220], 'figure'), 'decorative' => true],
            'height'          => 'l',
            'align'           => 'start',
            'edge'            => 'wave',
        ], $this->texts($context, fn (string $l): array => [
            'title'      => $this->text('hero.title', $l),
            'text'       => $this->text('hero.text', $l),
            'primary'    => $this->text('hero.primary', $l),
            'secondary'  => $this->text('hero.secondary', $l),
            'background' => '',
            'side_image' => '',
        ]), self::options('dark', '', 'm'));

        $icons = ['bi-lightning-charge', 'bi-shield-check', 'bi-graph-up-arrow', 'bi-headset'];
        $sections[] = new SectionData('pages.links', [
            'items'   => array_map(static fn (string $icon): array => ['icon' => $icon, 'image' => ['key' => '', 'decorative' => true], 'link' => self::link()], $icons),
            'columns' => '4',
        ], $this->texts($context, fn (string $l): array => [
            'heading' => $this->text('features.heading', $l),
            'intro'   => $this->text('features.intro', $l),
            'items'   => array_map(fn (int $i): array => ['label' => $this->text('features.item' . $i, $l), 'image' => ''], range(1, 4)),
        ]), self::options('none', 'icons'));

        $cards = [];

        foreach ([[[120, 170, 230], [40, 90, 180]], [[150, 220, 170], [30, 130, 90]], [[245, 200, 140], [210, 110, 40]]] as $i => [$from, $to]) {
            $cards[] = ['image' => ['key' => $context->image('model-company-card-' . ($i + 1) . '.png', 800, 500, $from, $to), 'decorative' => true], 'link' => self::link()];
        }

        $sections[] = new SectionData('pages.cards', ['cards' => $cards, 'columns' => '3', 'button_style' => 'primary'], $this->texts($context, fn (string $l): array => [
            'heading' => $this->text('cards.heading', $l),
            'intro'   => $this->text('cards.intro', $l),
            'cards'   => array_map(fn (int $i): array => ['image' => '', 'title' => $this->text('cards.title' . $i, $l), 'text' => $this->text('cards.text' . $i, $l), 'link' => $this->text('cards.button', $l)], range(1, 3)),
        ]), self::options('light', '', 'l'));

        $sections[] = new SectionData('pages.testimonials', [
            'items'   => array_map(static fn (string $name): array => ['photo' => ['key' => '', 'decorative' => true], 'name' => $name], ['Laura M.', 'Marc R.', 'Sara T.']),
            'columns' => '3',
        ], $this->texts($context, fn (string $l): array => [
            'heading' => $this->text('testimonials.heading', $l),
            'intro'   => '',
            'items'   => array_map(fn (int $i): array => ['photo' => '', 'quote' => $this->text('testimonials.quote' . $i, $l), 'role' => $this->text('testimonials.role' . $i, $l)], range(1, 3)),
        ]), self::options('dark', '', 'l'));

        $sections[] = new SectionData('pages.media_text', [
            'image'        => ['key' => $context->image('model-company-story.png', 800, 600, [250, 220, 190], [200, 120, 60]), 'decorative' => false],
            'image_side'   => 'end',
            'link'         => self::link(),
            'button_style' => 'primary',
        ], $this->texts($context, fn (string $l): array => [
            'image' => $this->text('story.alt', $l),
            'title' => $this->text('story.title', $l),
            'text'  => HtmlSanitizer::clean('<p>' . esc($this->text('story.text', $l)) . '</p>'),
            'link'  => $this->text('story.button', $l),
        ]), self::options());

        $sections[] = new SectionData('pages.cta', ['link' => self::link(), 'button_style' => 'light'], $this->texts($context, fn (string $l): array => [
            'title' => $this->text('cta.title', $l),
            'text'  => $this->text('cta.text', $l),
            'link'  => $this->text('cta.button', $l),
        ]), self::options('primary', '', 'l'));

        return $sections;
    }
}
