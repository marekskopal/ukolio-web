<?php

declare(strict_types=1);

namespace TaskManager\Model\Entity;

use DateTimeImmutable;
use MarekSkopal\ORM\Attribute\Column;
use MarekSkopal\ORM\Attribute\ColumnEnum;
use MarekSkopal\ORM\Attribute\Entity;
use MarekSkopal\ORM\Attribute\ManyToOne;
use MarekSkopal\ORM\Enum\Type;
use TaskManager\Model\Entity\Enum\TaskPriorityEnum;
use TaskManager\Model\Repository\TaskRepository;

#[Entity(repositoryClass: TaskRepository::class)]
class Task extends AEntity
{
    public function __construct(
        #[ManyToOne(entityClass: Project::class)]
        public readonly Project $project,
        #[ManyToOne(entityClass: Status::class)]
        public Status $status,
        #[Column(type: Type::String)]
        public string $name,
        #[Column(type: Type::Text, nullable: true)]
        public ?string $description,
        #[ColumnEnum(enum: TaskPriorityEnum::class, default: TaskPriorityEnum::Medium)]
        public TaskPriorityEnum $priority,
        #[Column(type: Type::Date, nullable: true)]
        public ?DateTimeImmutable $dueDate,
        #[Column(type: Type::Int)]
        public int $position,
    ) {
    }
}
