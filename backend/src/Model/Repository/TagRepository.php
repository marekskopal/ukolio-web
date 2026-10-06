<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\Tag;

/** @extends ARepository<Tag> */
final class TagRepository extends ARepository
{
	/** @return list<Tag> */
	public function findByWorkspace(int $workspaceId): array
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('name', 'ASC')
			->fetchAll();
	}

	public function findOneByWorkspaceAndId(int $workspaceId, int $tagId): ?Tag
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $tagId]);
	}

	public function findOneByWorkspaceAndName(int $workspaceId, string $name): ?Tag
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'name' => $name]);
	}
}
