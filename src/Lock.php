<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Closure;

readonly class Lock
{
    /**
     * @param Closure($this): void $releaseFn
     * @param string $token
     */
    public function __construct(
        protected Closure $releaseFn,
        public string $token,
    )
    {
    }

    /**
     * @return void
     */
    public function release(): void
    {
        ($this->releaseFn)($this);
    }
}
