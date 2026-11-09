<?php

declare(strict_types=1);

namespace Modules\Dashboard\Tests\Fixtures;

use Modules\Core\Widgets\AsyncWidget;
use RuntimeException;

/**
 * Test async widget: counts how often data() runs (cache per user) and throws on demand.
 */
final class CountingWidget extends AsyncWidget
{
    public static int $calls = 0;

    public static bool $fail = false;

    public function id(): string
    {
        return 'dashboard.counting';
    }

    public function title(): string
    {
        return 'Counting widget';
    }

    public function permission(): string
    {
        return 'users-users.list';
    }

    public function settings(): array
    {
        return ['label' => ['type' => 'string', 'default' => 'calls', 'label' => 'Dashboard.widget']];
    }

    public function data(array $options): array
    {
        if (self::$fail) {
            throw new RuntimeException('Data source down (secret detail).');
        }

        return ['calls' => ++self::$calls, 'user' => auth()->id()];
    }

    public function renderData(array $data, array $options): string
    {
        return '<p class="counting">' . esc((string) $options['label']) . ': ' . (int) $data['calls'] . '</p>';
    }
}
