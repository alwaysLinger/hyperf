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

namespace Hyperf\WorkerPool;

use Closure;
use Hyperf\Engine\Channel;
use Hyperf\WorkerPool\Exception\RuntimeException;
use Hyperf\WorkerPool\Exception\TimeoutException;
use Hyperf\WorkerPool\Exception\WorkerPoolException;
use Hyperf\WorkerPool\Pool\Contracts\PoolInterface;
use Hyperf\WorkerPool\Pool\QueuePool;
use Hyperf\WorkerPool\Pool\StackPool;

use function Hyperf\Coroutine\go;

class WorkerPool
{
    private bool $running = true;

    private int $runnings = 0;

    private ?PoolInterface $workers;

    private ?Channel $gcChan = null;

    private Channel $workerChan;

    private Closure $workerDone;

    public function __construct(protected ?Config $config = null)
    {
        if ($this->config === null) {
            $this->config = new Config();
        }
        $this->config->check();

        $this->workerChan = new Channel();

        $this->workers = match ($this->config->getPoolType()) {
            Config::QUEUE_POOL => new QueuePool($this->config->getCapacity()),
            Config::STACK_POOL => new StackPool($this->config->getCapacity()),
        };

        $this->workerDone = $this->release(...);

        $this->collectWorkers();

        if ($this->config->isPreSpawn()) {
            $this->spawnWorkers($this->config->getCapacity());
        }
    }

    /**
     * @throws WorkerPoolException
     */
    public function submit(callable $task, float $timeout = -1, bool $sync = false): mixed
    {
        return $this->submitTask(new Task($task(...), $sync), $timeout);
    }

    /**
     * @throws WorkerPoolException
     */
    public function submitTask(TaskInterface $task, float $timeout = -1): mixed
    {
        if (! $this->running) {
            throw new RuntimeException('Pool closed, cannot submit task');
        }

        $worker = $this->getWorker($timeout);

        $ret = $worker->submit($task);
        if ($ret instanceof WorkerPoolException) {
            throw $ret;
        }
        return $ret;
    }

    public function stop(): void
    {
        $this->running = false;
        $this->gcChan?->close();
        foreach ($this->workers->getIterator() as $worker => $v) {
            $this->stopWorker($worker);
        }
    }

    private function getWorker(float $timeout = -1): Worker
    {
        $worker = $this->workers->detach();
        if ($worker) {
            return $worker;
        }

        if ($this->workers->count() == 0 && $this->config->getCapacity() > $this->runnings) {
            return $this->startWorker();
        }

        if ($this->config->getMaxBlocks() <= 0 || $this->workerChan->stats()['consumer_num'] >= $this->config->getMaxBlocks()) {
            throw new RuntimeException('WorkerPool exhausted');
        }

        $worker = $this->workerChan->pop($timeout);
        if ($worker === false && $this->workerChan->isTimeout()) {
            throw new TimeoutException('Waiting for available worker timeout');
        }

        return $worker;
    }

    private function spawnWorkers(int $num): void
    {
        for ($i = 0; $i < $num; ++$i) {
            $this->workers->insert($this->startWorker());
        }
    }

    private function startWorker(): Worker
    {
        ++$this->runnings;

        $worker = (new Worker($this->workerDone))->run();
        $worker->setRef($this->workers);
        return $worker;
    }

    private function stopWorker(Worker $worker): void
    {
        $worker->stop();
        --$this->runnings;
    }

    private function release(Worker $worker): void
    {
        if ($this->workerChan->stats()['consumer_num'] > 0) {
            $this->workerChan->push($worker);
            return;
        }

        $this->workers->release($worker);
    }

    private function collectWorkers(): void
    {
        if ($this->config->getGcIntervalMs() >= 0) {
            $interval = $this->config->getGcIntervalMs();
            $this->gcChan = new Channel();
            go(function () use ($interval) {
                $intervalSecond = $interval / 1000;
                while (true) {
                    if (! $this->running) {
                        break;
                    }
                    $this->gcChan->pop($intervalSecond);
                    if (! $this->running) {
                        break;
                    }
                    if ($this->gcChan->isTimeout()) {
                        $at = (int) (microtime(true) * 1000) - $interval;
                        $this->workers->collectBefore($at);
                        continue;
                    }
                    break;
                }
            });
        }
    }
}
