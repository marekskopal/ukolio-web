<?php

declare(strict_types=1);

namespace Ukolio\Tests\Support;

use Firebase\JWT\JWT;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Model\Entity\Enum\SystemRoleEnum;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Service\Authentication\AuthenticationServiceInterface;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\UserProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final class Fixture
{
	public static int $userCounter = 0;

	public static function reset(): void
	{
		self::$userCounter = 0;
	}

	public static function createUser(
		?string $email = null,
		string $password = 'TestPass1!',
		string $name = 'Test User',
		LocaleEnum $locale = LocaleEnum::En,
		SystemRoleEnum $systemRole = SystemRoleEnum::User,
		bool $emailVerified = true,
	): User {
		self::$userCounter++;
		$email ??= 'user' . self::$userCounter . '@example.com';

		$userProvider = AppHarness::container()->get(UserProviderInterface::class);
		assert($userProvider instanceof UserProviderInterface);

		$user = $userProvider->createUser($email, $password, $name, $locale);

		if ($systemRole !== SystemRoleEnum::User || $emailVerified) {
			$user->systemRole = $systemRole;
			$user->emailVerified = $emailVerified;
			$repository = AppHarness::container()->get(UserRepository::class);
			assert($repository instanceof UserRepository);
			$repository->persist($user);
		}

		return $user;
	}

	public static function createWorkspace(User $owner, string $name = 'Test Workspace'): Workspace
	{
		$provider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($provider instanceof WorkspaceProviderInterface);
		return $provider->createWorkspace($owner, $name);
	}

	public static function addMember(Workspace $workspace, User $user, WorkspaceRoleEnum $role): void
	{
		$provider = AppHarness::container()->get(WorkspaceProviderInterface::class);
		assert($provider instanceof WorkspaceProviderInterface);
		$provider->addMember($workspace, $user, $role);
	}

	public static function createProject(User $author, Workspace $workspace, string $name = 'Test Project'): Project
	{
		$provider = AppHarness::container()->get(ProjectProviderInterface::class);
		assert($provider instanceof ProjectProviderInterface);
		return $provider->createProject($author, $workspace, $name, null);
	}

	public static function accessTokenFor(User $user): string
	{
		$key = (string) getenv('AUTHORIZATION_TOKEN_KEY');
		return JWT::encode(
			['id' => $user->id, 'exp' => time() + 3600],
			$key,
			AuthenticationServiceInterface::TokenAlgorithm,
		);
	}

	public static function expiredAccessTokenFor(User $user): string
	{
		$key = (string) getenv('AUTHORIZATION_TOKEN_KEY');
		return JWT::encode(
			['id' => $user->id, 'exp' => time() - 60],
			$key,
			AuthenticationServiceInterface::TokenAlgorithm,
		);
	}
}
