<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Iterator;
use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Project;

/** @extends AbstractRepository<Project> */
final class ProjectRepository extends AbstractRepository
{
	/** @return Iterator<Project> */
	public function findProjectsByWorkspace(int $workspaceId): Iterator
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('id', 'DESC')
			->fetchAll();
	}

	public function findProject(int $workspaceId, int $projectId): ?Project
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $projectId]);
	}
}
