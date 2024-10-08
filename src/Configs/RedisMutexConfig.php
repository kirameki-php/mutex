<?php declare(strict_types=1);

namespace Kirameki\Mutex\Configs;

class RedisMutexConfig extends MutexConfig
{
    /**
     * @param string $connection
     * @param string $prefix
     * @param int $retryIntervalMilliseconds
     * @param int $retryMaxIntervalMilliseconds
     * @param float $retryBackoffMultiplier
     */
    public function __construct(
        public string $connection,
        public string $prefix = 'mutex:',
        int $retryIntervalMilliseconds = 10,
        int $retryMaxIntervalMilliseconds = 100,
        float $retryBackoffMultiplier = 2.0,
    )
    {
        parent::__construct(
            $retryIntervalMilliseconds,
            $retryMaxIntervalMilliseconds,
            $retryBackoffMultiplier,
        );
    }
}
