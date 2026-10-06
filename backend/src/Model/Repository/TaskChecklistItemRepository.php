<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\TaskChecklistItem;

/** @extends ARepository<TaskChecklistItem> */
final class TaskChecklistItemRepository extends ARepository
{
	/** @return list<TaskChecklistItem> */
	public function findByTask(int $taskId): array
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->orderBy('position', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findOneById(int $id): ?TaskChecklistItem
	{
		return $this->findOne(['id' => $id]);
	}

	/**
	 * @param list<int> $taskIds
	 * @return list<TaskChecklistItem>
	 */
	public function findByTasks(array $taskIds): array
	{
		if ($taskIds === []) {
			return [];
		}

		return $this->select()
			->where(['task_id', 'IN', $taskIds])
			->fetchAll();
	}
}
