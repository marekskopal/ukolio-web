<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\Workspace;

/** @extends ARepository<Workspace> */
final class WorkspaceRepository extends ARepository
{
	public function findWorkspaceById(int $workspaceId): ?Workspace
	{
		return $this->findOne(['id' => $workspaceId]);
	}

	/** @return list<Workspace> */
	public function findAllWorkspaces(): array
	{
		return $this->select()->orderBy('id', 'ASC')->fetchAll();
	}

	/** @return list<Workspace> */
	public function findByOwner(int $ownerId): array
	{
		return $this->select()->where(['owner_id' => $ownerId])->fetchAll();
	}
}
