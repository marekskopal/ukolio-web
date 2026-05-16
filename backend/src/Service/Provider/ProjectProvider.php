<?php

declare(strict_types=1);

namespace TaskManager\Service\Provider;

use DateTimeImmutable;
use Iterator;
use TaskManager\Model\Entity\Enum\EventTypeEnum;
use TaskManager\Model\Entity\Project;
use TaskManager\Model\Entity\User;
use TaskManager\Model\Repository\ProjectRepository;

final readonly class ProjectProvider implements ProjectProviderInterface
{
    public function __construct(
        private ProjectRepository $projectRepository,
        private WorkflowProviderInterface $workflowProvider,
        private EventProviderInterface $eventProvider,
    ) {
    }

    /** @return Iterator<Project> */
    public function getProjects(User $user): Iterator
    {
        return $this->projectRepository->findProjectsByUser($user->id);
    }

    public function getProject(User $user, int $projectId): ?Project
    {
        return $this->projectRepository->findProject($user->id, $projectId);
    }

    public function createProject(User $user, string $name, ?string $description): Project
    {
        $now = new DateTimeImmutable();
        $project = new Project(user: $user, name: $name, description: $description);
        $project->createdAt = $now;
        $project->updatedAt = $now;

        $this->projectRepository->persist($project);

        $this->workflowProvider->createDefaultWorkflow($project);

        $this->eventProvider->recordEvent($user, $project, EventTypeEnum::ProjectCreated, ['name' => $name]);

        return $project;
    }

    public function updateProject(Project $project, string $name, ?string $description): Project
    {
        $project->name = $name;
        $project->description = $description;
        $project->updatedAt = new DateTimeImmutable();
        $this->projectRepository->persist($project);

        $this->eventProvider->recordEvent($project->user, $project, EventTypeEnum::ProjectUpdated, ['name' => $name]);

        return $project;
    }

    public function deleteProject(Project $project): void
    {
        $this->projectRepository->delete($project);
    }
}
