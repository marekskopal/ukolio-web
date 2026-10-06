<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\Field;

/** @extends ARepository<Field> */
final class FieldRepository extends ARepository
{
	/** @return list<Field> */
	public function findByWorkspace(int $workspaceId): array
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('name', 'ASC')
			->fetchAll();
	}

	public function findOneByWorkspaceAndId(int $workspaceId, int $fieldId): ?Field
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $fieldId]);
	}

	public function findOneByWorkspaceAndName(int $workspaceId, string $name): ?Field
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'name' => $name]);
	}
}
