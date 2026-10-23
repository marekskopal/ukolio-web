<?php

declare(strict_types=1);

namespace Ukolio\Tests\Middleware;

use Laminas\Diactoros\Response\TextResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Ukolio\Middleware\AuthorizationMiddleware;
use Ukolio\Middleware\Exception\NotAuthorizedException;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(AuthorizationMiddleware::class)]
final class AuthorizationMiddlewareTest extends IntegrationTestCase
{
	public function testAvatarRouteIsOpen(): void
	{
		$response = $this->middleware()->process(new ServerRequest([], [], '/api/avatars/12/0a1b2c', 'GET'), $this->okHandler());

		self::assertSame(200, $response->getStatusCode());
	}

	/** @return iterable<string, array{string}> */
	public static function nonAvatarPaths(): iterable
	{
		yield 'extra segment' => ['/api/avatars/12/0a1b2c/extra'];
		yield 'listing' => ['/api/avatars/'];
		yield 'non-numeric user' => ['/api/avatars/list/0a1b2c'];
	}

	#[DataProvider('nonAvatarPaths')]
	public function testOtherPathsUnderAvatarPrefixRequireAuth(string $path): void
	{
		$this->expectException(NotAuthorizedException::class);

		$this->middleware()->process(new ServerRequest([], [], $path, 'GET'), $this->okHandler());
	}

	private function middleware(): AuthorizationMiddleware
	{
		$middleware = AppHarness::container()->get(AuthorizationMiddleware::class);
		assert($middleware instanceof AuthorizationMiddleware);

		return $middleware;
	}

	private function okHandler(): RequestHandlerInterface
	{
		return new class implements RequestHandlerInterface {
			public function handle(ServerRequestInterface $request): ResponseInterface
			{
				return new TextResponse('ok');
			}
		};
	}
}
