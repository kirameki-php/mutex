<?php declare(strict_types=1);

namespace Kirameki\Mutex;

use Kirameki\Core\Sleep;
use Kirameki\Mutex\Configs\FileMutexConfig;
use Override;
use Random\Randomizer;
use function assert;
use function fclose;
use function flock;
use function fopen;
use function fread;
use function fseek;
use function fwrite;
use function md5;
use function unlink;
use const DIRECTORY_SEPARATOR;
use const LOCK_EX;
use const LOCK_NB;
use const LOCK_UN;

/**
 * @extends AbstractMutex<FileMutexConfig>
 */
class FileMutex extends AbstractMutex implements InterprocessMutex
{
    /**
     * @var resource|null
     */
    protected $stream = null;

    protected ?string $streamPath = null;

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
        $streamPath = $this->keyToFilename($lock);
        $stream = fopen($streamPath, 'w+');
        if ($stream !== false) {
            if (flock($stream, LOCK_EX | LOCK_NB)) {
                fwrite($stream, $lock->token);
                $this->stream = $stream;
                $this->streamPath = $streamPath;
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

        fseek($stream, 0);
        $token = fread($stream, self::TokenBytes * 2) ?: '';

        if ($lock->token !== $token) {
            $this->throwTokenMismatchException($lock, $token);
        }

        flock($stream, LOCK_UN);
        fclose($stream);

        assert($this->streamPath !== null);
        unlink($this->streamPath);

        $this->stream = null;
        $this->streamPath = null;
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
