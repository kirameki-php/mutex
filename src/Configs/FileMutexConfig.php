<?php declare(strict_types=1);

namespace Kirameki\Mutex\Configs;

class FileMutexConfig extends MutexConfig
{
    /**
     * @param string $basePath
     * @param int $retryIntervalMilliseconds
     * @param int $retryMaxIntervalMilliseconds
     * @param float $retryBackoffMultiplier
     */
    public function __construct(
        public string $basePath,
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
