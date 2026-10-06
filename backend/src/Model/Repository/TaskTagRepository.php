<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\TaskTag;

/** @extends AbstractRepository<TaskTag> */
final class TaskTagRepository extends AbstractRepository
{
	/** @return list<TaskTag> */
	public function findByTask(int $taskId): array
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->fetchAll();
	}

	/**
	 * @param list<int> $tagIds
	 * @return list<int> distinct task ids that have ANY of the given tags
	 */
	public function findTaskIdsByTagIds(array $tagIds): array
	{
		if ($tagIds === []) {
			return [];
		}
		$result = [];
		foreach ($this->select()->where(['tag_id', 'IN', $tagIds])->fetchAll() as $row) {
			$result[$row->task->id] = true;
		}
		return array_keys($result);
	}
}
