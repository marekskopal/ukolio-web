<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\Enum\TaskRelationTypeEnum;
use Ukolio\Model\Entity\TaskRelation;

/** @extends ARepository<TaskRelation> */
class TaskRelationRepository extends ARepository
{
	public function findOneById(int $id): ?TaskRelation
	{
		return $this->findOne(['id' => $id]);
	}

	/** @return list<TaskRelation> */
	public function findOutgoing(int $taskId): array
	{
		return $this->select()
			->where(['source_task_id' => $taskId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	/** @return list<TaskRelation> */
	public function findIncoming(int $taskId): array
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

	/** @return list<TaskRelation> */
	public function findOutgoingByType(int $taskId, TaskRelationTypeEnum $type): array
	{
		return $this->select()
			->where(['source_task_id' => $taskId, 'type' => $type->value])
			->fetchAll();
	}

	/**
	 * @param list<int> $sourceTaskIds
	 * @return list<TaskRelation>
	 */
	public function findByTypeAndSources(TaskRelationTypeEnum $type, array $sourceTaskIds): array
	{
		if ($sourceTaskIds === []) {
			return [];
		}
		return $this->select()
			->where(['type' => $type->value])
			->where(['source_task_id', 'IN', $sourceTaskIds])
			->fetchAll();
	}

	/**
	 * All parent/child task ids participating in a Parent relation within a workspace.
	 *
	 * @return array{parents: list<int>, children: list<int>}
	 */
	public function findParentChildIdsInWorkspace(int $workspaceId): array
	{
		$parents = [];
		$children = [];
		$relations = $this->select()
			->where(['type' => TaskRelationTypeEnum::Parent->value])
			->where(['sourceTask.project.workspace_id' => $workspaceId])
			->fetchAll();
		foreach ($relations as $relation) {
			$parents[$relation->sourceTask->id] = true;
			$children[$relation->targetTask->id] = true;
		}

		return ['parents' => array_keys($parents), 'children' => array_keys($children)];
	}

	/** @return list<TaskRelation> */
	public function findByCreatedBy(int $userId): array
	{
		return $this->select()
			->where(['created_by_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
