<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Ukolio\Model\Entity\Priority;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\Enum\OrderDirectionEnum;
use Ukolio\Model\Repository\Enum\TaskOrderByEnum;

interface TaskProviderInterface
{
	public function getTask(int $taskId): ?Task;

	/** @return Iterator<Task> */
	public function getTasksByProject(Project $project): Iterator;

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
	): Iterator;

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
	): int;

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
		Priority $priority,
		?DateTimeImmutable $dueDate,
		?User $assignee = null,
		?array $fieldValues = null,
		?array $tagIds = null,
	): Task;

	/**
	 * @param array<int, ?string>|null $fieldValues
	 * @param list<int>|null $tagIds
	 */
	public function updateTask(
		User $author,
		Task $task,
		string $name,
		?string $description,
		Priority $priority,
		?DateTimeImmutable $dueDate,
		Status $status,
		?User $assignee,
		?array $fieldValues = null,
		?array $tagIds = null,
		bool $recordEvent = true,
	): Task;

	public function moveTask(User $author, Task $task, Status $newStatus, int $newPosition, bool $recordEvent = true): Task;

	public function deleteTask(User $author, Task $task, bool $recordEvent = true): void;

	public function nextPosition(Status $status): int;

	public function unassignTasksForUserInWorkspace(User $user, Workspace $workspace): void;
}
