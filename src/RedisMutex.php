<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Kirameki\Core\Sleep;
use Kirameki\Mutex\Configs\RedisMutexConfig;
use Kirameki\Redis\Options\SetMode;
use Kirameki\Redis\RedisConnection;
use Kirameki\Redis\RedisManager;
use Override;
use Random\Randomizer;

/**
 * @extends AbstractMutex<RedisMutexConfig>
 */
class RedisMutex extends AbstractMutex implements DistributedMutex
{
    /**
     * @var RedisConnection|null
     */
    protected ?RedisConnection $redis = null;

    /**
     * @param RedisManager $redisManager
     * @param RedisMutexConfig $config
     * @param Randomizer|null $randomizer
     * @param Sleep|null $sleep
     */
    public function __construct(
        protected RedisManager $redisManager,
        RedisMutexConfig $config,
        ?Randomizer $randomizer = null,
        ?Sleep $sleep = null,
    )
    {
        parent::__construct($config, $randomizer, $sleep);
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function tryLocking(Lock $lock): bool
    {
        return $this->getRedis()->set($lock->key, $lock->token, SetMode::Nx) !== false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function release(Lock $lock): void
    {
        $script = <<<LUA
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

        $redis = $this->getRedis();
        $result = $redis->eval($script, 1, $lock->key, $lock->token);

        switch ($result) {
            case -1:
                $this->throwLockAlreadyReleasedException($lock);
            case 0:
                $this->throwTokenMismatchException($lock, $redis->get($lock->key));
        }
    }


    /**
     * @return RedisConnection
     */
    protected function getRedis(): RedisConnection
    {
        return $this->redis ??= $this->redisManager->use($this->config->connection);
    }
}
