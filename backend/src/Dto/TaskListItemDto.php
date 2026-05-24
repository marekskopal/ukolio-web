<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Task;
use const DATE_ATOM;

final readonly class TaskListItemDto
{
	/** @param list<int> $tagIds */
	public function __construct(
		public int $id,
		public string $code,
		public int $projectId,
		public string $projectName,
		public int $statusId,
		public StatusDto $status,
		public ?int $assigneeId,
		public string $name,
		public ?string $description,
		public string $priority,
		public ?string $dueDate,
		public int $position,
		public int $sequenceNumber,
		public bool $createdByAgent,
		public string $createdAt,
		public string $updatedAt,
		public array $tagIds,
	) {
	}

	/** @param list<int> $tagIds */
	public static function fromEntity(Task $task, array $tagIds = []): self
	{
		return new self(
			id: $task->id,
			code: $task->project->prefix . '-' . $task->sequenceNumber,
			projectId: $task->project->id,
			projectName: $task->project->name,
			statusId: $task->status->id,
			status: StatusDto::fromEntity($task->status),
			assigneeId: $task->assignee?->id,
			name: $task->name,
			description: $task->description,
			priority: $task->priority->value,
			dueDate: $task->dueDate?->format('Y-m-d'),
			position: $task->position,
			sequenceNumber: $task->sequenceNumber,
			createdByAgent: $task->createdByAgent,
			createdAt: $task->createdAt->format(DATE_ATOM),
			updatedAt: $task->updatedAt->format(DATE_ATOM),
			tagIds: $tagIds,
		);
	}
}
