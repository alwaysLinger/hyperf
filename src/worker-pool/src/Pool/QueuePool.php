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

namespace Hyperf\WorkerPool\Pool;

use Hyperf\WorkerPool\Exception\RuntimeException;
use Hyperf\WorkerPool\Heap\WorkerMinHeap;
use Hyperf\WorkerPool\Pool\Contracts\PoolInterface;
use Hyperf\WorkerPool\Pool\Contracts\WithNodeInterface;
use Hyperf\WorkerPool\Worker;
use Iterator;
use WeakMap;

class QueuePool extends DoublyLinkedList implements PoolInterface
{
    protected WeakMap $map;

    protected WorkerMinHeap $heap;

    public function __construct(protected int $capacity)
    {
        $this->map = new WeakMap();
        $this->heap = new WorkerMinHeap();

        parent::__construct();
    }

    public function enqueue(mixed $value): Node
    {
        $node = $this->pushBack($value);
        if ($value instanceof WithNodeInterface) {
            $value->setNode($node);
        }

        return $node;
    }

    public function dequeue(): mixed
    {
        $front = $this->front();
        if ($front === null) {
            return null;
        }

        return $this->remove($front);
    }

    public function peek(): ?Node
    {
        return $this->front();
    }

    public function empty(): bool
    {
        return $this->len() === 0;
    }

    public function capacity(): int
    {
        return $this->capacity;
    }

    public function insert(object $value): void
    {
        if ($this->len() >= $this->capacity) {
            throw new RuntimeException("Pool capacity exceeded: {$this->capacity}");
        }

        $this->enqueue($value);
        if ($value instanceof Worker) {
            $this->map[$value] = true;
            $this->heap->insert($value);
        }
    }

    public function detach(): ?object
    {
        $value = $this->dequeue();
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Worker) {
            $this->heap->remove($value);
        }

        return $value;
    }

    public function release(object $value): void
    {
        $this->insert($value);
    }

    public function count(): int
    {
        return $this->len();
    }

    public function del(Node|WithNodeInterface $value): void
    {
        $node = $value;
        if ($value instanceof WithNodeInterface) {
            $node = $value->getNode();
        }

        $this->remove($node);
    }

    public function getIterator(): Iterator
    {
        return $this->map->getIterator();
    }

    public function collectBefore(int $at): void
    {
        if ($this->heap->len() == 0) {
            return;
        }

        while (true) {
            $value = $this->heap->top();
            if (is_null($value)) {
                break;
            }
            if (! is_object($value) || ! method_exists($value, 'activeAt')) {
                break;
            }
            if ($value->activeAt() >= $at) {
                break;
            }
            $value = $this->heap->extract();
            if (is_null($value)) {
                break;
            }

            $this->del($value);
            if ($value instanceof Worker) {
                $value->stop();
            }
            unset($this->map[$value]);
        }
    }

    public function ref(object $value): void
    {
        $this->map[$value] = true;
    }
}
