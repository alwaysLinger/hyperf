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

use Hyperf\WorkerPool\Exception\RuntimeException;
use Hyperf\WorkerPool\Pool\QueuePool;
use Hyperf\WorkerPool\Pool\StackPool;
use Hyperf\WorkerPool\Worker;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * @internal
 * @coversNothing
 */
#[CoversNothing]
class PoolTest extends TestCase
{
    public function testQueueOverCapacityException()
    {
        $cap = 2;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Pool capacity exceeded: {$cap}");

        $queue = new QueuePool($cap);

        $worker1 = new Worker();
        $worker2 = new Worker();
        $queue->insert($worker1);
        $queue->insert($worker2);

        $worker3 = new Worker();
        $queue->insert($worker3);
    }

    public function testStackOverCapacityException()
    {
        $cap = 2;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Pool capacity exceeded: {$cap}");

        $queue = new StackPool($cap);

        $worker1 = new Worker();
        $worker2 = new Worker();
        $queue->insert($worker1);
        $queue->insert($worker2);

        $worker3 = new Worker();
        $queue->insert($worker3);
    }

    public function testQueuePool()
    {
        $queue = new QueuePool(5);
        $this->assertEquals(5, $queue->capacity());
        $this->assertEquals(0, $queue->count());

        $worker1 = new stdClass();
        $worker2 = new stdClass();
        $queue->insert($worker1);
        $queue->insert($worker2);

        $this->assertEquals(2, $queue->count());

        $this->assertSame($worker1, $queue->detach());
        $this->assertEquals(1, $queue->count());
        $this->assertSame($worker2, $queue->detach());
        $this->assertEquals(0, $queue->count());

        $this->assertNull($queue->detach());

        $queue->release($worker1);
        $this->assertSame($worker1, $queue->detach());
    }

    public function testStackPool()
    {
        $stack = new StackPool(5);
        $this->assertEquals(5, $stack->capacity());
        $this->assertEquals(0, $stack->count());

        $worker1 = new stdClass();
        $worker2 = new stdClass();
        $stack->insert($worker1);
        $stack->insert($worker2);

        $this->assertEquals(2, $stack->count());

        $this->assertSame($worker2, $stack->detach());
        $this->assertEquals(1, $stack->count());
        $this->assertSame($worker1, $stack->detach());
        $this->assertEquals(0, $stack->count());

        $this->assertNull($stack->detach());

        $stack->release($worker1);
        $this->assertSame($worker1, $stack->detach());

        $worker3 = new stdClass();
        $worker4 = new stdClass();
        $worker5 = new stdClass();
        $stack->insert($worker3);
        $stack->insert($worker4);
        $stack->insert($worker5);

        $this->assertSame($worker5, $stack->detach());
        $this->assertSame($worker4, $stack->detach());
        $this->assertSame($worker3, $stack->detach());
    }

    public function testQueueWithNodeInterface()
    {
        $queue = new QueuePool(5);

        $worker2 = new Worker();
        $worker3 = new Worker();
        $worker4 = new Worker();
        $worker5 = new Worker();
        $worker1 = new Worker();

        $queue->insert($worker1);
        $queue->insert($worker2);
        $queue->insert($worker3);
        $queue->insert($worker4);
        $queue->insert($worker5);

        $this->assertEquals(5, $queue->count());

        $queue->del($worker2);
        $queue->del($worker4);

        $this->assertEquals(3, $queue->count());

        $this->assertSame($worker1, $queue->detach());
        $this->assertSame($worker3, $queue->detach());
        $this->assertSame($worker5, $queue->detach());

        $this->assertEquals(0, $queue->count());
        $this->assertNull($queue->detach());
    }

    public function testStackWithNodeInterface()
    {
        $stack = new StackPool(5);

        $worker2 = new Worker();
        $worker3 = new Worker();
        $worker4 = new Worker();
        $worker5 = new Worker();
        $worker1 = new Worker();

        $stack->insert($worker1);
        $stack->insert($worker2);
        $stack->insert($worker3);
        $stack->insert($worker4);
        $stack->insert($worker5);

        $this->assertEquals(5, $stack->count());

        $stack->del($worker2);
        $stack->del($worker4);

        $this->assertEquals(3, $stack->count());

        $this->assertSame($worker5, $stack->detach());
        $this->assertSame($worker3, $stack->detach());
        $this->assertSame($worker1, $stack->detach());

        $this->assertEquals(0, $stack->count());
        $this->assertNull($stack->detach());
    }
}
