<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\TaskWatcher;

/** @extends ARepository<TaskWatcher> */
final class TaskWatcherRepository extends ARepository
{
	/** @return list<TaskWatcher> */
	public function findByTask(int $taskId): array
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findByTaskAndUser(int $taskId, int $userId): ?TaskWatcher
	{
		return $this->findOne(['task_id' => $taskId, 'user_id' => $userId]);
	}
}
