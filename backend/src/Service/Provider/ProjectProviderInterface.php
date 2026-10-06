<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;

interface ProjectProviderInterface
{
	/** @return list<Project> */
	public function getProjects(Workspace $workspace): array;

	public function getProject(Workspace $workspace, int $projectId): ?Project;

	public function createProject(User $author, Workspace $workspace, string $name, ?string $description): Project;

	public function updateProject(User $author, Project $project, string $name, ?string $description): Project;

	public function deleteProject(Project $project): void;
}
