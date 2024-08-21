<?php declare(strict_types=1);

namespace Kirameki\Mutex\Configs;

abstract class MutexConfig
{
    /**
     * @param int $retryIntervalMilliseconds
     * @param int $retryMaxIntervalMilliseconds
     * @param float $retryBackoffMultiplier
     */
    public function __construct(
        public int $retryIntervalMilliseconds = 10,
        public int $retryMaxIntervalMilliseconds = 100,
        public float $retryBackoffMultiplier = 2.0,
    )
    {
    }
}
