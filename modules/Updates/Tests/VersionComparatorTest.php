<?php

declare(strict_types=1);

namespace Modules\Updates\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Updates\Services\VersionComparator;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Semantic-version precedence of release tags.
 *
 * @internal
 */
final class VersionComparatorTest extends CIUnitTestCase
{
    /**
     * @return iterable<array{0: string, 1: string, 2: int}>
     */
    public static function cases(): iterable
    {
        yield ['1.0.0', '1.0.0', 0];
        yield ['v1.0.0', '1.0.0', 0];
        yield ['1.0.1', '1.0.0', 1];
        yield ['1.10.0', '1.9.9', 1];
        yield ['2.0.0', '10.0.0', -1];
        yield ['1.0.0-alpha', '1.0.0', -1];
        yield ['1.0.0-alpha', '1.0.0-alpha.1', -1];
        yield ['1.0.0-alpha.1', '1.0.0-alpha.beta', -1];
        yield ['1.0.0-alpha.beta', '1.0.0-beta', -1];
        yield ['1.0.0-beta.2', '1.0.0-beta.11', -1];
        yield ['1.0.0-beta.11', '1.0.0-rc.1', -1];
        yield ['1.0.0-rc.1', '1.0.0', -1];
        yield ['1.0.0+build.5', '1.0.0+build.9', 0];
    }

    #[DataProvider('cases')]
    public function testPrecedence(string $a, string $b, int $expected): void
    {
        $this->assertSame($expected, VersionComparator::compare($a, $b));
        $this->assertSame(-$expected, VersionComparator::compare($b, $a));
    }

    public function testNormalizeAndNewer(): void
    {
        $this->assertSame('1.2.3', VersionComparator::normalize('v1.2.3'));
        $this->assertSame('1.2.3-rc.1', VersionComparator::normalize(' 1.2.3-rc.1 '));
        $this->assertNull(VersionComparator::normalize('1.2'));
        $this->assertNull(VersionComparator::normalize('01.2.3'));
        $this->assertNull(VersionComparator::normalize('latest'));
        $this->assertNull(VersionComparator::normalize('1.2.3-'));
        $this->assertTrue(VersionComparator::isNewer('0.2.0', '0.1.0'));
        $this->assertFalse(VersionComparator::isNewer('0.1.0', '0.1.0'));
        $this->assertTrue(VersionComparator::isPrerelease('v2.0.0-beta.1'));
        $this->assertFalse(VersionComparator::isPrerelease('2.0.0'));

        $this->expectException(\InvalidArgumentException::class);
        VersionComparator::compare('main', '1.0.0');
    }
}
