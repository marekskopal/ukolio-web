<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\ProjectField;

/** @extends ARepository<ProjectField> */
final class ProjectFieldRepository extends ARepository
{
	/** @return list<ProjectField> */
	public function findByProject(int $projectId): array
	{
		return $this->select()
			->where(['project_id' => $projectId])
			->orderBy('position', 'ASC')
			->fetchAll();
	}

	/** @return list<ProjectField> */
	public function findByField(int $fieldId): array
	{
		return $this->select()
			->where(['field_id' => $fieldId])
			->fetchAll();
	}
}
