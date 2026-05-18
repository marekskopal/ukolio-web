<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Enum\TaskRelationTypeEnum;
use Ukolio\Model\Entity\TaskRelation;

/** @extends AbstractRepository<TaskRelation> */
class TaskRelationRepository extends AbstractRepository
{
	public function findOneById(int $id): ?TaskRelation
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return Iterator<TaskRelation> */
	public function findOutgoing(int $taskId): Iterator
	{
		return $this->select()
			->where(['source_task_id' => $taskId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	/** @return Iterator<TaskRelation> */
	public function findIncoming(int $taskId): Iterator
	{
		return $this->select()
			->where(['target_task_id' => $taskId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findPair(int $sourceTaskId, int $targetTaskId, TaskRelationTypeEnum $type): ?TaskRelation
	{
		return $this->findOne([
			'source_task_id' => $sourceTaskId,
			'target_task_id' => $targetTaskId,
			'type' => $type->value,
		]);
	}

	/** @return Iterator<TaskRelation> */
	public function findOutgoingByType(int $taskId, TaskRelationTypeEnum $type): Iterator
	{
		return $this->select()
			->where(['source_task_id' => $taskId, 'type' => $type->value])
			->fetchAll();
	}

	/** @return Iterator<TaskRelation> */
	public function findByCreatedBy(int $userId): Iterator
	{
		return $this->select()
			->where(['created_by_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
