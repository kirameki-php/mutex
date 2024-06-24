<?php declare(strict_types=1);

namespace Kirameki\Mutex;

interface DistributedLock
{
    /**
     * @param bool $block
     * @param float $blockTimeoutSeconds
     * @param string|null $token
     */
    public function acquire(bool $block = false, float $blockTimeoutSeconds = 0, string $token = null);
}
