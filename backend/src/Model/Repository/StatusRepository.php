<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\Status;

/** @extends ARepository<Status> */
final class StatusRepository extends ARepository
{
	public function findById(int $statusId): ?Status
	{
		return $this->findOne(['id' => $statusId]);
	}

	/** @return list<Status> */
	public function findByWorkflow(int $workflowId): array
	{
		return $this->select()
			->where(['workflow_id' => $workflowId])
			->orderBy('position', 'ASC')
			->fetchAll();
	}
}
