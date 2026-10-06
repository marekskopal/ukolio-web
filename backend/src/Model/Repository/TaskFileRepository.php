<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\TaskFile;

/** @extends ARepository<TaskFile> */
final class TaskFileRepository extends ARepository
{
	/** @return list<TaskFile> */
	public function findByTask(int $taskId): array
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findOneById(int $id): ?TaskFile
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return list<TaskFile> */
	public function findByUploader(int $userId): array
	{
		return $this->select()
			->where(['uploaded_by_user_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
