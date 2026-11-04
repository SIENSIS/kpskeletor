<?php

declare(strict_types=1);

namespace Modules\Core\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Core\Modules\SemVer;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * @internal
 */
final class SemVerTest extends CIUnitTestCase
{
    /**
     * @return iterable<array{0: string, 1: string, 2: bool}>
     */
    public static function cases(): iterable
    {
        yield ['1.5.0', '^1.2', true];
        yield ['2.0.0', '^1.2', false];
        yield ['0.1.3', '^0.1', true];
        yield ['0.2.0', '^0.1', false];
        yield ['1.2.9', '~1.2.3', true];
        yield ['1.3.0', '~1.2.3', false];
        yield ['1.9.0', '~1.2', true];
        yield ['1.2.4', '1.2', true];
        yield ['1.3.0', '1.2', false];
        yield ['1.2.3', '1.2.3', true];
        yield ['2.5.1', '>=1.0 <3', true];
        yield ['3.0.0', '>=1.0 <3', false];
        yield ['1.0.0', '^2 || ^1', true];
        yield ['1.1.0', '!=1.1.0', false];
        yield ['9.9.9', '*', true];
    }

    #[DataProvider('cases')]
    public function testSatisfies(string $version, string $constraint, bool $expected): void
    {
        $this->assertSame($expected, SemVer::satisfies($version, $constraint));
    }

    public function testValidation(): void
    {
        $this->assertTrue(SemVer::isVersion('1.0.0'));
        $this->assertTrue(SemVer::isVersion('1.0.0-beta.2'));
        $this->assertFalse(SemVer::isVersion('1.0'));
        $this->assertTrue(SemVer::isConstraint('^1.0 || ~2.1.3'));
        $this->assertFalse(SemVer::isConstraint('latest'));
        $this->assertFalse(SemVer::isConstraint(''));
    }
}
