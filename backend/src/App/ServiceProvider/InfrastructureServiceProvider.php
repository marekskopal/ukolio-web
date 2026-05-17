<?php

declare(strict_types=1);

namespace Ukolio\App\ServiceProvider;

use Http\Discovery\Psr17FactoryDiscovery;
use League\Container\ServiceProvider\AbstractServiceProvider;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Log\LoggerInterface;
use Ukolio\Service\Logger\Logger;

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

		$container->add(LoggerInterface::class, fn (): LoggerInterface => Logger::initLogger(__DIR__ . '/../../../log'));

		$container->add(
			ResponseFactoryInterface::class,
			fn (): ResponseFactoryInterface => Psr17FactoryDiscovery::findResponseFactory(),
		);
	}
}
