<?php declare(strict_types=1);

namespace Kirameki\Mutex;

abstract class Mutex
{
    /**
     * @param float $waitSeconds
     * @param string|null $token
     * @return Lock
     */
    abstract public function acquire(float $waitSeconds = 60.0, string $token = null): Lock;

    /**
     * @param string|null $token
     * @return Lock|null
     */
    abstract public function tryAcquire(?string $token = null): ?Lock;

    /**
     * @param Lock $lock
     * @return void
     */
    abstract protected function release(Lock $lock): void;
}
