<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    private string $originalCachePath;
    private string $originalLogPath;

    protected function setUp(): void
    {
        $this->originalCachePath = $_ENV['CACHE_PATH'] ?? '';
        $this->originalLogPath = $_ENV['LOG_PATH'] ?? '';
    }

    protected function tearDown(): void
    {
        if ($this->originalCachePath === '') {
            unset($_ENV['CACHE_PATH']);
        } else {
            $_ENV['CACHE_PATH'] = $this->originalCachePath;
        }

        if ($this->originalLogPath === '') {
            unset($_ENV['LOG_PATH']);
        } else {
            $_ENV['LOG_PATH'] = $this->originalLogPath;
        }
    }

    // -------------------------------------------------------------------------
    // getCacheDir
    // -------------------------------------------------------------------------

    public function testGetCacheDirDefaultsToProjectVar(): void
    {
        unset($_ENV['CACHE_PATH']);

        $kernel = new Kernel('test', false);
        $cacheDir = $kernel->getCacheDir();

        $this->assertStringEndsWith(DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'test', $cacheDir);
        $this->assertStringStartsWith($kernel->getProjectDir(), $cacheDir);
    }

    public function testGetCacheDirWithRelativePath(): void
    {
        $_ENV['CACHE_PATH'] = 'var/cache';

        $kernel = new Kernel('test', false);
        $cacheDir = $kernel->getCacheDir();

        $this->assertSame(
            $kernel->getProjectDir() . DIRECTORY_SEPARATOR . 'var/cache' . DIRECTORY_SEPARATOR . 'test',
            $cacheDir
        );
    }

    public function testGetCacheDirWithAbsolutePath(): void
    {
        $_ENV['CACHE_PATH'] = '/tmp/episciences/cache';

        $kernel = new Kernel('prod', false);
        $cacheDir = $kernel->getCacheDir();

        $this->assertSame('/tmp/episciences/cache' . DIRECTORY_SEPARATOR . 'prod', $cacheDir);
    }

    public function testGetCacheDirStripsTrailingSeparator(): void
    {
        $_ENV['CACHE_PATH'] = 'var/cache/';

        $kernel = new Kernel('dev', false);
        $cacheDir = $kernel->getCacheDir();

        // Should not contain double separator before environment name
        $this->assertStringEndsWith(DIRECTORY_SEPARATOR . 'dev', $cacheDir);
        $this->assertStringNotContainsString(DIRECTORY_SEPARATOR . DIRECTORY_SEPARATOR, $cacheDir);
    }

    public function testGetCacheDirIgnoresEmptyPath(): void
    {
        $_ENV['CACHE_PATH'] = '';

        $kernel = new Kernel('test', false);
        $cacheDir = $kernel->getCacheDir();

        $this->assertStringEndsWith(DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'test', $cacheDir);
    }

    // -------------------------------------------------------------------------
    // getLogDir
    // -------------------------------------------------------------------------

    public function testGetLogDirDefaultsToProjectVar(): void
    {
        unset($_ENV['LOG_PATH']);

        $kernel = new Kernel('test', false);
        $logDir = $kernel->getLogDir();

        $this->assertStringEndsWith(DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log', $logDir);
        $this->assertStringStartsWith($kernel->getProjectDir(), $logDir);
    }

    public function testGetLogDirWithRelativePath(): void
    {
        $_ENV['LOG_PATH'] = 'var/log';

        $kernel = new Kernel('test', false);
        $logDir = $kernel->getLogDir();

        $this->assertSame(
            $kernel->getProjectDir() . DIRECTORY_SEPARATOR . 'var/log',
            $logDir
        );
    }

    public function testGetLogDirWithAbsolutePath(): void
    {
        $_ENV['LOG_PATH'] = '/tmp/episciences/log';

        $kernel = new Kernel('prod', false);
        $logDir = $kernel->getLogDir();

        $this->assertSame('/tmp/episciences/log', $logDir);
    }

    public function testGetLogDirStripsTrailingSeparator(): void
    {
        $_ENV['LOG_PATH'] = 'var/log/';

        $kernel = new Kernel('test', false);
        $logDir = $kernel->getLogDir();

        $this->assertStringNotContainsString('//', $logDir);
        $this->assertStringEndsNotWith(DIRECTORY_SEPARATOR, $logDir);
    }

    public function testGetLogDirIgnoresEmptyPath(): void
    {
        $_ENV['LOG_PATH'] = '';

        $kernel = new Kernel('test', false);
        $logDir = $kernel->getLogDir();

        $this->assertStringEndsWith(DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log', $logDir);
    }
}
