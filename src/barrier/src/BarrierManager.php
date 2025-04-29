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

namespace Hyperf\Barrier;

use Exception;
use Hyperf\Barrier\Exception\BarrierException;
use Hyperf\Barrier\Exception\RuntimeException;
use Hyperf\Coroutine\Coroutine;
use Hyperf\Support\Traits\Container;
use WeakMap;

class BarrierManager
{
    use Container;

    private static array $latestBarrier = [];

    /**
     * @throws BarrierException
     * @throws Exception
     */
    public static function awaitOnCounter(string $barrierKey, int $parties, float $timeout = -1): void
    {
        if (! Coroutine::inCoroutine()) {
            throw new RuntimeException('Barrier can only be used in coroutine environment');
        }

        if ($parties <= 1) {
            throw new RuntimeException('Parties must be greater than 1');
        }

        $batchKey = $barrierKey . $parties;
        if (! self::has($batchKey)) {
            self::setBarrier($batchKey, new CounterBarrier($barrierKey, $parties));
        }

        $barrier = self::getBarrier($batchKey);
        if ($barrier->broken() || $barrier->waiters() == $parties) {
            $barrier = new CounterBarrier($barrierKey, $parties);
            self::setBarrier($batchKey, $barrier);
        }

        try {
            $barrier->await($timeout);
        } catch (BarrierException $ex) {
            $exception = $ex;
        } finally {
            if ($barrier->broken() && $barrier->waiters() == 0) {
                self::clearLatestBarrier($batchKey, $barrier);
                unset($barrier);
            }
            self::clearBarrier($batchKey);
            if (isset($exception)) {
                throw $exception;
            }
        }
    }

    private static function getBarrier(string $batchKey): ?BarrierInterface
    {
        return self::$latestBarrier[$batchKey] ?? null;
    }

    private static function setBarrier(string $batchKey, BarrierInterface $barrier): void
    {
        if (! self::has($batchKey)) {
            self::set($batchKey, new WeakMap());
        }

        self::$latestBarrier[$batchKey] = $barrier;

        /** @var WeakMap $map */
        $map = self::get($batchKey);
        $map[$barrier] = true;
    }

    private static function clearLatestBarrier(string $batchKey, BarrierInterface $barrier): void
    {
        if (self::getBarrier($batchKey) === $barrier) {
            unset(self::$latestBarrier[$batchKey]);
        }
    }

    private static function clearBarrier(string $batchKey): void
    {
        /** @var WeakMap $map */
        $map = self::get($batchKey);
        if ((int) $map?->count() === 0) {
            unset(self::$container[$batchKey]);
        }
    }
}
