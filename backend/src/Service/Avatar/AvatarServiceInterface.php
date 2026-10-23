<?php

declare(strict_types=1);

namespace Ukolio\Service\Avatar;

use Ukolio\Model\Entity\User;

interface AvatarServiceInterface
{
	/**
	 * Validates, normalizes (EXIF-rotate, center-crop, 256×256 webp) and stores the image as the user's avatar.
	 * Throws InvalidAvatarException when the upload isn't an acceptable image.
	 */
	public function upload(User $user, string $body): User;

	/** Deletes the stored avatar object and reverts the user to initials. */
	public function remove(User $user): User;

	/** Returns the webp bytes of the avatar named by `$avatarKey`, or null when it isn't the user's current one. */
	public function readContent(int $userId, string $avatarKey): ?string;

	/** Best-effort cleanup of the stored object when the user row itself is going away. */
	public function deleteObject(User $user): void;
}
