<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Ukolio\Model\Entity\Enum\TaskPriorityEnum;
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
	): Iterator;

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $tagIds
	 */
	public function countTasksInWorkspace(
		Workspace $workspace,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $tagIds = null,
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
		TaskPriorityEnum $priority,
		?DateTimeImmutable $dueDate,
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
		TaskPriorityEnum $priority,
		?DateTimeImmutable $dueDate,
		Status $status,
		?array $fieldValues = null,
		?array $tagIds = null,
	): Task;

	public function moveTask(User $author, Task $task, Status $newStatus, int $newPosition): Task;

	public function deleteTask(User $author, Task $task): void;
}
