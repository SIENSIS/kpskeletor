<?php

declare(strict_types=1);

namespace Modules\Pages\Models;

use Modules\Core\Sections\PageModelContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Security\HtmlSanitizer;

/**
 * "Centre educatiu" (`pages.school`, D-47-01): a carousel, a band of icon links, illustrated
 * quick links, the latest news (when the News module is enabled), a place for an embedded tour,
 * a strip of logos and an image with text. Neutral placeholder texts and images only.
 */
final class SchoolModel extends PagesPageModel
{
    protected function modelName(): string
    {
        return 'school';
    }

    public function icon(): string
    {
        return 'bi bi-mortarboard';
    }

    public function sections(PageModelContext $context): array
    {
        $sections = [];

        // Carousel: two slides.
        $slides = [];

        foreach ([[[30, 70, 140], [20, 140, 160]], [[110, 60, 150], [220, 110, 120]]] as $i => [$from, $to]) {
            $slides[] = ['image' => ['key' => $context->image('model-school-slide-' . ($i + 1) . '.png', 1600, 640, $from, $to), 'decorative' => true], 'link' => self::link()];
        }

        $sections[] = new SectionData('pages.carousel', ['slides' => $slides, 'overlay' => 'dark', 'height' => 'm', 'button_style' => 'light', 'rotate' => false, 'interval' => 8], $this->texts($context, fn (string $l): array => [
            'label'  => $this->text('carousel.label', $l),
            'slides' => [
                ['image' => '', 'title' => $this->text('carousel.title1', $l), 'text' => $this->text('carousel.text1', $l), 'link' => $this->text('carousel.button', $l)],
                ['image' => '', 'title' => $this->text('carousel.title2', $l), 'text' => $this->text('carousel.text2', $l), 'link' => $this->text('carousel.button', $l)],
            ],
        ]), self::options('dark', '', 's', 'full'));

        // A band of icon links.
        $icons = ['bi-info-circle', 'bi-calendar-event', 'bi-journal-text', 'bi-people', 'bi-pc-display', 'bi-envelope'];
        $sections[] = new SectionData('pages.links', [
            'items'   => array_map(static fn (string $icon): array => ['icon' => $icon, 'image' => ['key' => '', 'decorative' => true], 'link' => self::link()], $icons),
            'columns' => '6',
        ], $this->texts($context, fn (string $l): array => [
            'heading' => '',
            'intro'   => '',
            'items'   => array_map(fn (int $i): array => ['label' => $this->text('band.item' . $i, $l), 'image' => ''], range(1, count($icons))),
        ]), self::options('primary', 'band', 's'));

        // Illustrated quick links.
        $colours = [[[240, 200, 120], [232, 120, 80]], [[150, 210, 190], [40, 140, 140]], [[180, 190, 240], [90, 90, 200]], [[240, 180, 210], [190, 70, 120]]];
        $sections[] = new SectionData('pages.links', [
            'items'   => array_map(static fn (array $c, int $i): array => ['icon' => '', 'image' => ['key' => $context->image('model-school-tile-' . ($i + 1) . '.png', 600, 450, $c[0], $c[1]), 'decorative' => true], 'link' => self::link()], $colours, array_keys($colours)),
            'columns' => '4',
        ], $this->texts($context, fn (string $l): array => [
            'heading' => $this->text('tiles.heading', $l),
            'intro'   => '',
            'items'   => array_map(fn (int $i): array => ['label' => $this->text('tiles.item' . $i, $l), 'image' => ''], range(1, 4)),
        ]), self::options('none', 'tiles'));

        // The latest news (skipped when the News module is not enabled).
        $sections[] = new SectionData('news.list', ['mode' => 'latest', 'limit' => 4, 'columns' => '4', 'show_date' => true, 'show_summary' => true, 'show_image' => true, 'search' => false], $this->texts($context, fn (string $l): array => [
            'heading' => $this->text('news.heading', $l),
            'intro'   => '',
        ]), self::options('light', 'cards'));

        // A place for an embedded tour or video: an embed needs a host allowed in Settings › Security.
        $sections[] = new SectionData('pages.text', ['column' => 'narrow'], $this->texts($context, fn (string $l): array => [
            'heading' => $this->text('embed.heading', $l),
            'text'    => HtmlSanitizer::clean('<p>' . esc($this->text('embed.text', $l)) . '</p>'),
        ]), self::options());

        // A strip of logos.
        $logos = [];

        foreach ([[[13, 110, 253], [120, 170, 250]], [[25, 135, 84], [130, 200, 160]], [[214, 51, 132], [240, 150, 190]], [[253, 126, 20], [250, 190, 120]]] as $i => [$from, $to]) {
            $logos[] = ['image' => ['key' => $context->image('model-logo-' . ($i + 1) . '.png', 360, 140, $from, $to, 'logo'), 'decorative' => false], 'link' => ['type' => '', 'value' => '']];
        }

        $sections[] = new SectionData('pages.logos', ['logos' => $logos, 'grayscale' => true], $this->texts($context, fn (string $l): array => [
            'heading' => $this->text('logos.heading', $l),
            'intro'   => '',
            'logos'   => array_map(fn (int $i): array => ['image' => $this->text('logos.alt', $l, [$i]), 'link' => ''], range(1, 4)),
        ]), self::options('light'));

        // An image with text.
        $sections[] = new SectionData('pages.media_text', [
            'image'        => ['key' => $context->image('model-school-about.png', 800, 600, [200, 220, 250], [60, 100, 200]), 'decorative' => false],
            'image_side'   => 'start',
            'link'         => self::link(),
            'button_style' => 'primary',
        ], $this->texts($context, fn (string $l): array => [
            'image' => $this->text('about.alt', $l),
            'title' => $this->text('about.title', $l),
            'text'  => HtmlSanitizer::clean('<p>' . esc($this->text('about.text', $l)) . '</p>'),
            'link'  => $this->text('about.button', $l),
        ]), self::options());

        return $sections;
    }
}
