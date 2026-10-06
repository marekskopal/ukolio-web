<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\TaskFieldValue;

/** @extends AbstractRepository<TaskFieldValue> */
final class TaskFieldValueRepository extends AbstractRepository
{
	/** @return list<TaskFieldValue> */
	public function findByTask(int $taskId): array
	{
		return $this->select()
			->where(['task_id' => $taskId])
			->fetchAll();
	}

	public function findOneByTaskAndField(int $taskId, int $fieldId): ?TaskFieldValue
	{
		return $this->findOne(['task_id' => $taskId, 'field_id' => $fieldId]);
	}

	/** @return list<TaskFieldValue> */
	public function findByField(int $fieldId): array
	{
		return $this->select()
			->where(['field_id' => $fieldId])
			->fetchAll();
	}
}
