<?php

declare(strict_types=1);

namespace Modules\Pages\Tests;

use Modules\Core\Storage\Picker\PickerField;
use Modules\Core\Tests\StorageFixturesTrait;
use Modules\Core\Tests\TestUploadedFile;
use Modules\Pages\Storage\FeaturedImages;

/**
 * The featured image of a page is a file picker (upload or choose in `modules/pages`); a forged
 * key is refused; the editor has "Insert image".
 *
 * @internal
 */
final class PagesPickerTest extends PagesTestCase
{
    use StorageFixturesTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->makeStorage();
    }

    protected function tearDown(): void
    {
        $this->removeStorageRoot();
        parent::tearDown();
    }

    public function testTheFeaturedImageComesFromThePicker(): void
    {
        $this->editor();
        $user  = auth()->user();
        $this->assertNotNull($user);
        $image = PickerField::backend()->upload($user, FeaturedImages::pickerField('featured_image')->scope($user), TestUploadedFile::fromContents(self::png(), 'hero.png'));
        $other = service('storage')->storeContents(self::png(), 'other.png', 'private', 'shared/other');

        $page = $this->body('GET', 'backend/pages/add');
        $this->assertStringContainsString('data-component="core/forms/file-picker"', $page);
        $this->assertStringContainsString('"images":{"name":"image"', $page);

        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Forjada'], ['featured_image' => $other->key]));
        $this->assertSame(0, db_connect()->table('page_translations')->where('slug', 'forjada')->countAllResults(), 'A forged key is refused.');

        $this->send('POST', 'backend/pages/add', $this->form(['title' => 'Amb imatge'], ['featured_image' => $image->key]))->assertRedirect();
        $row = db_connect()->table('page_translations')->where('slug', 'amb-imatge')->get()->getRowArray();
        $this->assertIsArray($row);
        $this->assertSame($image->key, $this->row((int) $row['page_id'])['featured_image']);
    }
}
