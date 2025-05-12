<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */

namespace Cases\PoolCases;

use Hyperf\WorkerPool\Pool\StackPool;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class StackTest extends TestCase
{
    public function testPushAndPop()
    {
        $stack = new StackPool(-1);

        $this->assertTrue($stack->empty());
        $this->assertNull($stack->shift());

        $node1 = $stack->unshift('1');
        $this->assertFalse($stack->empty());
        $this->assertEquals(1, $stack->len());

        $node2 = $stack->unshift('2');
        $this->assertEquals(2, $stack->len());

        $this->assertEquals('2', $stack->shift());
        $this->assertEquals(1, $stack->len());

        $this->assertEquals('1', $stack->shift());
        $this->assertEquals(0, $stack->len());
        $this->assertTrue($stack->empty());
    }

    public function testPeek()
    {
        $stack = new StackPool(-1);

        $this->assertNull($stack->peek());

        $stack->unshift('1');
        $node = $stack->peek();
        $this->assertNotNull($node);
        $this->assertEquals('1', $node->value());

        $this->assertEquals(1, $stack->len());

        $stack->unshift('2');
        $node = $stack->peek();
        $this->assertEquals('2', $node->value());
    }

    public function testMultipleOperations()
    {
        $stack = new StackPool(-1);

        $stack->unshift('1');
        $stack->unshift('2');
        $stack->unshift('3');

        $this->assertEquals('3', $stack->shift());

        $stack->unshift('4');
        $stack->unshift('5');

        $this->assertEquals('5', $stack->shift());
        $this->assertEquals('4', $stack->shift());
        $this->assertEquals('2', $stack->shift());
        $this->assertEquals('1', $stack->shift());

        $this->assertTrue($stack->empty());
    }

    public function testRemoveNode()
    {
        $stack = new StackPool(-1);

        $nodeA = $stack->unshift('1');
        $nodeB = $stack->unshift('2');
        $nodeC = $stack->unshift('3');

        $stack->remove($nodeB);
        $this->assertEquals(2, $stack->len());

        $this->assertEquals('3', $stack->shift());
        $this->assertEquals('1', $stack->shift());
        $this->assertTrue($stack->empty());
    }
}
