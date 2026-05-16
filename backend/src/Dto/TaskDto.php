<?php

declare(strict_types=1);

namespace TaskManager\Dto;

use TaskManager\Model\Entity\Task;

final readonly class TaskDto
{
    public function __construct(
        public int $id,
        public int $projectId,
        public int $statusId,
        public string $name,
        public ?string $description,
        public string $priority,
        public ?string $dueDate,
        public int $position,
        public string $createdAt,
        public string $updatedAt,
    ) {
    }

    public static function fromEntity(Task $task): self
    {
        return new self(
            id: $task->id,
            projectId: $task->project->id,
            statusId: $task->status->id,
            name: $task->name,
            description: $task->description,
            priority: $task->priority->value,
            dueDate: $task->dueDate?->format('Y-m-d'),
            position: $task->position,
            createdAt: $task->createdAt->format(DATE_ATOM),
            updatedAt: $task->updatedAt->format(DATE_ATOM),
        );
    }
}
