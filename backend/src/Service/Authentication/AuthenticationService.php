<?php

declare(strict_types=1);

namespace TaskManager\Service\Authentication;

use Firebase\JWT\JWT;
use TaskManager\Dto\AuthenticationDto;
use TaskManager\Dto\CredentialsDto;
use TaskManager\Model\Entity\User;
use TaskManager\Service\Authentication\Exception\AuthenticationException;
use TaskManager\Service\Provider\UserProviderInterface;

final readonly class AuthenticationService implements AuthenticationServiceInterface
{
	private const int AccessTokenExpiration = 3600;
	private const int RefreshTokenExpiration = 604800;

	public function __construct(private UserProviderInterface $userProvider)
	{
	}

	public function authenticate(CredentialsDto $credentials): AuthenticationDto
	{
		$user = $this->userProvider->getUserByEmail($credentials->email);
		if ($user === null) {
			throw new AuthenticationException('Invalid credentials.');
		}

		if (!password_verify($credentials->password, $user->password)) {
			throw new AuthenticationException('Invalid credentials.');
		}

		return $this->createAuthentication($user);
	}

	public function createAuthentication(User $user): AuthenticationDto
	{
		$accessTokenExpiration = time() + self::AccessTokenExpiration;
		$refreshTokenExpiration = time() + self::RefreshTokenExpiration;

		return new AuthenticationDto(
			accessToken: $this->createToken(['id' => $user->id, 'exp' => $accessTokenExpiration]),
			refreshToken: $this->createToken(['id' => $user->id, 'exp' => $refreshTokenExpiration]),
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
