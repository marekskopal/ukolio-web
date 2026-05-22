<?php

declare(strict_types=1);

namespace Ukolio\Tests\OAuth;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\OAuth\AuthorizationService;
use Ukolio\OAuth\AuthorizationServiceInterface;
use Ukolio\OAuth\ClientServiceInterface;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(AuthorizationService::class)]
final class AuthorizationServiceTest extends IntegrationTestCase
{
	public function testFullPkceCodeExchangeFlow(): void
	{
		$user = Fixture::createUser();

		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);
		$client = $clientService->registerClient('Test Client', ['http://localhost/cb']);

		$authService = $this->container->get(AuthorizationServiceInterface::class);
		assert($authService instanceof AuthorizationServiceInterface);

		$verifier = 'super-secret-pkce-verifier-value';
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

		$code = $authService->createAuthorizationCode(
			clientId: $client->clientId,
			userId: $user->id,
			codeChallenge: $challenge,
			codeChallengeMethod: 'S256',
			redirectUri: 'http://localhost/cb',
		);

		$pair = $authService->exchangeCode(
			code: $code,
			codeVerifier: $verifier,
			clientId: $client->clientId,
			redirectUri: 'http://localhost/cb',
		);

		self::assertNotEmpty($pair->accessToken);
		self::assertNotEmpty($pair->refreshToken);
		self::assertSame(3600, $pair->expiresIn);
	}

	public function testCodeCannotBeExchangedTwice(): void
	{
		$user = Fixture::createUser();
		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);
		$client = $clientService->registerClient('Test', ['http://localhost/cb']);

		$authService = $this->container->get(AuthorizationServiceInterface::class);
		assert($authService instanceof AuthorizationServiceInterface);

		$verifier = 'verifier-abc';
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

		$code = $authService->createAuthorizationCode(
			clientId: $client->clientId,
			userId: $user->id,
			codeChallenge: $challenge,
			codeChallengeMethod: 'S256',
			redirectUri: 'http://localhost/cb',
		);

		$authService->exchangeCode($code, $verifier, $client->clientId, 'http://localhost/cb');

		$this->expectException(\RuntimeException::class);
		$authService->exchangeCode($code, $verifier, $client->clientId, 'http://localhost/cb');
	}

	public function testWrongPkceVerifierFails(): void
	{
		$user = Fixture::createUser();
		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);
		$client = $clientService->registerClient('Test', ['http://localhost/cb']);

		$authService = $this->container->get(AuthorizationServiceInterface::class);
		assert($authService instanceof AuthorizationServiceInterface);

		$verifier = 'real-verifier';
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

		$code = $authService->createAuthorizationCode($client->clientId, $user->id, $challenge, 'S256', 'http://localhost/cb');

		$this->expectException(\RuntimeException::class);
		$authService->exchangeCode($code, 'wrong-verifier', $client->clientId, 'http://localhost/cb');
	}

	public function testRefreshTokenIssuesNewPairAndRevokesOld(): void
	{
		$user = Fixture::createUser();
		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);
		$client = $clientService->registerClient('Test', ['http://localhost/cb']);

		$authService = $this->container->get(AuthorizationServiceInterface::class);
		assert($authService instanceof AuthorizationServiceInterface);

		$verifier = 'verifier-refresh';
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
		$code = $authService->createAuthorizationCode($client->clientId, $user->id, $challenge, 'S256', 'http://localhost/cb');
		$pair = $authService->exchangeCode($code, $verifier, $client->clientId, 'http://localhost/cb');

		$refreshed = $authService->refreshToken($pair->refreshToken, $client->clientId);
		self::assertNotSame($pair->accessToken, $refreshed->accessToken);
		self::assertNotSame($pair->refreshToken, $refreshed->refreshToken);

		// Old refresh token is now revoked
		$this->expectException(\RuntimeException::class);
		$authService->refreshToken($pair->refreshToken, $client->clientId);
	}

	public function testValidateAccessTokenReturnsAuthorization(): void
	{
		$user = Fixture::createUser();
		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);
		$client = $clientService->registerClient('Test', ['http://localhost/cb']);

		$authService = $this->container->get(AuthorizationServiceInterface::class);
		assert($authService instanceof AuthorizationServiceInterface);

		$verifier = 'verifier';
		$challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');
		$code = $authService->createAuthorizationCode($client->clientId, $user->id, $challenge, 'S256', 'http://localhost/cb');
		$pair = $authService->exchangeCode($code, $verifier, $client->clientId, 'http://localhost/cb');

		$auth = $authService->validateAccessToken($pair->accessToken);
		self::assertSame($user->id, $auth->user->id);
	}

	public function testClientServiceRedirectUriMatchingForLocalhostIsLenient(): void
	{
		$clientService = $this->container->get(ClientServiceInterface::class);
		assert($clientService instanceof ClientServiceInterface);
		$client = $clientService->registerClient('LocalDev', ['http://localhost:9999/cb']);

		// Any port on localhost matches for OAuth 2.1 native-app callback
		self::assertTrue($clientService->validateRedirectUri($client->clientId, 'http://localhost:51234/cb'));
		self::assertFalse($clientService->validateRedirectUri($client->clientId, 'http://evil.example/cb'));
		self::assertFalse($clientService->validateRedirectUri('unknown', 'http://localhost/cb'));
	}
}
