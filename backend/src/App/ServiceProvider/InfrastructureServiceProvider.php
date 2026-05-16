<?php

declare(strict_types=1);

namespace TaskManager\App\ServiceProvider;

use Http\Discovery\Psr17FactoryDiscovery;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use TaskManager\Service\Logger\StderrLogger;

final class InfrastructureServiceProvider extends AbstractServiceProvider
{
    public function provides(string $id): bool
    {
        return in_array($id, [
            LoggerInterface::class,
            ResponseFactoryInterface::class,
        ], true);
    }

    public function register(): void
    {
        $container = $this->getContainer();

        $container->add(LoggerInterface::class, fn (): LoggerInterface => new StderrLogger());

        $container->add(
            ResponseFactoryInterface::class,
            fn (): ResponseFactoryInterface => Psr17FactoryDiscovery::findResponseFactory(),
        );
    }
}
