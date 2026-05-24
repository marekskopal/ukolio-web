<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use EmptyIterator;
use Iterator;
use MarekSkopal\ORM\Query\Select;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Repository\Enum\OrderDirectionEnum;
use Ukolio\Model\Repository\Enum\TaskOrderByEnum;

/** @extends AbstractRepository<Task> */
final class TaskRepository extends AbstractRepository
{
	public function findById(int $taskId): ?Task
	{
		return $this->findOne(['id' => $taskId]);
	}

	public function findByProjectAndSequence(int $projectId, int $sequenceNumber): ?Task
	{
		return $this->findOne(['project_id' => $projectId, 'sequence_number' => $sequenceNumber]);
	}

	/**
	 * @param list<int> $taskIds
	 * @return Iterator<Task>
	 */
	public function findByIds(array $taskIds): Iterator
	{
		if ($taskIds === []) {
			return new EmptyIterator();
		}
		return $this->select()
			->where(['id', 'IN', $taskIds])
			->fetchAll();
	}

	public function nextSequenceNumber(int $projectId): int
	{
		$max = 0;
		foreach ($this->findByProject($projectId) as $task) {
			if ($task->sequenceNumber > $max) {
				$max = $task->sequenceNumber;
			}
		}
		return $max + 1;
	}

	/** @return Iterator<Task> */
	public function findByProject(int $projectId): Iterator
	{
		return $this->select()
			->where(['project_id' => $projectId])
			->orderBy('status_id', 'ASC')
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	/** @return Iterator<Task> */
	public function findByStatus(int $statusId): Iterator
	{
		return $this->select()
			->where(['status_id' => $statusId])
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $assigneeIds
	 * @param list<int>|null $taskIdsFilter restrict to these IDs; pass [] to force an empty result
	 * @return Iterator<Task>
	 */
	public function findInWorkspace(
		int $workspaceId,
		int $limit,
		int $offset,
		TaskOrderByEnum $orderBy,
		OrderDirectionEnum $direction,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $taskIdsFilter = null,
		?array $assigneeIds = null,
	): Iterator {
		if ($taskIdsFilter !== null && $taskIdsFilter === []) {
			return new EmptyIterator();
		}

		$select = $this->buildWorkspaceSelect($workspaceId, $search, $statusIds, $onlyActive, $taskIdsFilter, $assigneeIds);

		$select->orderBy($orderBy->value, $direction->value);

		// Secondary deterministic order so equal-key rows stay stable across pages.
		if ($orderBy !== TaskOrderByEnum::CreatedAt) {
			$select->orderBy('created_at', OrderDirectionEnum::Desc->value);
		}
		$select->orderBy('id', OrderDirectionEnum::Desc->value);

		return $select
			->limit($limit)
			->offset($offset)
			->fetchAll();
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $assigneeIds
	 * @param list<int>|null $taskIdsFilter
	 */
	public function countInWorkspace(
		int $workspaceId,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $taskIdsFilter = null,
		?array $assigneeIds = null,
	): int {
		if ($taskIdsFilter !== null && $taskIdsFilter === []) {
			return 0;
		}
		return $this->buildWorkspaceSelect($workspaceId, $search, $statusIds, $onlyActive, $taskIdsFilter, $assigneeIds)->count();
	}

	/** @return Iterator<Task> */
	public function findByAssigneeInWorkspace(int $userId, int $workspaceId): Iterator
	{
		return $this->select()
			->where(['assignee_id' => $userId])
			->where(['project.workspace_id' => $workspaceId])
			->fetchAll();
	}

	/**
	 * @param list<int>|null $statusIds
	 * @param list<int>|null $assigneeIds
	 * @param list<int>|null $taskIdsFilter
	 * @return Select<Task>
	 */
	private function buildWorkspaceSelect(
		int $workspaceId,
		?string $search,
		?array $statusIds,
		bool $onlyActive,
		?array $taskIdsFilter = null,
		?array $assigneeIds = null,
	): Select {
		$select = $this->select()
			->where(['project.workspace_id' => $workspaceId]);

		if ($search !== null && $search !== '') {
			$select->where(['name', 'LIKE', '%' . $search . '%']);
		}
		if ($statusIds !== null && $statusIds !== []) {
			$select->where(['status_id', 'IN', $statusIds]);
		}
		if ($onlyActive) {
			$select->where(['status.type', '!=', StatusTypeEnum::Finish]);
		}
		if ($taskIdsFilter !== null && $taskIdsFilter !== []) {
			$select->where(['id', 'IN', $taskIdsFilter]);
		}
		if ($assigneeIds !== null && $assigneeIds !== []) {
			$select->where(['assignee_id', 'IN', $assigneeIds]);
		}

		return $select;
	}
}
