<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;

class KernelTest extends TestCase
{
    private const array VARS = ['CACHE_PATH', 'LOG_PATH'];

    /** @var array<string, mixed> */
    private array $backup = [];

    protected function setUp(): void
    {
        foreach (self::VARS as $name) {
            $this->backup[$name] = $_ENV[$name] ?? null;
            unset($_ENV[$name]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->backup as $name => $value) {
            if ($value === null) {
                unset($_ENV[$name]);
            } else {
                $_ENV[$name] = $value;
            }
        }
    }

    private function kernel(): Kernel
    {
        return new Kernel('test', false);
    }

    public function testRelativeCachePathIsResolvedAgainstProjectDir(): void
    {
        $_ENV['CACHE_PATH'] = 'var/cache/';
        $kernel = $this->kernel();

        self::assertSame($kernel->getProjectDir() . '/var/cache/test', $kernel->getCacheDir());
    }

    public function testRelativeCachePathDoesNotDependOnWorkingDirectory(): void
    {
        $_ENV['CACHE_PATH'] = 'var/cache/';
        $kernel = $this->kernel();
        $expected = $kernel->getCacheDir();

        $cwd = getcwd();
        chdir($kernel->getProjectDir() . '/public');
        try {
            self::assertSame($expected, $kernel->getCacheDir());
        } finally {
            chdir((string)$cwd);
        }
    }

    public function testAbsoluteCachePathIsKept(): void
    {
        $_ENV['CACHE_PATH'] = '/var/cache/';

        self::assertSame('/var/cache/test', $this->kernel()->getCacheDir());
    }

    public function testEmptyCachePathFallsBackToDefault(): void
    {
        $_ENV['CACHE_PATH'] = '';
        $kernel = $this->kernel();

        self::assertSame($kernel->getProjectDir() . '/var/cache/test', $kernel->getCacheDir());
    }

    public function testRelativeLogPathIsResolvedAgainstProjectDir(): void
    {
        $_ENV['LOG_PATH'] = 'var/log';
        $kernel = $this->kernel();

        self::assertSame($kernel->getProjectDir() . '/var/log', $kernel->getLogDir());
    }

    public function testAbsoluteLogPathIsKept(): void
    {
        $_ENV['LOG_PATH'] = '/var/log';

        self::assertSame('/var/log', $this->kernel()->getLogDir());
    }

    public function testMissingLogPathFallsBackToDefault(): void
    {
        $kernel = $this->kernel();

        self::assertSame($kernel->getProjectDir() . '/var/log', $kernel->getLogDir());
    }
}
