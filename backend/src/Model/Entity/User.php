<?php

declare(strict_types=1);

namespace TaskManager\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Enum\Type;
use TaskManager\Model\Repository\UserRepository;

#[Entity(repositoryClass: UserRepository::class)]
class User extends AEntity
{
    public function __construct(
        #[Column(type: Type::String)]
        public string $email,
        #[Column(type: Type::String)]
        public string $password,
        #[Column(type: Type::String)]
        public string $name,
    ) {
    }
}
