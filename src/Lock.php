<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Closure;
use Kirameki\Mutex\Exceptions\MutexException;

class Lock
{
    /**
     * @var bool
     */
    protected bool $released = false;

    /**
     * @param string $key
     * @param string $token
     * @param float $startTimestamp
     * @param Closure($this): mixed $releaseFn
     */
    public function __construct(
        public readonly string $key,
        public readonly string $token,
        public readonly float $startTimestamp,
        protected readonly Closure $releaseFn,
    )
    {
    }

    /**
     * @return void
     */
    public function release(): void
    {
        if ($this->released) {
            throw new MutexException("Release was already called. (key: '{$this->key}')");
        }

        ($this->releaseFn)($this);

        $this->released = true;
    }
}
