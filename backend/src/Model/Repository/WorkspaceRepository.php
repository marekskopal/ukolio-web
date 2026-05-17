<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\Workspace;

/** @extends AbstractRepository<Workspace> */
final class WorkspaceRepository extends AbstractRepository
{
	public function findWorkspaceById(int $workspaceId): ?Workspace
	{
		return $this->findOne(['id' => $workspaceId]);
	}
}
