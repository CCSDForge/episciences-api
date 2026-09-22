<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\Filesystem\Path;
use Symfony\Component\HttpKernel\Kernel as BaseKernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

class Kernel extends BaseKernel
{
    use MicroKernelTrait;

    protected function configureContainer(ContainerConfigurator $container): void
    {

        $appVersion = null;

        $container->import('../config/{packages}/*.yaml');
        $container->import('../config/{packages}/' . $this->environment . '/*.yaml');

        if (is_file(\dirname(__DIR__) . '/config/services.yaml')) {
            $container->import('../config/services.yaml');
            $container->import('../config/{services}_' . $this->environment . '.yaml');
        } elseif (is_file($path = \dirname(__DIR__) . '/config/services.php')) {
            (require $path)($container->withPath($path), $this);
        }

        $appVersion = '1.0.0';
        if (is_file($path = \dirname(__DIR__) . '/version.php') && is_readable($path)) {
            include($path);
        }

        $container->parameters()->set('git_application_version', $appVersion);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('../config/{routes}/' . $this->environment . '/*.yaml');
        $routes->import('../config/{routes}/*.yaml');

        if (is_file(\dirname(__DIR__) . '/config/routes.yaml')) {
            $routes->import('../config/routes.yaml');
        } elseif (is_file($path = \dirname(__DIR__) . '/config/routes.php')) {
            (require $path)($routes->withPath($path), $this);
        }
    }


    #[\Override]
    public function getCacheDir(): string
    {
        $cachePath = $this->resolveEnvPath('CACHE_PATH');

        return $cachePath !== null ? $cachePath . '/' . $this->environment : parent::getCacheDir();
    }

    #[\Override]
    public function getLogDir(): string
    {
        return $this->resolveEnvPath('LOG_PATH') ?? parent::getLogDir();
    }

    /**
     * Relative paths are resolved against the project directory, not the current working directory
     * (e.g. PHP-FPM runs from public/, bin/console from the project root).
     * The returned path is canonical, without trailing slash.
     */
    private function resolveEnvPath(string $name): ?string
    {
        $path = $_SERVER[$name] ?? $_ENV[$name] ?? null;

        if (!is_string($path) || $path === '') {
            return null;
        }

        return Path::makeAbsolute($path, $this->getProjectDir());
    }
}
