<?php

declare(strict_types=1);

namespace Ukolio\Service\Avatar;

use Ukolio\Model\Entity\User;

final readonly class AvatarUrl
{
	/** Public, unauthenticated URL of the user's avatar — `<img src>` can't carry the bearer token. */
	public static function forUser(User $user): ?string
	{
		if ($user->avatarKey === null) {
			return null;
		}

		return '/api/avatars/' . $user->id . '/' . $user->avatarKey;
	}
}
