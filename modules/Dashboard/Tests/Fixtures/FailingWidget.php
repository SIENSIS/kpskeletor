<?php

declare(strict_types=1);

namespace Modules\Dashboard\Tests\Fixtures;

use Modules\Core\Widgets\Widget;
use RuntimeException;

/**
 * Test widget whose render() throws: the dashboard must show an error card instead.
 */
final class FailingWidget extends Widget
{
    public function id(): string
    {
        return 'dashboard.failing';
    }

    public function title(): string
    {
        return 'Failing widget';
    }

    public function render(array $options): string
    {
        throw new RuntimeException('Broken on purpose (secret detail).');
    }
}
