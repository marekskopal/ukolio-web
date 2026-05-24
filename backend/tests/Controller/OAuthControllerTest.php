<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Ukolio\Controller\OAuthController;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(OAuthController::class)]
final class OAuthControllerTest extends IntegrationTestCase
{
	public function testFullPkceAuthorizeAndTokenHttpFlow(): void
	{
		$user = Fixture::createUser();

		// 1. Register a client.
		$register = $this->request(
			'POST',
			'/mcp/oauth/register',
			body: ['client_name' => 'Test Client', 'redirect_uris' => ['http://localhost/cb']],
		);
		self::assertSame(201, $register->getStatusCode());
		$clientId = self::stringField($this->jsonBody($register)['client_id']);

		// 2. Authorize as the user (PKCE S256 challenge).
		[$verifier, $challenge] = $this->pkcePair();

		$authorize = $this->request(
			'POST',
			'/mcp/oauth/authorize',
			body: [
				'clientId' => $clientId,
				'redirectUri' => 'http://localhost/cb',
				'codeChallenge' => $challenge,
				'codeChallengeMethod' => 'S256',
				'state' => 'state-token',
			],
			authenticatedAs: $user,
		);
		self::assertSame(200, $authorize->getStatusCode());
		$code = self::stringField($this->jsonBody($authorize)['code']);

		// 3. Exchange the code for tokens via the form-encoded token endpoint.
		$token = $this->postFormToken([
			'grant_type' => 'authorization_code',
			'code' => $code,
			'code_verifier' => $verifier,
			'client_id' => $clientId,
			'redirect_uri' => 'http://localhost/cb',
		]);

		self::assertSame(200, $token->getStatusCode());
		$tokenBody = $this->jsonBody($token);
		self::assertArrayHasKey('access_token', $tokenBody);
		self::assertArrayHasKey('refresh_token', $tokenBody);
		self::assertSame(3600, $tokenBody['expires_in']);
	}

	public function testTokenExchangeRejectsWrongPkceVerifier(): void
	{
		$user = Fixture::createUser();

		$register = $this->request(
			'POST',
			'/mcp/oauth/register',
			body: ['client_name' => 'Test Client', 'redirect_uris' => ['http://localhost/cb']],
		);
		$clientId = self::stringField($this->jsonBody($register)['client_id']);

		[, $challenge] = $this->pkcePair();

		$authorize = $this->request(
			'POST',
			'/mcp/oauth/authorize',
			body: [
				'clientId' => $clientId,
				'redirectUri' => 'http://localhost/cb',
				'codeChallenge' => $challenge,
				'codeChallengeMethod' => 'S256',
			],
			authenticatedAs: $user,
		);
		$code = self::stringField($this->jsonBody($authorize)['code']);

		$token = $this->postFormToken([
			'grant_type' => 'authorization_code',
			'code' => $code,
			'code_verifier' => 'wrong-verifier-value',
			'client_id' => $clientId,
			'redirect_uri' => 'http://localhost/cb',
		]);

		self::assertSame(400, $token->getStatusCode());
		$body = $this->jsonBody($token);
		self::assertSame('invalid_grant', $body['error']);
	}

	/** @return array{0:string,1:string} [verifier, S256 challenge] */
	private function pkcePair(): array
	{
		$verifier = bin2hex(random_bytes(32));
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
		return [$verifier, $challenge];
	}

	/**
	 * The token endpoint reads form-encoded bodies via getParsedBody(), so the JSON-based
	 * IntegrationTestCase::request helper would not populate the fields. Bypass it.
	 *
	 * @param array<string, string> $form
	 */
	private function postFormToken(array $form): ResponseInterface
	{
		$request = new ServerRequest([], [], '/mcp/oauth/token', 'POST');
		$request = $request
			->withHeader('Content-Type', 'application/x-www-form-urlencoded')
			->withParsedBody($form);

		return $this->handler->handle($request);
	}
}
