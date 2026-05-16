<?php

declare(strict_types=1);

namespace TaskManager\Controller;

use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use Mcp\Server\Session\FileSessionStore;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use TaskManager\Mcp\McpUserContextInterface;
use TaskManager\Mcp\Server\TaskManagerServer;
use TaskManager\OAuth\AuthorizationServiceInterface;
use TaskManager\Response\ErrorResponse;
use TaskManager\Route\Routes;

final readonly class McpController
{
	public function __construct(
		private AuthorizationServiceInterface $authorizationService,
		private McpUserContextInterface $userContext,
		private TaskManagerServer $server,
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
			$user = $this->authorizationService->validateAccessToken($token);
		} catch (RuntimeException) {
			return $this->unauthorized($request, 'Invalid or expired access token.');
		}

		$this->userContext->setUser($user);

		$sessionStore = new FileSessionStore($this->sessionDirectory());
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

	private function sessionDirectory(): string
	{
		$dir = (string) getenv('MCP_SESSION_DIR');
		if ($dir === '') {
			$dir = sys_get_temp_dir() . '/task-manager-mcp-sessions';
		}

		return $dir;
	}
}
