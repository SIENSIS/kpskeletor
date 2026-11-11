<?php

declare(strict_types=1);

namespace Modules\Menus\Tests;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Menus\Services\TreeMoves;

/**
 * Moves of the tree editors and the cycle checks.
 *
 * @internal
 */
final class TreeMovesTest extends CIUnitTestCase
{
    /** @var array<string, array{parent: ?string, zone: string, order: int}> */
    private array $nodes = [
        'a'  => ['parent' => null, 'zone' => 'sidebar', 'order' => 10],
        'b'  => ['parent' => null, 'zone' => 'sidebar', 'order' => 20],
        'b1' => ['parent' => 'b', 'zone' => 'sidebar', 'order' => 10],
        'c'  => ['parent' => null, 'zone' => 'sidebar', 'order' => 30],
        's'  => ['parent' => null, 'zone' => 'settings', 'order' => 5],
    ];

    public function testUpAndDownSwapSiblingsOfTheSameZone(): void
    {
        $nodes = TreeMoves::move($this->nodes, 'c', 'up');
        $this->assertSame(['a', 'c', 'b'], TreeMoves::siblings($nodes, null, 'sidebar'));

        $nodes = TreeMoves::move($nodes, 'a', 'up');
        $this->assertSame(['a', 'c', 'b'], TreeMoves::siblings($nodes, null, 'sidebar'), 'The first one stays.');

        $nodes = TreeMoves::move($this->nodes, 'a', 'down');
        $this->assertSame(['b', 'a', 'c'], TreeMoves::siblings($nodes, null, 'sidebar'));
        $this->assertSame(['s'], TreeMoves::siblings($nodes, null, 'settings'), 'Other zones are untouched.');
    }

    public function testIndentAndOutdent(): void
    {
        $nodes = TreeMoves::move($this->nodes, 'c', 'indent');
        $this->assertSame('b', $nodes['c']['parent']);
        $this->assertSame(['b1', 'c'], TreeMoves::siblings($nodes, 'b', 'sidebar'), 'Last child of the previous sibling.');

        $nodes = TreeMoves::move($nodes, 'b1', 'outdent');
        $this->assertNull($nodes['b1']['parent']);
        $this->assertSame(['a', 'b', 'b1'], TreeMoves::siblings($nodes, null, 'sidebar'), 'Right after its old parent.');

        $this->assertSame($this->nodes, TreeMoves::move($this->nodes, 'a', 'indent'), 'No previous sibling.');
        $this->assertSame($this->nodes, TreeMoves::move($this->nodes, 'a', 'outdent'), 'Already at the root.');
        $this->assertSame($this->nodes, TreeMoves::move($this->nodes, 'zz', 'up'), 'Unknown key.');
        $this->assertSame($this->nodes, TreeMoves::move($this->nodes, 'a', 'sideways'), 'Unknown move.');
    }

    public function testCycles(): void
    {
        $parents = ['a' => null, 'b' => 'a', 'c' => 'b'];
        $this->assertTrue(TreeMoves::isDescendant($parents, 'a', 'c'));
        $this->assertTrue(TreeMoves::isDescendant($parents, 'a', 'a'));
        $this->assertFalse(TreeMoves::isDescendant($parents, 'c', 'a'));
        $this->assertFalse(TreeMoves::hasCycle($parents));
        $this->assertTrue(TreeMoves::hasCycle(['a' => 'c', 'b' => 'a', 'c' => 'b']));
        $this->assertTrue(TreeMoves::hasCycle(['a' => 'a']));
        $this->assertFalse(TreeMoves::isDescendant(['a' => 'b', 'b' => 'a'], 'x', 'a'), 'A stored loop does not hang.');
    }
}
