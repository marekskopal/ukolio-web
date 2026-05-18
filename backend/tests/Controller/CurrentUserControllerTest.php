<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use PHPUnit\Framework\Attributes\CoversClass;
use Ukolio\Controller\CurrentUserController;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;

#[CoversClass(CurrentUserController::class)]
final class CurrentUserControllerTest extends IntegrationTestCase
{
	public function testGetCurrentUserReturnsAuthenticatedProfile(): void
	{
		$user = Fixture::createUser(email: 'me@example.com', name: 'Me');

		$response = $this->request('GET', '/api/current-user', authenticatedAs: $user);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertSame('me@example.com', $body['email']);
		self::assertSame('Me', $body['name']);
	}

	public function testPatchUpdatesNameAndLocale(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'PATCH',
			'/api/current-user',
			body: ['name' => 'Renamed', 'locale' => 'cs'],
			authenticatedAs: $user,
		);

		self::assertSame(200, $response->getStatusCode());
		$body = $this->jsonBody($response);
		self::assertSame('Renamed', $body['name']);
		self::assertSame('cs', $body['locale']);
	}

	public function testPatchRejectsUnknownLocale(): void
	{
		$user = Fixture::createUser();

		$response = $this->request(
			'PATCH',
			'/api/current-user',
			body: ['locale' => 'klingon'],
			authenticatedAs: $user,
		);

		self::assertSame(422, $response->getStatusCode());
	}

	public function testChangePasswordRequiresCorrectCurrentPassword(): void
	{
		$user = Fixture::createUser(password: 'OldPass11');

		$bad = $this->request(
			'POST',
			'/api/current-user/password',
			body: ['currentPassword' => 'WrongPass1', 'newPassword' => 'NewPass11'],
			authenticatedAs: $user,
		);
		self::assertSame(401, $bad->getStatusCode());

		$ok = $this->request(
			'POST',
			'/api/current-user/password',
			body: ['currentPassword' => 'OldPass11', 'newPassword' => 'NewPass22'],
			authenticatedAs: $user,
		);
		self::assertSame(200, $ok->getStatusCode());

		// Confirm new password authenticates
		$login = $this->request('POST', '/api/authentication/login', [
			'email' => $user->email,
			'password' => 'NewPass22',
		]);
		self::assertSame(200, $login->getStatusCode());
	}

	public function testChangePasswordRejectsWeakPassword(): void
	{
		$user = Fixture::createUser(password: 'OldPass11');

		$response = $this->request(
			'POST',
			'/api/current-user/password',
			body: ['currentPassword' => 'OldPass11', 'newPassword' => 'weak'],
			authenticatedAs: $user,
		);
		self::assertSame(422, $response->getStatusCode());
	}
}
