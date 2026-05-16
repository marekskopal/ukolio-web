<?php

declare(strict_types=1);

namespace TaskManager\Model\Entity;

use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use TaskManager\Model\Repository\ProjectRepository;

#[Entity(repositoryClass: ProjectRepository::class)]
class Project extends AEntity
{
    public function __construct(
        #[ManyToOne(entityClass: User::class)]
        public readonly User $user,
        #[Column(type: Type::String)]
        public string $name,
        #[Column(type: Type::Text, nullable: true)]
        public ?string $description = null,
    ) {
    }
}
