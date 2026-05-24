<?php

declare(strict_types=1);

namespace Ukolio\Dto;

use Ukolio\Model\Entity\Task;
use const DATE_ATOM;

final readonly class TaskDto
{
	/**
	 * @param list<TaskFieldValueDto> $fieldValues
	 * @param list<int> $tagIds
	 */
	public function __construct(
		public int $id,
		public string $code,
		public int $projectId,
		public int $statusId,
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
		public array $fieldValues,
		public array $tagIds,
	) {
	}

	/**
	 * @param array<int, ?string> $fieldValues
	 * @param list<int> $tagIds
	 */
	public static function fromEntity(Task $task, array $fieldValues = [], array $tagIds = []): self
	{
		$values = [];
		foreach ($fieldValues as $fieldId => $value) {
			$values[] = new TaskFieldValueDto(fieldId: $fieldId, value: $value);
		}

		return new self(
			id: $task->id,
			code: $task->project->prefix . '-' . $task->sequenceNumber,
			projectId: $task->project->id,
			statusId: $task->status->id,
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
			fieldValues: $values,
			tagIds: $tagIds,
		);
	}
}
