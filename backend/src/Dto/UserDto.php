<?php

declare(strict_types=1);

namespace TaskManager\Dto;

use TaskManager\Model\Entity\User;

final readonly class UserDto
{
    public function __construct(public int $id, public string $email, public string $name)
    {
    }

    public static function fromEntity(User $user): self
    {
        return new self(id: $user->id, email: $user->email, name: $user->name);
    }
}
