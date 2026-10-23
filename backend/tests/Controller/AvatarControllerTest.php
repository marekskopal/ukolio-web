<?php

declare(strict_types=1);

namespace Ukolio\Tests\Controller;

use Laminas\Diactoros\Stream;
use Laminas\Diactoros\UploadedFile;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ResponseInterface;
use Ukolio\Controller\AvatarController;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Service\Avatar\AvatarService;
use Ukolio\Tests\Support\AppHarness;
use Ukolio\Tests\Support\Fixture;
use Ukolio\Tests\Support\IntegrationTestCase;
use const UPLOAD_ERR_OK;

#[CoversClass(AvatarController::class)]
#[CoversClass(AvatarService::class)]
final class AvatarControllerTest extends IntegrationTestCase
{
	public function testUploadNormalizesToSquareWebpAndServesItPublicly(): void
	{
		$user = Fixture::createUser();

		$response = $this->upload($user, self::png(400, 200));

		self::assertSame(200, $response->getStatusCode(), (string) $response->getBody());
		$url = self::stringField($this->jsonBody($response)['avatarUrl']);
		self::assertMatchesRegularExpression('#^/api/avatars/' . $user->id . '/[a-f0-9]{32}$#', $url);

		$image = $this->request('GET', $url);
		self::assertSame(200, $image->getStatusCode());
		self::assertSame('image/webp', $image->getHeaderLine('Content-Type'));
		self::assertStringContainsString('immutable', $image->getHeaderLine('Cache-Control'));
		$size = getimagesizefromstring((string) $image->getBody());
		self::assertNotFalse($size);
		self::assertSame([256, 256], [$size[0], $size[1]]);
	}

	public function testReplacingAvatarRotatesUrlAndInvalidatesOldOne(): void
	{
		$user = Fixture::createUser();

		$first = self::stringField($this->jsonBody($this->upload($user, self::png(64, 64)))['avatarUrl']);
		$second = self::stringField($this->jsonBody($this->upload($this->reload($user), self::png(64, 64)))['avatarUrl']);

		self::assertNotSame($first, $second);
		self::assertFalse(AppHarness::fileStorage()->has('avatars/' . $user->id . '/' . basename($first) . '.webp'));
		self::assertSame(404, $this->request('GET', $first)->getStatusCode());
		self::assertSame(200, $this->request('GET', $second)->getStatusCode());
	}

	public function testUnknownKeyIsNotFound(): void
	{
		$user = Fixture::createUser();

		self::assertSame(404, $this->request('GET', '/api/avatars/' . $user->id . '/' . str_repeat('ab', 16))->getStatusCode());
	}

	public function testRejectsNonImageUpload(): void
	{
		$user = Fixture::createUser();

		$response = $this->upload($user, '<svg xmlns="http://www.w3.org/2000/svg"></svg>');

		self::assertSame(422, $response->getStatusCode());
		self::assertNull($this->reload($user)->avatarKey);
	}

	public function testRejectsOversizedUpload(): void
	{
		$user = Fixture::createUser();

		$response = $this->upload($user, self::png(16, 16) . str_repeat("\0", AvatarService::MaxUploadBytes));

		self::assertSame(422, $response->getStatusCode());
	}

	public function testUploadRequiresAuthentication(): void
	{
		$request = $this->buildRequest('POST', '/api/current-user/avatar')
			->withUploadedFiles(['file' => self::uploadedFile(self::png(16, 16))]);

		self::assertSame(401, $this->handler->handle($request)->getStatusCode());
	}

	public function testDeleteRevertsToInitials(): void
	{
		$user = Fixture::createUser();
		$url = self::stringField($this->jsonBody($this->upload($user, self::png(32, 32)))['avatarUrl']);

		$response = $this->request('DELETE', '/api/current-user/avatar', authenticatedAs: $this->reload($user));

		self::assertSame(200, $response->getStatusCode());
		self::assertNull($this->jsonBody($response)['avatarUrl']);
		self::assertNull($this->reload($user)->avatarKey);
		self::assertFalse(AppHarness::fileStorage()->has('avatars/' . $user->id . '/' . basename($url) . '.webp'));
		self::assertSame(404, $this->request('GET', $url)->getStatusCode());
	}

	public function testMemberListCarriesAvatarUrl(): void
	{
		$owner = Fixture::createUser();
		$workspace = Fixture::createWorkspace($owner);
		$member = Fixture::createUser(email: 'member@example.com');
		Fixture::addMember($workspace, $member, WorkspaceRoleEnum::Member);
		$url = self::stringField($this->jsonBody($this->upload($member, self::png(32, 32)))['avatarUrl']);

		$response = $this->request('GET', '/api/workspaces/' . $workspace->id . '/members', authenticatedAs: $owner);

		self::assertSame(200, $response->getStatusCode());
		$byUser = array_column($this->jsonList($response), 'avatarUrl', 'userId');
		self::assertSame($url, $byUser[$member->id]);
		self::assertNull($byUser[$owner->id]);
	}

	private function upload(User $user, string $bytes): ResponseInterface
	{
		$request = $this->buildRequest('POST', '/api/current-user/avatar', authenticatedAs: $user)
			->withUploadedFiles(['file' => self::uploadedFile($bytes)]);

		return $this->handler->handle($request);
	}

	private function reload(User $user): User
	{
		$repository = AppHarness::container()->get(UserRepository::class);
		assert($repository instanceof UserRepository);
		$reloaded = $repository->findUserById($user->id);
		self::assertNotNull($reloaded);

		return $reloaded;
	}

	private static function uploadedFile(string $bytes): UploadedFile
	{
		$stream = new Stream('php://temp', 'r+');
		$stream->write($bytes);
		$stream->rewind();

		return new UploadedFile($stream, strlen($bytes), UPLOAD_ERR_OK, 'avatar.png', 'image/png');
	}

	/**
	 * @param int<1, max> $width
	 * @param int<1, max> $height
	 */
	private static function png(int $width, int $height): string
	{
		$image = imagecreatetruecolor($width, $height);
		self::assertNotFalse($image);
		ob_start();
		imagepng($image);

		return (string) ob_get_clean();
	}
}
