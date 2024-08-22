<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Closure;
use Kirameki\Core\Sleep;
use Kirameki\Mutex\Configs\MutexConfig;
use Kirameki\Mutex\Exceptions\MutexException;
use Kirameki\Mutex\Exceptions\MutexTimeoutException;
use Random\Randomizer;
use function bin2hex;
use function hrtime;
use function min;

/**
 * @template TMutexConfig of MutexConfig
 */
abstract class AbstractMutex implements Mutex
{
    protected const int TokenBytes = 8;

    /**
     * @var Randomizer
     */
    protected Randomizer $randomizer;

    /**
     * @var Sleep
     */
    protected Sleep $sleep;

    /**
     * @param TMutexConfig $config
     * @param Randomizer|null $randomizer
     * @param Sleep|null $sleep
     */
    public function __construct(
        protected MutexConfig $config,
        ?Randomizer $randomizer = null,
        ?Sleep $sleep = null,
    )
    {
        $this->randomizer = $randomizer ?? new Randomizer();
        $this->sleep = $sleep ?? new Sleep();
    }

    /**
     * @inheritDoc
     */
    public function synchronize(string $key, Closure $callback, float $timeoutSeconds = 1.0, int $expireSeconds = 60): mixed
    {
        $lock = $this->acquire($key, $timeoutSeconds, $expireSeconds);
        try {
            return $callback();
        } finally {
            $this->release($lock);
        }
    }

    /**
     * @param string $key
     * @param float $timeoutSeconds
     * @param int $expireSeconds
     * @return Lock
     */
    protected function acquire(string $key, float $timeoutSeconds = 1.0, int $expireSeconds = 60): Lock
    {
        $tries = 1;
        while (true) {
            $lock = $this->instantiateLock($key, $expireSeconds);
            if ($this->tryLocking($lock)) {
                return $lock;
            }

            if ($this->waitTimeExceeded($lock, $timeoutSeconds)) {
                throw new MutexTimeoutException(
                    "Mutex acquire timeout. (key: '{$lock->key}', waitSeconds: {$timeoutSeconds})",
                );
            }

            $config = $this->config;
            $interval = $config->retryIntervalMilliseconds;
            $maxInterval = $config->retryMaxIntervalMilliseconds;
            $backoffMultiplier = $config->retryBackoffMultiplier;
            $sleepMs = $interval * ($tries ** $backoffMultiplier);
            $this->sleep->milliseconds(min($sleepMs, $maxInterval));
            $tries++;
        }
    }

    /**
     * @inheritDoc
     */
    public function tryAcquire(string $key, int $expireSeconds = 60): ?Lock
    {
        $lock = $this->instantiateLock($key, $expireSeconds);
        if ($this->tryLocking($lock)) {
            return $lock;
        }
        return null;
    }

    /**
     * @param string $key
     * @param int $expireSeconds
     * @return Lock
     */
    protected function instantiateLock(string $key, int $expireSeconds): Lock
    {
        $token = bin2hex($this->randomizer->getBytes(self::TokenBytes));
        $startTimestamp = $this->getHrTimestamp();
        $expireTimestamp = (int)($startTimestamp + $expireSeconds);
        $releaseFn = $this->release(...);
        return new Lock($key, $token, $startTimestamp, $expireTimestamp, $releaseFn);
    }

    /**
     * @param Lock $lock
     * @return bool
     */
    abstract protected function tryLocking(Lock $lock): bool;

    /**
     * @param Lock $lock
     * @return void
     */
    abstract protected function release(Lock $lock): void;

    /**
     * @param Lock $lock
     * @return never
     */
    protected function throwLockAlreadyReleasedException(Lock $lock): never
    {
        throw new MutexException("Lock already released. (key: '{$lock->key}')");
    }

    /**
     * @param Lock $lock
     * @param string $token
     * @return never
     */
    protected function throwTokenMismatchException(Lock $lock, string $token): never
    {
        throw new MutexException(
            "Token mismatch. (key: '{$lock->key}', expected: '{$lock->token}' actual: '{$token}')",
        );
    }

    /**
     * @return float
     */
    protected function getHrTimestamp(): float
    {
        return hrtime(true) / 1e+9;
    }

    /**
     * @param Lock $lock
     * @param float $waitSeconds
     * @return bool
     */
    protected function waitTimeExceeded(Lock $lock, float $waitSeconds): bool
    {
        $diff = $this->getHrTimestamp() - $lock->startTimestamp;
        return $diff >= $waitSeconds;
    }
}
