<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Kirameki\Core\Sleep;
use Kirameki\Mutex\Configs\FileMutexConfig;
use Kirameki\Mutex\Exceptions\MutexException;
use Override;
use Random\Randomizer;
use function fclose;
use function flock;
use function fopen;
use function fread;
use function fwrite;
use const DIRECTORY_SEPARATOR as DS;
use const LOCK_EX;
use const LOCK_NB;
use const PHP_INT_MAX;

/**
 * @extends AbstractMutex<FileMutexConfig>
 */
class FileMutex extends AbstractMutex implements InterprocessMutex
{
    /**
     * @var resource|null
     */
    protected $resource = null;

    /**
     * @param FileMutexConfig $config
     * @param Randomizer|null $randomizer
     * @param Sleep|null $sleep
     */
    public function __construct(
        FileMutexConfig $config,
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
    protected function tryLocking(Lock $lock): bool
    {
        $filePath = $this->config->basePath . DS . $lock->key;
        $file = fopen($filePath, 'w+');
        if ($file !== false) {
            if (flock($file, LOCK_EX | LOCK_NB)) {
                fwrite($file, $lock->token);
                $this->resource = $file;
                return true;
            } else {
                fclose($file);
            }
        }
        return false;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    protected function release(Lock $lock): void
    {
        $resource = $this->resource;

        if ($resource === null) {
            $this->throwLockAlreadyReleasedException($lock);
        }

        $token = fread($resource, PHP_INT_MAX) ?: '';

        if ($lock->token !== $token) {
            $this->throwTokenMismatchException($lock, $token);
        }

        flock($resource, LOCK_UN);
        fclose($resource);

        $this->resource = null;
    }
}
