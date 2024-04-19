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
        $appVersion = '1.0.0';

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
            $container->parameters()->set('git_application_version', $appVersion ?? '1.0.0');
        }
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
        return isset($_ENV['CACHE_PATH']) &&
        is_string($_ENV['CACHE_PATH']) &&
        $_ENV['CACHE_PATH'] !== '' ?
            $_ENV['CACHE_PATH'] . $this->environment : parent::getCacheDir();
    }

    public function getLogDir(): string
    {

        return isset($_ENV['LOG_PATH']) &&
        is_string($_ENV['LOG_PATH']) &&
        $_ENV['LOG_PATH'] !== '' ?
            $_ENV['LOG_PATH'] : parent::getLogDir();


    }
}
