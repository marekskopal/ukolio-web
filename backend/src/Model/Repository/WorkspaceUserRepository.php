<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use MarekSkopal\ORM\Repository\AbstractRepository;
use Ukolio\Model\Entity\WorkspaceUser;

/** @extends AbstractRepository<WorkspaceUser> */
final class WorkspaceUserRepository extends AbstractRepository
{
	/** @return list<WorkspaceUser> */
	public function findByUser(int $userId): array
	{
		return $this->select()
			->where(['user_id' => $userId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	/** @return list<WorkspaceUser> */
	public function findByWorkspace(int $workspaceId): array
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('id', 'ASC')
			->fetchAll();
	}

	public function findMembership(int $userId, int $workspaceId): ?WorkspaceUser
	{
		return $this->findOne(['user_id' => $userId, 'workspace_id' => $workspaceId]);
	}
}
