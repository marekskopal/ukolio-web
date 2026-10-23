<?php

declare(strict_types=1);

namespace Ukolio\Service\Avatar;

use DateTimeImmutable;
use GdImage;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Throwable;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Service\Storage\FileStorageInterface;
use const IMAGETYPE_JPEG;
use const IMAGETYPE_PNG;
use const IMAGETYPE_WEBP;
use const IMG_FLIP_HORIZONTAL;

final readonly class AvatarService implements AvatarServiceInterface
{
	public const int MaxUploadBytes = 1024 * 1024;

	private const int Size = 256;

	private const int Quality = 85;

	/**
	 * Guards against decompression bombs: a 1 MB file can still declare a huge canvas. GD holds ~4–5 bytes per
	 * pixel and imagerotate() allocates a second copy, so this keeps the worst case well under memory_limit.
	 */
	private const int MaxSourcePixels = 16000000;

	private const array AllowedImageTypes = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP];

	public function __construct(
		private FileStorageInterface $fileStorage,
		private UserRepository $userRepository,
		private LoggerInterface $logger,
	) {
	}

	public function upload(User $user, string $body): User
	{
		$size = strlen($body);
		if ($size === 0) {
			throw new InvalidAvatarException('Avatar image is empty.');
		}
		if ($size > self::MaxUploadBytes) {
			throw new InvalidAvatarException('Avatar image exceeds the 1 MB limit.');
		}

		$webp = $this->normalize($body);

		$previousKey = $user->avatarKey;
		$key = bin2hex(random_bytes(16));
		$this->fileStorage->put(self::storageKey($user->id, $key), $webp, 'image/webp');

		$user->avatarKey = $key;
		$user->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($user);

		if ($previousKey !== null) {
			$this->deleteObjectByKey($user->id, $previousKey);
		}

		return $user;
	}

	public function remove(User $user): User
	{
		if ($user->avatarKey === null) {
			return $user;
		}

		$this->deleteObject($user);
		$user->avatarKey = null;
		$user->updatedAt = new DateTimeImmutable();
		$this->userRepository->persist($user);

		return $user;
	}

	public function readContent(int $userId, string $avatarKey): ?string
	{
		$user = $this->userRepository->findUserById($userId);
		if ($user?->avatarKey === null || !hash_equals($user->avatarKey, $avatarKey)) {
			return null;
		}

		try {
			return $this->fileStorage->get(self::storageKey($user->id, $user->avatarKey));
		} catch (RuntimeException) {
			return null;
		}
	}

	public function deleteObject(User $user): void
	{
		if ($user->avatarKey === null) {
			return;
		}

		$this->deleteObjectByKey($user->id, $user->avatarKey);
	}

	/** Runs after the user row changed or is gone: an unreachable S3 must not fail the request. */
	private function deleteObjectByKey(int $userId, string $avatarKey): void
	{
		try {
			$this->fileStorage->delete(self::storageKey($userId, $avatarKey));
		} catch (Throwable $e) {
			$this->logger->warning('Failed to delete avatar object: ' . $e->getMessage(), ['userId' => $userId]);
		}
	}

	private static function storageKey(int $userId, string $avatarKey): string
	{
		return 'avatars/' . $userId . '/' . $avatarKey . '.webp';
	}

	/** Decodes the upload, applies EXIF orientation, center-crops to a square and re-encodes as 256×256 webp (dropping all metadata). */
	private function normalize(string $body): string
	{
		$info = getimagesizefromstring($body);
		if ($info === false || !in_array($info[2], self::AllowedImageTypes, true)) {
			throw new InvalidAvatarException('Avatar must be a JPEG, PNG or WebP image.');
		}
		if ($info[0] * $info[1] > self::MaxSourcePixels) {
			throw new InvalidAvatarException('Avatar image dimensions are too large.');
		}

		try {
			$source = imagecreatefromstring($body);
		} catch (Throwable) {
			$source = false;
		}

		if ($source === false) {
			throw new InvalidAvatarException('Avatar image could not be decoded.');
		}

		if ($info[2] === IMAGETYPE_JPEG) {
			$source = self::applyExifOrientation($source, $body);
		}

		$width = imagesx($source);
		$height = imagesy($source);
		$side = min($width, $height);

		$target = imagecreatetruecolor(self::Size, self::Size);
		if ($target === false) {
			throw new RuntimeException('Avatar canvas could not be allocated.');
		}
		imagealphablending($target, false);
		imagesavealpha($target, true);
		$transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
		if ($transparent !== false) {
			imagefill($target, 0, 0, $transparent);
		}
		imagecopyresampled(
			$target,
			$source,
			0,
			0,
			intdiv($width - $side, 2),
			intdiv($height - $side, 2),
			self::Size,
			self::Size,
			$side,
			$side,
		);

		ob_start();
		$encoded = imagewebp($target, null, self::Quality);
		$webp = (string) ob_get_clean();
		if (!$encoded || $webp === '') {
			throw new RuntimeException('Avatar image could not be encoded.');
		}

		return $webp;
	}

	private static function applyExifOrientation(GdImage $image, string $body): GdImage
	{
		if (!function_exists('exif_read_data')) {
			return $image;
		}

		try {
			$exif = exif_read_data('data://image/jpeg;base64,' . base64_encode($body));
		} catch (Throwable) {
			return $image;
		}

		$orientation = is_array($exif) && is_int($exif['Orientation'] ?? null) ? $exif['Orientation'] : 1;

		// Orientations 2/4/5/7 are the mirrored variants of 1/3/8/6: flip first, then rotate
		// (imagerotate() turns counter-clockwise for positive angles).
		if (in_array($orientation, [2, 4, 5, 7], true)) {
			imageflip($image, IMG_FLIP_HORIZONTAL);
		}
		$angle = match ($orientation) {
			3, 4 => 180,
			5, 8 => 90,
			6, 7 => -90,
			default => 0,
		};
		if ($angle === 0) {
			return $image;
		}

		$rotated = imagerotate($image, $angle, 0);

		return $rotated === false ? $image : $rotated;
	}
}
