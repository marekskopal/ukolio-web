<?php

declare(strict_types=1);

namespace Ukolio\App;

use League\Container\Container;
use League\Container\ReflectionContainer;
use MarekSkopal\Router\Builder\RouterBuilder;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ukolio\App\ServiceProvider\AuthenticationServiceProvider;
use Ukolio\App\ServiceProvider\DomainServiceProvider;
use Ukolio\App\ServiceProvider\InfrastructureServiceProvider;
use Ukolio\App\ServiceProvider\OrmServiceProvider;
use Ukolio\App\ServiceProvider\RealtimeServiceProvider;
use Ukolio\Middleware\AuthorizationMiddleware;
use Ukolio\Route\Strategy\JsonStrategy;
use Ukolio\Service\Dbal\DbContext;

final readonly class ApplicationFactory
{
	private const int AuthorizationTokenKeyMinLength = 32;

	public static function create(): Application
	{
		self::validateEnvironment();

		$dbContext = self::initializeDbContext();
		$container = self::initializeContainer($dbContext);
		$requestHandler = self::initializeRequestHandler($container);

		return new Application($container, $requestHandler, $dbContext);
	}

	private static function validateEnvironment(): void
	{
		$required = [
			'AUTHORIZATION_TOKEN_KEY',
			'MYSQL_HOST',
			'MYSQL_DATABASE',
			'MYSQL_USER',
			'MYSQL_PASSWORD',
			'S3_BUCKET',
			'S3_ACCESS_KEY',
			'S3_SECRET_KEY',
			'REDIS_HOST',
			'REDIS_PORT',
			'REDIS_PASSWORD',
			'MEMCACHED_HOST',
			'MEMCACHED_PORT',
		];

		$missing = [];
		foreach ($required as $var) {
			$value = getenv($var);
			if ($value === false || $value === '') {
				$missing[] = $var;
			}
		}

		if ($missing !== []) {
			throw new \RuntimeException('Required environment variables are not set: ' . implode(', ', $missing));
		}

		$key = (string) getenv('AUTHORIZATION_TOKEN_KEY');
		if (strlen($key) < self::AuthorizationTokenKeyMinLength) {
			throw new \RuntimeException(sprintf(
				'AUTHORIZATION_TOKEN_KEY must be at least %d characters. Generate one with `openssl rand -hex 32`.',
				self::AuthorizationTokenKeyMinLength,
			));
		}
	}

	private static function initializeContainer(DbContext $dbContext): ContainerInterface
	{
		$container = new Container();
		$container->defaultToShared();
		$container->delegate(new ReflectionContainer(true));

		$container->addServiceProvider(new InfrastructureServiceProvider());
		$container->addServiceProvider(new OrmServiceProvider($dbContext));
		$container->addServiceProvider(new AuthenticationServiceProvider());
		$container->addServiceProvider(new RealtimeServiceProvider());
		$container->addServiceProvider(new DomainServiceProvider());

		return $container;
	}

	private static function initializeRequestHandler(ContainerInterface $container): RequestHandlerInterface
	{
		$strategy = $container->get(JsonStrategy::class);
		if (!$strategy instanceof JsonStrategy) {
			throw new \RuntimeException('JsonStrategy not found in container.');
		}
		$strategy->setContainer($container);

		$router = (new RouterBuilder())
			->setClassDirectories([__DIR__ . '/../Controller'])
			->build();

		$router->setStrategy($strategy);

		$authorizationMiddleware = $container->get(AuthorizationMiddleware::class);
		if (!$authorizationMiddleware instanceof AuthorizationMiddleware) {
			throw new \RuntimeException('AuthorizationMiddleware not found in container.');
		}
		$router->middleware($authorizationMiddleware);

		return $router;
	}

	private static function initializeDbContext(): DbContext
	{
		/** @var non-empty-string $host */
		$host = (string) getenv('MYSQL_HOST');
		/** @var non-empty-string $database */
		$database = (string) getenv('MYSQL_DATABASE');
		/** @var non-empty-string $user */
		$user = (string) getenv('MYSQL_USER');
		/** @var non-empty-string $password */
		$password = (string) getenv('MYSQL_PASSWORD');

		return new DbContext($host, $database, $user, $password);
	}
}
