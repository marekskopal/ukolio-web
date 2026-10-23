<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\WorkspaceUser;
use Ukolio\Service\Avatar\AvatarUrl;

final readonly class WorkspaceMemberDto
{
	public function __construct(
		public int $userId,
		public string $name,
		public string $email,
		public string $role,
		public ?string $avatarUrl,
	) {
	}

	public static function fromEntity(WorkspaceUser $membership): self
	{
		return new self(
			userId: $membership->user->id,
			name: $membership->user->name,
			email: $membership->user->email,
			role: $membership->role->value,
			avatarUrl: AvatarUrl::forUser($membership->user),
		);
	}
}
