<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Config\Services;
use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Sections\FieldContext;
use Modules\Core\Sections\Fields\BooleanField;
use Modules\Core\Sections\Fields\ChoicesField;
use Modules\Core\Sections\Fields\SelectField;
use Modules\Core\Sections\SectionData;
use Modules\Core\Sections\SectionQuery;
use Modules\Core\Sections\SectionValidationException;
use Modules\Pages\Embeds\EmbedHosts;
use Modules\Pages\Sections\EmbedSection;

/**
 * Pieces the section types of session 46 rely on: several choices from a provider, booleans
 * ticked by default, per-section query parameters, the embed host rules and the
 * `check()` / `warnings()` / `cspSources()` hooks of a type.
 *
 * @internal
 */
final class SectionFieldsTest extends CIUnitTestCase
{
    private function context(): FieldContext
    {
        return new FieldContext(null, 'Pages', ['ca', 'es', 'en'], 'ca');
    }

    public function testChoicesFromAProvider(): void
    {
        $calls = 0;
        $field = ChoicesField::make('categories')->max(2)->optionsFrom(static function () use (&$calls): array {
            $calls++;

            return ['3' => 'Esports', '1' => 'Cultura', '7' => 'Sortides'];
        });

        $this->assertSame(['value' => ['3', '7']], $field->clean(['7', '3', '7'], $this->context(), 'categories'), 'Stored in the order of the options, without repeats.');
        $this->assertSame(['value' => []], $field->clean('', $this->context(), 'categories'));
        $this->assertSame(['7'], $field->resolve(['7', '99', ['x']], [], 'ca', 'ca'), 'A choice no longer offered is left out.');
        $this->assertSame('Esports, Sortides', $field->summary(['3', '7']));
        $this->assertSame(['3' => 'Esports', '1' => 'Cultura', '7' => 'Sortides'], $field->getOptions(), 'Names from a provider are not language keys.');
        $this->assertSame(1, $calls, 'The provider is read once.');

        foreach ([['99'], 'abc', [['3']], ['3', '1', '7']] as $forged) {
            try {
                $field->clean($forged, $this->context(), 'categories');
                $this->fail('Refused: ' . json_encode($forged));
            } catch (SectionValidationException $e) {
                $this->assertSame('categories', $e->path);
            }
        }

        $required = ChoicesField::make('c')->required()->optionsFrom(static fn (): array => ['1' => 'A']);
        $this->expectException(SectionValidationException::class);
        $required->clean([], $this->context(), 'c');
    }

    public function testASelectWithAProvider(): void
    {
        $field = SelectField::make('view')->optionsFrom(static fn (): array => ['a' => 'A', 'b' => 'B'])->default('b');

        $this->assertSame('b', $field->emptyValue());
        $this->assertSame(['value' => 'a'], $field->clean('a', $this->context(), 'view'));
        $this->assertSame('b', $field->resolve('zzz', [], 'ca', 'ca'));
    }

    public function testBooleansTickedByDefault(): void
    {
        $field = BooleanField::make('show')->default(true);

        $this->assertTrue($field->formValue(null, []), 'A new section starts ticked.');
        $this->assertTrue($field->resolve(null, [], 'ca', 'ca'), 'A section saved before the field existed.');
        $this->assertSame(['value' => false], $field->clean('0', $this->context(), 'show'), 'Unticked is kept.');
        $this->assertFalse($field->resolve(false, [], 'ca', 'ca'));
        $this->assertFalse(BooleanField::make('x')->resolve(null, [], 'ca', 'ca'), 'Off by default otherwise.');
    }

    public function testQueryParametersAreNamedAfterTheSection(): void
    {
        $request = Services::incomingrequest(null, false);
        $request->setGlobal('get', ['page-5' => '3', 'page-6' => '2', 'q-5' => ' hola ', 'other' => 'x']);
        Services::injectMock('request', $request);

        $query = new SectionQuery(new SectionData('news.list', id: 5));
        $this->assertSame('page-5', $query->name('page'));
        $this->assertSame(3, $query->page());
        $this->assertSame('hola', $query->get('q'));
        $this->assertSame('kp-s5', $query->anchor());

        $url = $query->url(['page' => 4]);
        $this->assertStringContainsString('page-5=4', $url);
        $this->assertStringContainsString('page-6=2', $url, 'The other section\'s page is kept.');
        $this->assertStringContainsString('other=x', $url);
        $this->assertStringEndsWith('#kp-s5', $url);
        $this->assertStringNotContainsString('page-5', $query->url(['page' => 1]), 'Page 1 has no parameter.');

        $request->setGlobal('get', ['page-5' => '-2']);
        $this->assertSame(1, $query->page(), 'Anything but a positive number is page 1.');
    }

    public function testEmbedHosts(): void
    {
        $hosts = new EmbedHosts(['www.youtube-nocookie.com', '*.tour.example.org']);

        $this->assertSame('www.youtube-nocookie.com', EmbedHosts::host('https://WWW.youtube-nocookie.com/embed/x'));
        $this->assertSame('https://www.youtube-nocookie.com', $hosts->origin('https://www.youtube-nocookie.com/embed/x?y=1'));
        $this->assertSame('https://a.tour.example.org:8443', $hosts->origin('https://a.tour.example.org:8443/v'));
        $this->assertNull($hosts->origin('https://tour.example.org/'), 'A *. entry covers subdomains only.');
        $this->assertNull($hosts->origin('https://evil-tour.example.org.attacker.net/'));
        $this->assertNull($hosts->origin('https://xtour.example.org/'));

        foreach (['http://www.youtube-nocookie.com/', 'https://u:p@www.youtube-nocookie.com/', 'javascript:alert(1)', '//www.youtube-nocookie.com/', 'https://www.youtube-nocookie.com/a b', 'data:text/html,x', ''] as $bad) {
            $this->assertNull($hosts->origin($bad), $bad);
        }

        $this->assertNull((new EmbedHosts([]))->origin('https://www.youtube-nocookie.com/x'), 'Empty list: nothing.');
    }

    public function testTheEmbedTypeHooks(): void
    {
        $type    = new EmbedSection(new EmbedHosts(['www.youtube-nocookie.com']));
        $allowed = new SectionData('pages.embed', ['url' => 'https://www.youtube-nocookie.com/embed/x']);
        $removed = new SectionData('pages.embed', ['url' => 'https://tour.example.org/360']);

        $type->check($allowed, $this->context());
        $this->assertSame(['frame-src' => ['https://www.youtube-nocookie.com']], $type->cspSources($allowed));
        $this->assertSame([], $type->warnings($allowed));

        $this->assertSame([], $type->cspSources($removed));
        $this->assertCount(1, $type->warnings($removed));
        $this->assertStringContainsString('tour.example.org', $type->warnings($removed)[0]['message']);
        $this->assertArrayNotHasKey('link', $type->warnings($removed)[0], 'No link for a guest.');

        try {
            $type->check($removed, $this->context());
            $this->fail('A host outside the list is refused.');
        } catch (SectionValidationException $e) {
            $this->assertSame('url', $e->path);
            $this->assertStringContainsString('tour.example.org', $e->getMessage());
            $this->assertNull($e->link);
        }
    }
}
