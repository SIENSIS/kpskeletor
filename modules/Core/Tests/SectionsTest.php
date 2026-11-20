<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use Modules\Auth\Tests\AuthTestCase;
use Modules\Core\Cache\CoreCache;
use Modules\Core\Config\ModuleManifest;
use Modules\Core\Modules\ManifestValidator;
use Modules\Core\Registry\DeclarationRegistry;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\Fields\ImageField;
use Modules\Core\Sections\Fields\LinkField;
use Modules\Core\Sections\Fields\ListField;
use Modules\Core\Sections\Fields\TextField;
use Modules\Core\Sections\RenderContext;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionForm;
use Modules\Core\Sections\SectionLinks;
use Modules\Core\Sections\SectionOptions;
use Modules\Core\Sections\SectionRegistry;
use Modules\Core\Sections\SectionRenderer;
use Modules\Core\Sections\SectionType;
use Modules\Core\Sections\SectionValidationException;
use Modules\Pages\Config\Module as PagesModule;
use Modules\Pages\Sections\CardsSection;
use Modules\Pages\Sections\LinksSection;
use Modules\Pages\Sections\TextSection;

/**
 * The Core's section registry, field types and rendering (D-45-01; docs/sections.md): types of
 * enabled modules only, keys checked, every field validated on the server against forged posts,
 * translations with fallback, the common options and the `<section>` wrapper.
 *
 * @internal
 */
final class SectionsTest extends AuthTestCase
{
    use ContentLocalesTrait;
    use StorageFixturesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->useShippedContentLocales();
        $this->makeStorage();
        service('routes')->loadRoutes();
        self::declare(['Pages' => new PagesModule()]);
    }

    protected function tearDown(): void
    {
        $this->removeStorageRoot();
        parent::tearDown();
    }

    /**
     * @param array<string, ModuleManifest> $manifests
     */
    private static function declare(array $manifests): void
    {
        $registry = new DeclarationRegistry();
        $registry->store($registry->build($manifests));
        (new CoreCache())->invalidate('modules');
    }

    private ?FieldContext $context = null;

    private function context(): FieldContext
    {
        return $this->context ??= new FieldContext($this->createUser('ed@example.com', 'editor'), 'Pages', ['ca', 'es', 'en'], 'ca');
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $options
     */
    private function validate(SectionType $type, array $fields, array $options = [], ?FieldContext $context = null): SectionData
    {
        return (new SectionForm($type, $context ?? $this->context()))->validate($fields, $options);
    }

    /**
     * @param array<string, mixed> $fields
     * @param array<string, mixed> $options
     */
    private function assertRefused(SectionType $type, array $fields, string $path, array $options = [], ?FieldContext $context = null): void
    {
        try {
            $this->validate($type, $fields, $options, $context);
            $this->fail("Accepted, expected a refusal at {$path}.");
        } catch (SectionValidationException $e) {
            $this->assertSame($path, $e->path, $e->getMessage());
        }
    }

    public function testTheRegistryOffersTheTypesOfEnabledModulesOnly(): void
    {
        $registry = new SectionRegistry();
        $this->assertSame(['pages.hero', 'pages.links', 'pages.cards', 'pages.media_text', 'pages.cta', 'pages.text'], array_keys($registry->all()));
        $this->assertSame('Pages', $registry->module('pages.cards'));
        $this->assertSame('Modules\Pages\Views\sections\media_text', $registry->get('pages.media_text')?->view());

        db_connect()->table('modules')->where('name', 'Pages')->update(['enabled' => 0]);
        (new CoreCache())->invalidate('modules');
        $this->assertSame([], (new SectionRegistry())->all(), 'A disabled module offers no section type.');
        db_connect()->table('modules')->where('name', 'Pages')->update(['enabled' => 1]);
        (new CoreCache())->invalidate('modules');
    }

    public function testTypesWithAForeignOrRepeatedKeyAreSkipped(): void
    {
        $manifest               = new class () extends ModuleManifest {};
        $manifest->name         = 'Core';
        $manifest->sectionTypes = [ForeignKeySection::class, CoreSampleSection::class, RepeatedKeySection::class, 'Missing\Section'];
        self::declare(['Pages' => new PagesModule(), 'Core' => $manifest]);

        $this->assertEqualsCanonicalizing(
            ['core.sample', 'pages.hero', 'pages.links', 'pages.cards', 'pages.media_text', 'pages.cta', 'pages.text'],
            array_keys((new SectionRegistry())->all()),
            'Core may not declare pages.*, repeat a key or name a missing class.',
        );
    }

    public function testTheManifestKeyIsValidated(): void
    {
        $manifest               = new PagesModule();
        $manifest->sectionTypes = [\stdClass::class];
        $errors                 = (new ManifestValidator())->validate($manifest, ROOTPATH . 'modules/Pages');

        $this->assertNotSame([], $errors);
        $this->assertStringContainsString('sectionTypes', implode(' ', $errors));
    }

    public function testTextIsKeptAsTypedAndEscapedWhenShown(): void
    {
        $type    = new TextSection();
        $section = $this->validate($type, ['heading' => ['ca' => '<b>Hola</b> & adéu'], 'text' => ['ca' => '<p>Text</p><script>alert(1)</script><p onclick="x()">B</p>']]);

        $this->assertSame('<b>Hola</b> & adéu', $section->texts['ca']['heading']);
        $this->assertStringNotContainsString('<script', (string) $section->texts['ca']['text']);
        $this->assertStringNotContainsString('onclick', (string) $section->texts['ca']['text']);

        $html = (new SectionRenderer())->renderOne($section, new RenderContext('ca', 'ca'));
        $this->assertStringContainsString('&lt;b&gt;Hola&lt;/b&gt; &amp; adéu', $html);
        $this->assertStringNotContainsString('<script', $html);
    }

    public function testForgedPostsAreRefused(): void
    {
        $text  = new TextSection();
        $cards = new CardsSection();
        $card  = static fn (array $extra = []): array => $extra + ['title' => ['ca' => 'T'], 'image' => ['key' => ''], 'link' => ['type' => '']];

        $this->assertRefused($text, ['text' => ['ca' => 'x'], 'evil' => 'y'], 'evil');
        $this->assertRefused($text, ['text' => 'not per language'], 'text');
        $this->assertRefused($text, ['text' => ['ca' => ['nested']]], 'text');
        $this->assertRefused($text, ['text' => ['es' => 'Només en castellà']], 'text.ca');
        $this->assertRefused($text, ['heading' => ['ca' => str_repeat('a', 161)], 'text' => ['ca' => 'x']], 'heading.ca');
        $this->assertRefused($text, ['text' => ['ca' => 'x'], 'column' => 'huge'], 'column');
        $this->assertRefused($cards, ['cards' => array_fill(0, 13, $card())], 'cards');
        $this->assertRefused($cards, ['cards' => []], 'cards');
        $this->assertRefused($cards, ['cards' => [$card(['secret' => 'x'])]], 'cards.0.secret');
        $this->assertRefused($cards, ['cards' => 'x'], 'cards');
        $this->assertRefused($cards, ['cards' => [$card()], 'columns' => '9'], 'columns');
        $this->assertRefused($text, ['text' => ['ca' => 'x']], 'options.anchor', ['anchor' => 'Not An Anchor']);
        $this->assertRefused($text, ['text' => ['ca' => 'x']], 'options.background', ['background' => 'url(x)']);
        $this->assertRefused($text, ['text' => ['ca' => 'x']], 'options.css', ['css' => 'color:red']);
        $this->assertRefused($text, ['text' => ['ca' => 'x']], 'options.background_image', ['background' => 'image']);

        $links = new LinksSection();
        $item  = static fn (array $extra): array => $extra + ['label' => ['ca' => 'L'], 'image' => ['key' => ''], 'link' => ['type' => '']];
        $this->assertRefused($links, ['items' => [$item(['icon' => 'bi bi-star onerror'])]], 'items.0.icon');
        $this->assertRefused($links, ['items' => [$item(['icon' => 'bi-not-an-icon-at-all'])]], 'items.0.icon');
        $this->assertSame('bi-star', $this->validate($links, ['items' => [$item(['icon' => 'bi-star'])]])->values['items'][0]['icon']);
    }

    public function testLinksAreCheckedLikeMenuItems(): void
    {
        $cards = new CardsSection();
        $post  = static fn (string $type, string $value, string $label = 'Més'): array => ['cards' => [[
            'title' => ['ca' => 'T'], 'image' => ['key' => ''], 'link' => ['type' => $type, 'value' => [$type => $value], 'label' => ['ca' => $label]],
        ]]];

        foreach (['javascript:alert(1)', 'http://example.com/', '//evil.example/', 'https://user:pw@example.com/', 'data:text/html,x', "java\tscript:alert(1)", 'https://exa mple.com', '/\\evil'] as $url) {
            $this->assertRefused($cards, $post('url', $url), 'cards.0.link.value', [], null);
        }

        $this->assertRefused($cards, $post('route', 'no-such-route'), 'cards.0.link.value');
        $this->assertRefused($cards, $post('evil.type', '1'), 'cards.0.link.value');
        $this->assertRefused($cards, $post('url', 'https://example.com/', ''), 'cards.0.link.label.ca');

        $ok = $this->validate($cards, $post('url', 'https://example.com/a?b=1'));
        $this->assertSame(['type' => 'url', 'value' => 'https://example.com/a?b=1'], $ok->values['cards'][0]['link']);
        $this->assertSame('/contacte', SectionLinks::checkUrl('/contacte'));
        $this->assertNull(SectionLinks::checkUrl('http://example.com/'));

        $resolved = (new CardsSection())->resolve($ok, 'en', 'ca');
        $this->assertSame(['href' => 'https://example.com/a?b=1', 'label' => 'Més', 'external' => true], $resolved['cards'][0]['link'], 'The label falls back to the default language.');
    }

    public function testImagesMustBeFilesTheEditorMayUse(): void
    {
        $owner   = $this->createUser('owner@example.com', 'user');
        $private = service('storage')->storeContents(self::png(), 'secret.png', 'private', 'users/' . $owner->id . '/docs');
        $shared  = service('storage')->storeContents(self::png(), 'shared.png', 'public', 'modules/pages');
        $field   = ImageField::make('image')->label('Image');
        $context = $this->context();

        foreach (['../../etc/passwd', str_repeat('a', 32), $private->key, $shared->key . ',' . $shared->key] as $key) {
            try {
                $field->clean(['key' => $key, 'decorative' => '1'], $context, 'image');
                $this->fail("Image {$key} accepted.");
            } catch (SectionValidationException $e) {
                $this->assertSame('image', $e->path);
            }
        }

        // An image of the module's folder: allowed, with an alternative text unless decorative.
        try {
            $field->clean(['key' => $shared->key], $context, 'image');
            $this->fail('An image without alternative text was accepted.');
        } catch (SectionValidationException $e) {
            $this->assertSame('image.alt.ca', $e->path);
        }

        $clean = $field->clean(['key' => $shared->key, 'alt' => ['ca' => 'Una foto']], $context, 'image');
        $this->assertSame(['key' => $shared->key, 'decorative' => false], $clean['value']);
        $this->assertSame('Una foto', $clean['texts']['ca'] ?? null);

        // A key the section already had is kept even if the editor could not choose it now.
        $kept = $field->clean(['key' => $private->key, 'decorative' => '1'], new FieldContext($context->user, 'Pages', ['ca'], 'ca', [$private->key]), 'image');
        $this->assertSame($private->key, $kept['value']['key']);
        $this->assertNull($field->resolve($kept['value'], [], 'ca', 'ca'), 'Private files are never shown in a section.');
    }

    public function testTranslationsFallBackToTheDefaultLanguage(): void
    {
        $type    = new TextSection();
        $section = $this->validate($type, ['heading' => ['ca' => 'Títol', 'es' => 'Título', 'en' => ''], 'text' => ['ca' => '<p>Català</p>', 'en' => '<p>English</p>']]);

        $this->assertSame('Título', $type->resolve($section, 'es', 'ca')['heading']);
        $this->assertSame('Títol', $type->resolve($section, 'en', 'ca')['heading'], 'Empty in English: the default language.');
        $this->assertSame('<p>English</p>', $type->resolve($section, 'en', 'ca')['text']);
        $this->assertSame('<p>Català</p>', $type->resolve($section, 'es', 'ca')['text']);
    }

    public function testListsKeepTheirOrderAndAlignTranslations(): void
    {
        $list  = ListField::make('items')->items(0, 3)->fields([TextField::make('label')->translated(), TextField::make('code')]);
        $clean = $list->clean([
            'n2' => ['label' => ['ca' => 'Segon', 'en' => 'Second'], 'code' => 'b'],
            '0'  => ['label' => ['ca' => 'Primer'], 'code' => 'a'],
        ], $this->context(), 'items');

        $this->assertSame([['code' => 'b'], ['code' => 'a']], $clean['value']);
        $this->assertSame([['label' => 'Second'], ['label' => '']], $clean['texts']['en']);
        $this->assertSame([['label' => 'Second', 'code' => 'b'], ['label' => 'Primer', 'code' => 'a']], $list->resolve($clean['value'], $clean['texts'], 'en', 'ca'));
    }

    public function testTheRendererWrapsVisibleSectionsWithTheirOptions(): void
    {
        $text    = new TextSection();
        $visible = $this->validate($text, ['text' => ['ca' => '<p>Visible</p>']], ['background' => 'dark', 'spacing' => 'l', 'width' => 'full', 'anchor' => 'sobre-nosaltres']);
        $hidden  = new SectionData('pages.text', [], ['ca' => ['text' => '<p>Amagada</p>']], [], true);
        $unknown = new SectionData('gone.module', [], [], []);

        $html = (new SectionRenderer())->render([$visible, $hidden, $unknown], new RenderContext('ca', 'ca'));

        $this->assertSame(1, substr_count($html, '<section'));
        $this->assertStringContainsString('id="sobre-nosaltres"', $html);
        $this->assertStringContainsString('data-bs-theme="dark"', $html);
        $this->assertMatchesRegularExpression('/class="kp-section(&#x20;| )kp-section-text(&#x20;| )kp-bg-dark(&#x20;| )kp-space-l(&#x20;| )kp-width-full"/', $html);
        $this->assertStringContainsString('kp-section-inner container-fluid', $html);
        $this->assertStringNotContainsString('Amagada', $html);

        $this->assertSame(['style' => '', 'background' => 'none', 'background_image' => '', 'spacing' => 'm', 'width' => 'contained', 'anchor' => ''], SectionOptions::normalise(['background' => 'evil', 'anchor' => '"><x'], $text));
    }

    public function testAThemeOverridesASectionView(): void
    {
        $dir  = ROOTPATH . 'themes/default/Views/overrides/Pages/sections';
        $file = $dir . '/text.php';
        $this->assertFileDoesNotExist($file);
        mkdir($dir, 0775, true);
        file_put_contents($file, '<div class="theme-text-override"><?= $values[\'text\'] ?></div>');

        try {
            service('theme')->reset();
            $section = $this->validate(new TextSection(), ['text' => ['ca' => '<p>Des del tema</p>']]);
            $html    = (new SectionRenderer())->renderOne($section, new RenderContext('ca', 'ca'));

            $this->assertStringContainsString('<div class="theme-text-override"><p>Des del tema</p></div>', $html);
        } finally {
            unlink($file);
            rmdir($dir);
            @rmdir(dirname($dir));
            service('theme')->reset();
        }
    }

    public function testALinkFieldWithoutLabelNeedsNoText(): void
    {
        $field = LinkField::make('link')->withoutLabel();
        $clean = $field->clean(['type' => 'url', 'value' => ['url' => '/qui-som']], $this->context(), 'link');

        $this->assertSame(['type' => 'url', 'value' => '/qui-som'], $clean['value']);
        $this->assertSame(site_url('qui-som'), $field->resolve($clean['value'], [], 'ca', 'ca')['href'] ?? null);
    }
}

/**
 * A type whose key claims another module (`pages.*` declared by Core): skipped by the registry.
 */
final class ForeignKeySection extends SectionType
{
    public function key(): string
    {
        return 'pages.foreign';
    }

    public function label(): string
    {
        return 'Foreign';
    }

    protected function schema(): array
    {
        return [];
    }
}

/**
 * A valid type of the Core (`core.sample`).
 */
class CoreSampleSection extends SectionType
{
    public function key(): string
    {
        return 'core.sample';
    }

    public function label(): string
    {
        return 'Sample';
    }

    protected function schema(): array
    {
        return [];
    }
}

/**
 * Another class with the same key: skipped.
 */
final class RepeatedKeySection extends CoreSampleSection
{
}
