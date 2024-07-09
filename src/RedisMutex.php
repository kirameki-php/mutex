<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Kirameki\Mutex\Exceptions\MutexException;
use Kirameki\Redis\Options\SetMode;
use Kirameki\Redis\RedisConnection;
use Random\Randomizer;
use function bin2hex;
use function hrtime;

class RedisMutex extends Mutex
{
    public function __construct(
        protected RedisConnection $connection,
        protected Randomizer $randomizer,
        protected string $prefix = 'mutex:',
        protected int $retryIntervalMilliSeconds = 10,
    )
    {
    }

    /**
     * @param string $key
     * @param float $expireSeconds
     * @param float $waitSeconds
     * @return Lock
     */
    public function acquire(string $key, float $expireSeconds = 60.0, float $waitSeconds = 60.0): Lock
    {
        $lock = $this->instantiateLock($key);

        while (true) {
            // TODO allow px
            $result = $this->connection->set($lock->key, '_', SetMode::Nx, ex: (int) $expireSeconds);
            if ($result !== false) {
                return $lock;
            }

            if ($this->isWaitTimeout($lock, $waitSeconds)) {
                throw new MutexException('Failed to acquire lock within the time limit.');
            }

            usleep($this->retryIntervalMilliSeconds * 1000);
        }
    }

    /**
     * @param string $key
     * @return Lock|null
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
     * @param Lock $lock
     * @return void
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
    protected function isWaitTimeout(Lock $lock, float $waitSeconds): bool
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
