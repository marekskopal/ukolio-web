<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\ScriptVariable;

/** @extends ARepository<ScriptVariable> */
final class ScriptVariableRepository extends ARepository
{
	/** @return list<ScriptVariable> */
	public function findByWorkspace(int $workspaceId): array
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('key', 'ASC')
			->fetchAll();
	}

	public function findOneByWorkspaceAndKey(int $workspaceId, string $key): ?ScriptVariable
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'key' => $key]);
	}

	public function findOneByWorkspaceAndId(int $workspaceId, int $id): ?ScriptVariable
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $id]);
	}
}
