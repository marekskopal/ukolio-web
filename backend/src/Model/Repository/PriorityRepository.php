<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\Priority;

/** @extends ARepository<Priority> */
final class PriorityRepository extends ARepository
{
	/** @return list<Priority> */
	public function findByWorkspace(int $workspaceId): array
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	public function findOneByWorkspaceAndId(int $workspaceId, int $priorityId): ?Priority
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $priorityId]);
	}

	public function findOneByWorkspaceAndName(int $workspaceId, string $name): ?Priority
	{
		foreach ($this->findByWorkspace($workspaceId) as $priority) {
			if (mb_strtolower($priority->name) === mb_strtolower(trim($name))) {
				return $priority;
			}
		}
		return null;
	}

	public function findDefaultForWorkspace(int $workspaceId): ?Priority
	{
		foreach ($this->findByWorkspace($workspaceId) as $priority) {
			if ($priority->isDefault) {
				return $priority;
			}
		}
		return null;
	}
}
