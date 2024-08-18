<?php declare(strict_types=1);

namespace Kirameki\Mutex\Configs;

class RedisMutexConfig
{
    /**
     * @param string $prefix
     * @param int $retryIntervalMilliseconds
     * @param int $retryMaxIntervalMilliseconds
     * @param float $retryBackoffMultiplier
     */
    public function __construct(
        public string $prefix = 'mutex:',
        public int $retryIntervalMilliseconds = 10,
        public int $retryMaxIntervalMilliseconds = 100,
        public float $retryBackoffMultiplier = 2.0,
    )
    {
    }
}
