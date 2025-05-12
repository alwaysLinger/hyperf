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

namespace Hyperf\WorkerPool\Pool\Contracts;

use Hyperf\WorkerPool\Pool\Node;
use Iterator;

interface PoolInterface
{
    public function capacity(): int;

    public function ref(object $value): void;

    public function insert(object $value): void;

    public function detach(): ?object;

    public function release(object $value): void;

    public function count(): int;

    public function del(Node|WithNodeInterface $value): void;

    public function getIterator(): Iterator;

    public function collectBefore(int $at): void;
}
