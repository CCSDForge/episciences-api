<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Kernel;
use PHPUnit\Framework\TestCase;

class KernelTest extends TestCase
{
    private const array VARS = ['CACHE_PATH', 'LOG_PATH'];

    /** @var array<string, array{env: mixed, server: mixed}> */
    private array $backup = [];

    protected function setUp(): void
    {
        foreach (self::VARS as $name) {
            $this->backup[$name] = ['env' => $_ENV[$name] ?? null, 'server' => $_SERVER[$name] ?? null];
            unset($_ENV[$name], $_SERVER[$name]);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->backup as $name => $values) {
            unset($_ENV[$name], $_SERVER[$name]);
            if ($values['env'] !== null) {
                $_ENV[$name] = $values['env'];
            }
            if ($values['server'] !== null) {
                $_SERVER[$name] = $values['server'];
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

    public function testRelativeCachePathWithoutTrailingSlashIsResolved(): void
    {
        $_ENV['CACHE_PATH'] = 'var/cache';
        $kernel = $this->kernel();

        self::assertSame($kernel->getProjectDir() . '/var/cache/test', $kernel->getCacheDir());
    }

    public function testRelativeCachePathIsCanonicalized(): void
    {
        $_ENV['CACHE_PATH'] = './var/../var/cache/';
        $kernel = $this->kernel();

        self::assertSame($kernel->getProjectDir() . '/var/cache/test', $kernel->getCacheDir());
    }

    public function testServerVariableTakesPrecedenceOverEnv(): void
    {
        $_SERVER['CACHE_PATH'] = '/tmp/server-cache';
        $_ENV['CACHE_PATH'] = '/tmp/env-cache';

        self::assertSame('/tmp/server-cache/test', $this->kernel()->getCacheDir());
    }

    public function testServerOnlyVariableIsUsed(): void
    {
        $_SERVER['LOG_PATH'] = '/tmp/server-log';

        self::assertSame('/tmp/server-log', $this->kernel()->getLogDir());
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
