<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\User;

final readonly class UserDto
{
	public function __construct(public int $id, public string $email, public string $name, public ?int $currentWorkspaceId,)
	{
	}

	public static function fromEntity(User $user): self
	{
		return new self(id: $user->id, email: $user->email, name: $user->name, currentWorkspaceId: $user->currentWorkspaceId,);
	}
}
