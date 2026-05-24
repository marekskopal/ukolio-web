<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Dto;

use Ukolio\Model\Entity\Task;

final readonly class McpTaskDto
{
	/**
	 * @param list<McpTaskFieldValueDto> $fieldValues
	 * @param list<int> $tagIds
	 */
	public function __construct(
		public int $id,
		public string $code,
		public int $projectId,
		public int $statusId,
		public string $statusName,
		public ?int $assigneeId,
		public string $name,
		public ?string $description,
		public string $priority,
		public ?string $dueDate,
		public int $position,
		public int $sequenceNumber,
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
			$values[] = new McpTaskFieldValueDto(fieldId: $fieldId, value: $value);
		}

		return new self(
			id: $task->id,
			code: $task->project->prefix . '-' . $task->sequenceNumber,
			projectId: $task->project->id,
			statusId: $task->status->id,
			statusName: $task->status->name,
			assigneeId: $task->assignee?->id,
			name: $task->name,
			description: $task->description,
			priority: $task->priority->value,
			dueDate: $task->dueDate?->format('Y-m-d'),
			position: $task->position,
			sequenceNumber: $task->sequenceNumber,
			fieldValues: $values,
			tagIds: $tagIds,
		);
	}
}
