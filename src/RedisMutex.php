<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Kirameki\Core\Sleep;
use Kirameki\Mutex\Exceptions\MutexException;
use Kirameki\Mutex\Exceptions\MutexTimeoutException;
use Kirameki\Redis\Options\SetMode;
use Kirameki\Redis\RedisConnection;
use Random\Randomizer;
use function bin2hex;
use function hrtime;
use function min;

class RedisMutex extends Mutex
{
    /**
     * @var Randomizer
     */
    protected Randomizer $randomizer;

    /**
     * @var Sleep
     */
    protected Sleep $sleep;

    /**
     * @param RedisConnection $connection
     * @param string $prefix
     * @param int $retryIntervalMilliseconds
     * @param int $retryMaxIntervalMilliseconds
     * @param float $retryBackoffMultiplier
     * @param Randomizer|null $randomizer
     * @param Sleep|null $sleep
     */
    public function __construct(
        protected RedisConnection $connection,
        protected string $prefix = 'mutex:',
        protected int $retryIntervalMilliseconds = 10,
        protected int $retryMaxIntervalMilliseconds = 100,
        protected float $retryBackoffMultiplier = 2.0,
        Randomizer $randomizer = null,
        ?Sleep $sleep = null,
    )
    {
        $this->randomizer = $randomizer ?? new Randomizer();
        $this->sleep = $sleep ?? new Sleep();
    }

    /**
     * @inheritDoc
     */
    public function acquire(string $key, float $timeoutSeconds = 1.0, int $expireSeconds = 60): Lock
    {
        $lock = $this->instantiateLock($key);

        $tries = 1;
        while (true) {
            $result = $this->connection->set($lock->key, '_', SetMode::Nx, ex: $expireSeconds);
            if ($result !== false) {
                return $lock;
            }

            if ($this->waitTimeExceeded($lock, $timeoutSeconds)) {
                throw new MutexTimeoutException(
                    "Mutex acquire timeout. (key: '{$lock->key}', waitSeconds: {$timeoutSeconds})",
                );
            }

            $sleepMs = $this->retryIntervalMilliseconds * ($tries ** $this->retryBackoffMultiplier);
            $this->sleep->milliseconds(min($sleepMs, $this->retryMaxIntervalMilliseconds));
            $tries++;
        }
    }

    /**
     * @inheritDoc
     */
    public function tryAcquire(string $key): ?Lock
    {
        $lock = $this->instantiateLock($key);
        $result = $this->connection->set($lock->key, $lock->token, SetMode::Nx);
        if ($result === false) {
            return null;
        }
        return $lock;
    }

    /**
     * @param string $key
     * @return Lock
     */
    protected function instantiateLock(string $key): Lock
    {
        $token = bin2hex($this->randomizer->getBytes(16));
        $startTimestamp = $this->getHrTimestamp();
        return new Lock($key, $token, $startTimestamp, $this->release(...));
    }

    /**
     * @inheritDoc
     */
    protected function release(Lock $lock): void
    {
        $script = $this->getReleaseScript();
        $result = $this->connection->eval($script, 1, $lock->key, $lock->token);

        switch ($result) {
            case -1:
                throw new MutexException("Lock already released. (key: '{$lock->key}')");
            case 0:
                throw new MutexException("Token did not match. (key: '{$lock->key}', token: '{$lock->token}')");
        }
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

    /**
     * @return string
     */
    protected function getReleaseScript(): string
    {
        return <<<LUA
            token = redis.call('get', KEYS[1])
            
            if token == false then
                return -1
            end
            
            if token == ARGV[1] then
                return redis.call('del', KEYS[1])
            else
                return 0
            end
        LUA;
    }
}
