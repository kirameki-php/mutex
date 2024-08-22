<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Kirameki\Core\Sleep;
use Kirameki\Mutex\Configs\FileMutexConfig;
use Override;
use Random\Randomizer;
use function fclose;
use function flock;
use function fopen;
use function fread;
use function fwrite;
use function md5;
use const DIRECTORY_SEPARATOR;
use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;
use const PHP_INT_MAX;

/**
 * @extends AbstractMutex<FileMutexConfig>
 */
class FileMutex extends AbstractMutex implements InterprocessMutex
{
    /**
     * @var resource|null
     */
    protected $stream = null;

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
        $stream = fopen($this->keyToFilename($lock), 'w+');
        if ($stream !== false) {
            if (flock($stream, LOCK_EX | LOCK_NB)) {
                fwrite($stream, $lock->token);
                $this->stream = $stream;
                return true;
            } else {
                fclose($stream);
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
        $stream = $this->stream;

        if ($stream === null) {
            $this->throwLockAlreadyReleasedException($lock);
        }

        $token = fread($stream, PHP_INT_MAX) ?: '';

        if ($lock->token !== $token) {
            $this->throwTokenMismatchException($lock, $token);
        }

        flock($stream, LOCK_UN);
        fclose($stream);

        $this->stream = null;
    }

    /**
     * @param Lock $lock
     * @return string
     */
    protected function keyToFilename(Lock $lock): string
    {
        return $this->config->directory .
            DIRECTORY_SEPARATOR .
            $this->config->prefix .
            md5($lock->key);
    }
}
