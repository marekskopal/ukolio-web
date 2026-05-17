<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Iterator;
use Ukolio\Model\Entity\Enum\EventTypeEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\ProjectRepository;

final readonly class ProjectProvider implements ProjectProviderInterface
{
	public function __construct(
		private ProjectRepository $projectRepository,
		private WorkflowProviderInterface $workflowProvider,
		private EventProviderInterface $eventProvider,
	) {
	}

	/** @return Iterator<Project> */
	public function getProjects(Workspace $workspace): Iterator
	{
		return $this->projectRepository->findProjectsByWorkspace($workspace->id);
	}

	public function getProject(Workspace $workspace, int $projectId): ?Project
	{
		return $this->projectRepository->findProject($workspace->id, $projectId);
	}

	public function createProject(User $author, Workspace $workspace, string $name, ?string $description): Project
	{
		$now = new DateTimeImmutable();
		$project = new Project(workspace: $workspace, name: $name, description: $description);
		$project->createdAt = $now;
		$project->updatedAt = $now;

		$this->projectRepository->persist($project);

		$this->workflowProvider->createDefaultWorkflow($project);

		$this->eventProvider->recordEvent($author, $project, EventTypeEnum::ProjectCreated, ['name' => $name]);

		return $project;
	}

	public function updateProject(User $author, Project $project, string $name, ?string $description): Project
	{
		$project->name = $name;
		$project->description = $description;
		$project->updatedAt = new DateTimeImmutable();
		$this->projectRepository->persist($project);

		$this->eventProvider->recordEvent($author, $project, EventTypeEnum::ProjectUpdated, ['name' => $name]);

		return $project;
	}

	public function deleteProject(Project $project): void
	{
		$this->projectRepository->delete($project);
	}
}
