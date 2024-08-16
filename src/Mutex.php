<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Closure;
use Kirameki\Mutex\Exceptions\MutexTimeoutException;

abstract class Mutex
{
    /**
     * @template TResult
     * @param string $key
     * @param Closure(): TResult $callback
     * @param float $timeoutSeconds
     * @param int $expireSeconds
     * @return TResult
     */
    public function synchronize(string $key, Closure $callback, float $timeoutSeconds = 1.0, int $expireSeconds = 60): mixed
    {
        $lock = $this->acquire($key, $timeoutSeconds, $expireSeconds);
        try {
            return $callback();
        } finally {
            $this->release($lock);
        }
    }

    /**
     * @param string $key
     * @param float $timeoutSeconds
     * @param int $expireSeconds
     * @return Lock
     * @throws MutexTimeoutException
     */
    abstract public function acquire(string $key, float $timeoutSeconds = 1.0, int $expireSeconds = 60): Lock;

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
