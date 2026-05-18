<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Mcp\Server\Transport\StreamableHttpTransport;
use Predis\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Mcp\Server\UkolioServer;
use Ukolio\Mcp\Session\RedisSessionStore;
use Ukolio\OAuth\AuthorizationServiceInterface;
use Ukolio\OAuth\ClientServiceInterface;
use Ukolio\Response\ErrorResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Actor\ActorContextInterface;

final readonly class McpController
{
	public function __construct(
		private AuthorizationServiceInterface $authorizationService,
		private ClientServiceInterface $clientService,
		private McpUserContextInterface $userContext,
		private ActorContextInterface $actorContext,
		private UkolioServer $server,
		private ClientInterface $redis,
	) {
	}

	#[RouteGet(Routes::Mcp->value)]
	public function actionGetMcp(ServerRequestInterface $request): ResponseInterface
	{
		return $this->handleMcp($request);
	}

	#[RoutePost(Routes::Mcp->value)]
	public function actionPostMcp(ServerRequestInterface $request): ResponseInterface
	{
		return $this->handleMcp($request);
	}

	#[RouteDelete(Routes::Mcp->value)]
	public function actionDeleteMcp(ServerRequestInterface $request): ResponseInterface
	{
		return $this->handleMcp($request);
	}

	private function handleMcp(ServerRequestInterface $request): ResponseInterface
	{
		$token = $this->extractBearerToken($request);
		if ($token === null) {
			return $this->unauthorized($request, 'Missing or invalid Authorization header. Expected: Bearer <access_token>');
		}

		try {
			$authorization = $this->authorizationService->validateAccessToken($token);
		} catch (RuntimeException) {
			return $this->unauthorized($request, 'Invalid or expired access token.');
		}

		$this->userContext->setUser($authorization->user);

		$clientName = $authorization->clientId;
		$client = $this->clientService->findByClientId($authorization->clientId);
		if ($client !== null) {
			$clientName = $client->clientName;
		}
		$this->actorContext->setAgent($authorization->clientId, $clientName);

		$sessionStore = new RedisSessionStore($this->redis, $this->sessionTtl());
		$mcpServer = $this->server->build($sessionStore);
		$transport = new StreamableHttpTransport($request);

		return $mcpServer->run($transport);
	}

	private function extractBearerToken(ServerRequestInterface $request): ?string
	{
		$header = $request->getHeaderLine('Authorization');
		if ($header === '' || !str_starts_with($header, 'Bearer ')) {
			return null;
		}

		$token = substr($header, 7);

		return $token !== '' ? $token : null;
	}

	private function unauthorized(ServerRequestInterface $request, string $message): ErrorResponse
	{
		$scheme = $request->getHeaderLine('X-Forwarded-Proto');
		if ($scheme === '') {
			$scheme = $request->getUri()->getScheme();
		}
		$host = $request->getHeaderLine('X-Forwarded-Host');
		if ($host === '') {
			$host = $request->getHeaderLine('Host');
		}
		if ($host === '') {
			$host = $request->getUri()->getAuthority();
		}
		$baseUrl = $scheme . '://' . $host;

		return new ErrorResponse($message, 401, [
			'WWW-Authenticate' => 'Bearer resource_metadata="' . $baseUrl . Routes::OAuthResourceMetadata->value . '"',
		]);
	}

	private function sessionTtl(): int
	{
		$ttl = (int) getenv('MCP_SESSION_TTL');

		return $ttl > 0 ? $ttl : 86400;
	}
}
