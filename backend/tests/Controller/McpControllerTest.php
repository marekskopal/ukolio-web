<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use Laminas\Diactoros\ServerRequest;
use Laminas\Diactoros\Stream;
use Nette\Utils\Json;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use stdClass;
use Ukolio\Controller\McpController;
use Ukolio\Model\Entity\User;
use Ukolio\OAuth\AuthorizationServiceInterface;
use Ukolio\OAuth\ClientServiceInterface;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

/**
 * Drives the Streamable HTTP transport end-to-end (OAuth bearer → session store → tool call), the
 * layer the per-tool tests skip by calling tool classes directly.
 */
#[CoversClass(McpController::class)]
final class McpControllerTest extends IntegrationTestCase
{
	private const string RedirectUri = 'http://localhost/cb';

	public function testInitializeThenCallToolWithinSession(): void
	{
		$user = Fixture::createUser();
		Fixture::createProject($user, Fixture::createWorkspace($user), 'Transport Project');
		$token = $this->mcpAccessTokenFor($user);

		$sessionId = $this->initialize($token);

		$response = $this->mcp($token, [
			'jsonrpc' => '2.0',
			'id' => 2,
			'method' => 'tools/call',
			'params' => ['name' => 'list_projects', 'arguments' => new stdClass()],
		], $sessionId);

		self::assertSame(200, $response->getStatusCode());
		$message = $this->decodeMessage($response);
		self::assertArrayNotHasKey('error', $message);
		self::assertStringContainsString('Transport Project', Json::encode($message['result'] ?? null));
	}

	public function testSessionIsNotServedToAnotherUser(): void
	{
		$owner = Fixture::createUser('owner@example.com');
		$intruder = Fixture::createUser('intruder@example.com');

		$sessionId = $this->initialize($this->mcpAccessTokenFor($owner));

		$response = $this->mcp($this->mcpAccessTokenFor($intruder), [
			'jsonrpc' => '2.0',
			'id' => 2,
			'method' => 'tools/list',
		], $sessionId);

		self::assertSame(404, $response->getStatusCode());
	}

	public function testMissingBearerIsChallenged(): void
	{
		$response = $this->mcp(null, ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list']);

		self::assertSame(401, $response->getStatusCode());
		self::assertStringContainsString('resource_metadata=', $response->getHeaderLine('WWW-Authenticate'));
	}

	private function initialize(string $token): string
	{
		$response = $this->mcp($token, [
			'jsonrpc' => '2.0',
			'id' => 1,
			'method' => 'initialize',
			'params' => [
				'protocolVersion' => '2025-06-18',
				'capabilities' => new stdClass(),
				'clientInfo' => ['name' => 'phpunit', 'version' => '1.0'],
			],
		]);

		self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$sessionId = $response->getHeaderLine('Mcp-Session-Id');
		self::assertNotSame('', $sessionId, 'initialize must open a session (Redis session store is configured)');

		$this->mcp($token, ['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $sessionId);

		return $sessionId;
	}

	/** @param array<string, mixed> $message */
	private function mcp(?string $token, array $message, ?string $sessionId = null): ResponseInterface
	{
		$stream = new Stream('php://temp', 'r+');
		$stream->write(Json::encode($message));
		$stream->rewind();

		$request = (new ServerRequest([], [], 'http://localhost/mcp', 'POST'))
			->withBody($stream)
			->withHeader('Host', 'localhost')
			->withHeader('Content-Type', 'application/json')
			->withHeader('Accept', 'application/json, text/event-stream');

		if ($token !== null) {
			$request = $request->withHeader('Authorization', 'Bearer ' . $token);
		}
		if ($sessionId !== null) {
			$request = $request
				->withHeader('Mcp-Session-Id', $sessionId)
				->withHeader('MCP-Protocol-Version', '2025-06-18');
		}

		return $this->handler->handle($request);
	}

	/**
	 * The transport answers either plain JSON or a single-event SSE stream.
	 *
	 * @return array<string, mixed>
	 */
	private function decodeMessage(ResponseInterface $response): array
	{
		$body = (string) $response->getBody();
		if (preg_match('/^data: (.+)$/m', $body, $matches) === 1) {
			$body = $matches[1];
		}

		/** @var array<string, mixed> $decoded */
		$decoded = Json::decode($body, forceArrays: true);

		return $decoded;
	}

	private function mcpAccessTokenFor(User $user): string
	{
		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);
		$client = $clientService->registerClient('Test Client', [self::RedirectUri]);

		$authService = $this->container->get(AuthorizationServiceInterface::class);
		assert($authService instanceof AuthorizationServiceInterface);

		$verifier = bin2hex(random_bytes(32));
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

		$code = $authService->createAuthorizationCode(
			clientId: $client->clientId,
			userId: $user->id,
			codeChallenge: $challenge,
			codeChallengeMethod: 'S256',
			redirectUri: self::RedirectUri,
		);

		return $authService->exchangeCode($code, $verifier, $client->clientId, self::RedirectUri)->accessToken;
	}
}
