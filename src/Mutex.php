<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Closure;

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
     * @return Lock|null
     */
    public function tryAcquire(string $key): ?Lock;
}
