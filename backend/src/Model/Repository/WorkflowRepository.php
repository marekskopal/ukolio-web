<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\Workflow;

/** @extends ARepository<Workflow> */
final class WorkflowRepository extends ARepository
{
	public function findById(int $workflowId): ?Workflow
	{
		return $this->findOne(['id' => $workflowId]);
	}

	public function findByProject(int $projectId): ?Workflow
	{
		return $this->findOne(['project_id' => $projectId]);
	}

	/** @return list<Workflow> */
	public function findByWorkspace(int $workspaceId): array
	{
		return $this->select()
			->where(['project.workspace_id' => $workspaceId])
			->orderBy('project.name', 'ASC')
			->orderBy('id', 'ASC')
			->fetchAll();
	}
}
