<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Enum\TaskPriorityEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\Enum\OrderDirectionEnum;
use Ukolio\Model\Repository\Enum\TaskOrderByEnum;
use Ukolio\Model\Repository\TaskRepository;
use Ukolio\Model\Repository\TaskTagRepository;
use Ukolio\Service\Actor\ActorContextInterface;

final readonly class TaskProvider implements TaskProviderInterface
{
	public function __construct(
		private TaskRepository $taskRepository,
		private EventProviderInterface $eventProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private TaskFileProviderInterface $taskFileProvider,
		private TaskRelationProviderInterface $taskRelationProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private TaskTagRepository $taskTagRepository,
		private ActorContextInterface $actorContext,
	) {
	}

	public function getTask(int $taskId): ?Task
	{
		return $this->taskRepository->findById($taskId);
	}

	/** @return Iterator<Task> */
	public function getTasksByProject(Project $project): Iterator
	{
		return $this->taskRepository->findByProject($project->id);
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $tagIds
	 * @param list<int>|null $assigneeIds
	 * @return Iterator<Task>
	 */
	public function getTasksInWorkspace(
		Workspace $workspace,
		int $limit,
		int $offset,
		TaskOrderByEnum $orderBy,
		OrderDirectionEnum $direction,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $tagIds = null,
		?array $assigneeIds = null,
	): Iterator {
		return $this->taskRepository->findInWorkspace(
			$workspace->id,
			$limit,
			$offset,
			$orderBy,
			$direction,
			$search,
			$statusIds,
			$onlyActive,
			$this->resolveTaskIdsByTags($tagIds),
			$assigneeIds,
		);
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $tagIds
	 * @param list<int>|null $assigneeIds
	 */
	public function countTasksInWorkspace(
		Workspace $workspace,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $tagIds = null,
		?array $assigneeIds = null,
	): int {
		return $this->taskRepository->countInWorkspace(
			$workspace->id,
			$search,
			$statusIds,
			$onlyActive,
			$this->resolveTaskIdsByTags($tagIds),
			$assigneeIds,
		);
	}

	/**
	 * @param list<int>|null $tagIds
	 * @return list<int>|null null = no tag filter; [] = no matches
	 */
	private function resolveTaskIdsByTags(?array $tagIds): ?array
	{
		if ($tagIds === null || $tagIds === []) {
			return null;
		}
		return $this->taskTagRepository->findTaskIdsByTagIds($tagIds);
	}

	/**
	 * @param array<int, ?string>|null $fieldValues
	 * @param list<int>|null $tagIds
	 */
	public function createTask(
		User $author,
		Project $project,
		Status $status,
		string $name,
		?string $description,
		TaskPriorityEnum $priority,
		?DateTimeImmutable $dueDate,
		?User $assignee = null,
		?array $fieldValues = null,
		?array $tagIds = null,
	): Task {
		if ($fieldValues !== null) {
			$this->taskFieldValueProvider->validateForProject($project, $fieldValues);
		}

		$position = $this->nextPosition($status);
		$sequenceNumber = $this->taskRepository->nextSequenceNumber($project->id);

		$now = new DateTimeImmutable();
		$task = new Task(
			project: $project,
			status: $status,
			assignee: $assignee,
			name: $name,
			description: $description,
			priority: $priority,
			dueDate: $dueDate,
			position: $position,
			sequenceNumber: $sequenceNumber,
			createdByAgent: $this->actorContext->isAgent(),
		);
		$task->createdAt = $now;
		$task->updatedAt = $now;

		$this->taskRepository->persist($task);

		if ($fieldValues !== null) {
			$this->taskFieldValueProvider->persistForTask($task, $fieldValues);
		}

		if ($tagIds !== null) {
			$tagChanges = $this->taskTagProvider->setTagsForTask($project->workspace, $task, $tagIds);
			if ($tagChanges['added'] !== [] || $tagChanges['removed'] !== []) {
				$this->eventProvider->recordEvent(
					$author,
					$project,
					EventTypeEnum::TaskTagsUpdated,
					['taskName' => $task->name, 'added' => $tagChanges['added'], 'removed' => $tagChanges['removed']],
					$task->id,
				);
			}
		}

		$this->eventProvider->recordEvent(
			$author,
			$project,
			EventTypeEnum::TaskCreated,
			['name' => $name, 'statusId' => $status->id, 'statusName' => $status->name],
			$task->id,
		);

		return $task;
	}

	/**
	 * @param array<int, ?string>|null $fieldValues
	 * @param list<int>|null $tagIds
	 */
	public function updateTask(
		User $author,
		Task $task,
		string $name,
		?string $description,
		TaskPriorityEnum $priority,
		?DateTimeImmutable $dueDate,
		Status $status,
		?User $assignee,
		?array $fieldValues = null,
		?array $tagIds = null,
	): Task {
		if ($fieldValues !== null) {
			$this->taskFieldValueProvider->validateForProject($task->project, $fieldValues);
		}

		$oldName = $task->name;
		$statusChanged = $task->status->id !== $status->id;

		$task->name = $name;
		$task->description = $description;
		$task->priority = $priority;
		$task->dueDate = $dueDate;
		$task->assignee = $assignee;
		if ($statusChanged) {
			$task->status = $status;
			$task->position = $this->nextPosition($status);
		}
		$task->updatedAt = new DateTimeImmutable();
		$this->taskRepository->persist($task);

		$fieldChanges = $fieldValues !== null
			? $this->taskFieldValueProvider->persistForTask($task, $fieldValues)
			: [];

		$tagChanges = $tagIds !== null
			? $this->taskTagProvider->setTagsForTask($task->project->workspace, $task, $tagIds)
			: ['added' => [], 'removed' => []];

		$metadata = ['name' => $name, 'oldName' => $oldName];
		if ($fieldChanges !== []) {
			$metadata['fieldChanges'] = $fieldChanges;
		}

		$this->eventProvider->recordEvent($author, $task->project, EventTypeEnum::TaskUpdated, $metadata, $task->id);

		if ($tagChanges['added'] !== [] || $tagChanges['removed'] !== []) {
			$this->eventProvider->recordEvent(
				$author,
				$task->project,
				EventTypeEnum::TaskTagsUpdated,
				['taskName' => $task->name, 'added' => $tagChanges['added'], 'removed' => $tagChanges['removed']],
				$task->id,
			);
		}

		return $task;
	}

	public function moveTask(User $author, Task $task, Status $newStatus, int $newPosition): Task
	{
		$fromStatus = $task->status;
		$fromPosition = $task->position;
		$sameColumn = $fromStatus->id === $newStatus->id;

		if ($sameColumn) {
			$this->reorderWithinColumn($task, $newPosition);
		} else {
			$this->closeGapInOldColumn($task);
			$this->openSlotInNewColumn($newStatus, $newPosition);
			$task->status = $newStatus;
			$task->position = $newPosition;
		}
		$task->updatedAt = new DateTimeImmutable();
		$this->taskRepository->persist($task);

		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskMoved,
			[
				'fromStatusId' => $fromStatus->id,
				'fromStatusName' => $fromStatus->name,
				'toStatusId' => $newStatus->id,
				'toStatusName' => $newStatus->name,
				'fromPosition' => $fromPosition,
				'toPosition' => $newPosition,
				'taskName' => $task->name,
			],
			$task->id,
		);

		return $task;
	}

	public function unassignTasksForUserInWorkspace(User $user, Workspace $workspace): void
	{
		$now = new DateTimeImmutable();
		foreach ($this->taskRepository->findByAssigneeInWorkspace($user->id, $workspace->id) as $task) {
			$task->assignee = null;
			$task->updatedAt = $now;
			$this->taskRepository->persist($task);
		}
	}

	public function deleteTask(User $author, Task $task): void
	{
		$this->eventProvider->recordEvent(
			$author,
			$task->project,
			EventTypeEnum::TaskDeleted,
			['name' => $task->name],
			$task->id,
		);

		$this->taskFieldValueProvider->deleteAllForTask($task);
		$this->taskFileProvider->deleteAllForTask($author, $task);
		$this->taskRelationProvider->deleteAllForTask($task);
		$this->taskTagProvider->deleteAllForTask($task);
		$this->taskRepository->delete($task);
	}

	private function reorderWithinColumn(Task $task, int $newPosition): void
	{
		$oldPosition = $task->position;
		if ($oldPosition === $newPosition) {
			return;
		}

		foreach ($this->taskRepository->findByStatus($task->status->id) as $sibling) {
			if ($sibling->id === $task->id) {
				continue;
			}

			if ($oldPosition < $newPosition) {
				if ($sibling->position > $oldPosition && $sibling->position <= $newPosition) {
					$sibling->position--;
					$sibling->updatedAt = new DateTimeImmutable();
					$this->taskRepository->persist($sibling);
				}
			} else {
				if ($sibling->position >= $newPosition && $sibling->position < $oldPosition) {
					$sibling->position++;
					$sibling->updatedAt = new DateTimeImmutable();
					$this->taskRepository->persist($sibling);
				}
			}
		}

		$task->position = $newPosition;
	}

	private function closeGapInOldColumn(Task $task): void
	{
		foreach ($this->taskRepository->findByStatus($task->status->id) as $sibling) {
			if ($sibling->id === $task->id) {
				continue;
			}
			if ($sibling->position <= $task->position) {
				continue;
			}

			$sibling->position--;
			$sibling->updatedAt = new DateTimeImmutable();
			$this->taskRepository->persist($sibling);
		}
	}

	private function openSlotInNewColumn(Status $newStatus, int $newPosition): void
	{
		foreach ($this->taskRepository->findByStatus($newStatus->id) as $sibling) {
			if ($sibling->position >= $newPosition) {
				$sibling->position++;
				$sibling->updatedAt = new DateTimeImmutable();
				$this->taskRepository->persist($sibling);
			}
		}
	}

	private function nextPosition(Status $status): int
	{
		$tasks = iterator_to_array($this->taskRepository->findByStatus($status->id), false);
		if ($tasks === []) {
			return 0;
		}
		$max = 0;
		foreach ($tasks as $t) {
			if ($t->position > $max) {
				$max = $t->position;
			}
		}
		return $max + 1;
	}
}
