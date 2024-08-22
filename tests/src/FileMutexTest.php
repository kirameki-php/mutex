<?php declare(strict_types=1);

namespace Tests\Kirameki\Mutex;

use Kirameki\Core\Testing\TestCase;
use Kirameki\Mutex\Configs\FileMutexConfig;
use Kirameki\Mutex\FileMutex;
use function dump;

final class FileMutexTest extends TestCase
{
    public function test_locking(): void
    {
        $mutex = new FileMutex(new FileMutexConfig('/tmp'));
        $lock = $mutex->tryAcquire('test');
        $lock?->release();
    }
}
