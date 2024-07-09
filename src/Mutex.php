<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Closure;

abstract class Mutex
{
    /**
     * @template TResult
     * @param string $key
     * @param Closure(): TResult $callback
     * @param float $waitSeconds
     * @return TResult
     */
    public function synchronize(string $key, Closure $callback, float $waitSeconds = 60.0): mixed
    {
        $lock = $this->acquire($key, $waitSeconds);
        try {
            return $callback();
        } finally {
            $this->release($lock);
        }
    }

    /**
     * @param string $key
     * @param float $expireSeconds
     * @param float $waitSeconds
     * @return Lock
     */
    abstract public function acquire(string $key, float $expireSeconds, float $waitSeconds = 60.0): Lock;

    /**
     * @param string $key
     * @return Lock|null
     */
    abstract public function tryAcquire(string $key): ?Lock;

    /**
     * @param Lock $lock
     * @return void
     */
    abstract protected function release(Lock $lock): void;
}
