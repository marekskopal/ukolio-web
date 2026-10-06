<?php

declare(strict_types=1);

namespace Ukolio\Model\Repository;

use Ukolio\Model\Entity\Project;

/** @extends ARepository<Project> */
final class ProjectRepository extends ARepository
{
	/** @return list<Project> */
	public function findProjectsByWorkspace(int $workspaceId): array
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->orderBy('id', 'DESC')
			->fetchAll();
	}

	public function countByWorkspace(int $workspaceId): int
	{
		return $this->select()
			->where(['workspace_id' => $workspaceId])
			->count();
	}

	public function findProject(int $workspaceId, int $projectId): ?Project
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'id' => $projectId]);
	}

	public function findByWorkspaceAndPrefix(int $workspaceId, string $prefix): ?Project
	{
		return $this->findOne(['workspace_id' => $workspaceId, 'prefix' => $prefix]);
	}

	/** @return list<string> */
	public function findPrefixesInWorkspace(int $workspaceId, ?int $excludeProjectId): array
	{
		$prefixes = [];
		foreach ($this->findProjectsByWorkspace($workspaceId) as $project) {
			if ($excludeProjectId !== null && $project->id === $excludeProjectId) {
				continue;
			}
			$prefixes[] = $project->prefix;
		}
		return $prefixes;
	}
}
