<?php declare(strict_types=1);

namespace Tests\Kirameki\Mutex;

use Kirameki\Core\Testing\TestCase;
use Kirameki\Mutex\Configs\FileMutexConfig;
use Kirameki\Mutex\FileMutex;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use function dump;
use function glob;
use function scandir;
use function unlink;

final class FileMutexTest extends TestCase
{
    protected string $fileDir = '/tmp/kirameki-mutex-test';

    #[Before]
    protected function createTempDir(): void
    {
        if (!file_exists($this->fileDir)) {
            mkdir($this->fileDir);
        }
    }

    /**
     * @return list<string>
     */
    protected function scanTempDir(string $pattern = '*'): array
    {
        return glob("{$this->fileDir}/{$pattern}") ?: [];
    }

    #[After]
    protected function removeTempDir(): void
    {
        if (file_exists($this->fileDir)) {
            array_map(unlink(...), $this->scanTempDir());
            rmdir($this->fileDir);
        }
    }

    public function test_tryAcquire(): void
    {
        $mutex = new FileMutex(new FileMutexConfig($this->fileDir));
        $lock = $mutex->tryAcquire('test');
        $this->assertNotNull($lock);

        $this->assertCount(1, $this->scanTempDir("mutex_*"));
        $lock->release();
        $this->assertCount(0, $this->scanTempDir("mutex_*"));
    }
}
