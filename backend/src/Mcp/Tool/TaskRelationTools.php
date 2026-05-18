<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpTaskRelationDto;
use Ukolio\Mcp\Dto\McpTaskRelationListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Enum\TaskRelationTypeEnum;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskRelation;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskRelationProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class TaskRelationTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private TaskProviderInterface $taskProvider,
		private TaskRelationProviderInterface $taskRelationProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * List all relations for a task — both outgoing (source) and incoming (target).
	 *
	 * @param int $taskId Task ID
	 */
	#[McpTool(name: 'list_task_relations', description: 'List all relations of a task (outgoing + incoming).')]
	public function listTaskRelations(int $taskId): McpTaskRelationListDto
	{
		$task = $this->requireTask($taskId);

		$outgoing = array_map(
			static fn (TaskRelation $rel): McpTaskRelationDto => McpTaskRelationDto::fromEntity($rel),
			$this->taskRelationProvider->findOutgoing($task),
		);
		$incoming = array_map(
			static fn (TaskRelation $rel): McpTaskRelationDto => McpTaskRelationDto::fromEntity($rel),
			$this->taskRelationProvider->findIncoming($task),
		);

		return new McpTaskRelationListDto(outgoing: $outgoing, incoming: $incoming);
	}

	/**
	 * Link two tasks with a typed relation. The relation is directional — source links to target.
	 * Types: Related (symmetric), Duplicates (symmetric), Parent (source is parent, target is subtask),
	 * DependsOn (source depends on target).
	 *
	 * @param int $sourceTaskId Source task ID
	 * @param int $targetTaskId Target task ID
	 * @param string $type Relation type: Related, Duplicates, Parent, DependsOn
	 */
	#[McpTool(name: 'link_tasks', description: 'Create a typed relation from sourceTaskId to targetTaskId.')]
	public function linkTasks(int $sourceTaskId, int $targetTaskId, string $type): McpTaskRelationDto
	{
		$user = $this->userContext->getUser();
		$source = $this->requireTask($sourceTaskId);
		$target = $this->requireTask($targetTaskId);
		$typeEnum = $this->parseType($type);

		$relation = $this->taskRelationProvider->createRelation($user, $source, $target, $typeEnum);

		return McpTaskRelationDto::fromEntity($relation);
	}

	/**
	 * Delete a relation. Pass either relationId, or all of sourceTaskId + targetTaskId + type.
	 *
	 * @param int|null $relationId Optional relation ID
	 * @param int|null $sourceTaskId Optional source task ID
	 * @param int|null $targetTaskId Optional target task ID
	 * @param string|null $type Optional relation type (Related, Duplicates, Parent, DependsOn)
	 */
	#[McpTool(name: 'unlink_tasks', description: 'Remove a relation by id or by (source, target, type).')]
	public function unlinkTasks(
		?int $relationId = null,
		?int $sourceTaskId = null,
		?int $targetTaskId = null,
		?string $type = null,
	): string {
		$user = $this->userContext->getUser();
		$relation = $this->resolveRelation($relationId, $sourceTaskId, $targetTaskId, $type);

		if (!$this->workspaceProvider->isMember($user, $relation->sourceTask->project->workspace)) {
			throw new RuntimeException('Relation not found.');
		}

		$this->taskRelationProvider->deleteRelation($user, $relation);

		return 'Relation deleted.';
	}

	private function requireTask(int $taskId): Task
	{
		$task = $this->taskProvider->getTask($taskId);
		if ($task === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $task->project->workspace)) {
			throw new RuntimeException(sprintf('Task %d not found.', $taskId));
		}
		return $task;
	}

	private function parseType(string $type): TaskRelationTypeEnum
	{
		$enum = TaskRelationTypeEnum::tryFrom($type);
		if ($enum === null) {
			throw new RuntimeException(sprintf(
				'Invalid relation type "%s". Valid values: %s',
				$type,
				implode(', ', array_column(TaskRelationTypeEnum::cases(), 'value')),
			));
		}
		return $enum;
	}

	private function resolveRelation(?int $relationId, ?int $sourceTaskId, ?int $targetTaskId, ?string $type): TaskRelation
	{
		if ($relationId !== null) {
			$relation = $this->taskRelationProvider->getRelation($relationId);
			if ($relation === null) {
				throw new RuntimeException(sprintf('Relation %d not found.', $relationId));
			}
			return $relation;
		}

		if ($sourceTaskId === null || $targetTaskId === null || $type === null) {
			throw new RuntimeException('Provide relationId, or all of sourceTaskId, targetTaskId, type.');
		}

		$source = $this->requireTask($sourceTaskId);
		$target = $this->requireTask($targetTaskId);
		$typeEnum = $this->parseType($type);

		foreach ($this->taskRelationProvider->findOutgoing($source) as $rel) {
			if ($rel->targetTask->id === $target->id && $rel->type === $typeEnum) {
				return $rel;
			}
		}

		throw new RuntimeException('Relation not found.');
	}
}
