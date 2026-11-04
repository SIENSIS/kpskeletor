<?php

declare(strict_types=1);

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Smoke test: the application boots and the home page answers.
 *
 * @internal
 */
final class HomeTest extends CIUnitTestCase
{
    use FeatureTestTrait;

    public function testHomePageAnswers200(): void
    {
        $result = $this->get('/');

        $result->assertStatus(200);
    }
}
