<?php

namespace App;

use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
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

        if (is_file($path = \dirname(__DIR__) . '/version.php') && is_readable($path)) {
            include($path);
        }

        $container->parameters()->set('git_application_version', $appVersion ?? '1.0.0');
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


    public function getCacheDir(): string
    {
        $cachePath = $_ENV['CACHE_PATH'] ?? null;
        if (is_string($cachePath) && $cachePath !== '') {
            // Chemins relatifs : préfixés par le dossier du projet (ex: "var/cache")
            // Chemins absolus : utilisés tels quels (ex: "/data/cache")
            if (!str_starts_with($cachePath, '/')) {
                $cachePath = $this->getProjectDir() . DIRECTORY_SEPARATOR . $cachePath;
            }

            return rtrim($cachePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $this->environment;
        }

        return $this->getProjectDir() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . $this->environment;
    }

    public function getLogDir(): string
    {
        $logPath = $_ENV['LOG_PATH'] ?? null;
        if (is_string($logPath) && $logPath !== '') {
            // Chemins relatifs : préfixés par le dossier du projet (ex: "var/log")
            // Chemins absolus : utilisés tels quels (ex: "/data/log")
            if (!str_starts_with($logPath, '/')) {
                $logPath = $this->getProjectDir() . DIRECTORY_SEPARATOR . $logPath;
            }

            return rtrim($logPath, DIRECTORY_SEPARATOR);
        }

        return $this->getProjectDir() . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'log';
    }
}
