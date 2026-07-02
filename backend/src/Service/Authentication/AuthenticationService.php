<?php

declare(strict_types=1);

namespace Ukolio\Service\Authentication;

use Firebase\JWT\JWT;
use Ukolio\Dto\AuthenticationDto;
use Ukolio\Dto\CredentialsDto;
use Ukolio\Model\Entity\User;
use Ukolio\Service\Authentication\Exception\AuthenticationException;
use Ukolio\Service\Provider\UserProviderInterface;

final readonly class AuthenticationService implements AuthenticationServiceInterface
{
	private const int AccessTokenExpiration = 3600;
	private const int RefreshTokenExpiration = 604800;

	public function __construct(private UserProviderInterface $userProvider, private LoginAttemptService $loginAttempts)
	{
	}

	public function authenticate(CredentialsDto $credentials): AuthenticationDto
	{
		$user = $this->userProvider->getUserByEmail($credentials->email);
		if ($user === null) {
			throw new AuthenticationException('Invalid credentials.');
		}

		$this->loginAttempts->assertNotLocked($user);

		if ($user->password === null || !password_verify($credentials->password, $user->password)) {
			$this->loginAttempts->recordFailure($user);
			// If this failure just tripped the lock, surface that to the caller as 429.
			$this->loginAttempts->assertNotLocked($user);

			throw new AuthenticationException('Invalid credentials.');
		}

		$this->loginAttempts->recordSuccess($user);

		return $this->createAuthentication($user);
	}

	public function createAuthentication(User $user): AuthenticationDto
	{
		$accessTokenExpiration = time() + self::AccessTokenExpiration;
		$refreshTokenExpiration = time() + self::RefreshTokenExpiration;

		return new AuthenticationDto(
			accessToken: $this->createToken(['id' => $user->id, 'tv' => $user->tokenVersion, 'exp' => $accessTokenExpiration]),
			refreshToken: $this->createToken(['id' => $user->id, 'tv' => $user->tokenVersion, 'exp' => $refreshTokenExpiration]),
			userId: $user->id,
		);
	}

	/** @param array<string,mixed> $claims */
	private function createToken(array $claims): string
	{
		$key = (string) getenv('AUTHORIZATION_TOKEN_KEY');

		return JWT::encode($claims, $key, self::TokenAlgorithm);
	}
}
