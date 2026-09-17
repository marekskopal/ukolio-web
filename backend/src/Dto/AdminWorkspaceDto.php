<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Workspace;
use const DATE_ATOM;

final readonly class AdminWorkspaceDto
{
	public function __construct(
		public int $id,
		public string $name,
		public int $ownerId,
		public string $ownerEmail,
		public string $ownerName,
		public int $memberCount,
		public int $projectCount,
		public int $taskCount,
		public string $createdAt,
	) {
	}

	public static function fromEntity(Workspace $workspace, int $memberCount, int $projectCount, int $taskCount): self
	{
		return new self(
			id: $workspace->id,
			name: $workspace->name,
			ownerId: $workspace->owner->id,
			ownerEmail: $workspace->owner->email,
			ownerName: $workspace->owner->name,
			memberCount: $memberCount,
			projectCount: $projectCount,
			taskCount: $taskCount,
			createdAt: $workspace->createdAt->format(DATE_ATOM),
		);
	}
}
