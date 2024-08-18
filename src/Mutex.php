<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Closure;
use Kirameki\Mutex\Exceptions\MutexTimeoutException;

interface Mutex
{
    /**
     * @template TResult
     * @param string $key
     * @param Closure(): TResult $callback
     * @param float $timeoutSeconds
     * @param int $expireSeconds
     * @return TResult
     */
    public function synchronize(string $key, Closure $callback, float $timeoutSeconds = 1.0, int $expireSeconds = 60);

    /**
     * @param string $key
     * @param float $timeoutSeconds
     * @param int $expireSeconds
     * @return Lock
     * @throws MutexTimeoutException
     */
    public function acquire(string $key, float $timeoutSeconds = 1.0, int $expireSeconds = 60): Lock;

    /**
     * @param string $key
     * @return Lock|null
     */
    public function tryAcquire(string $key): ?Lock;

    /**
     * @param Lock $lock
     * @return void
     */
    public function release(Lock $lock): void;
}
