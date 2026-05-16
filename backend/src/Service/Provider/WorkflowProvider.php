<?php

declare(strict_types=1);

namespace TaskManager\Service\Provider;

use DateTimeImmutable;
use TaskManager\Model\Entity\Enum\StatusTypeEnum;
use TaskManager\Model\Entity\Project;
use TaskManager\Model\Entity\Workflow;
use TaskManager\Model\Repository\WorkflowRepository;

final readonly class WorkflowProvider implements WorkflowProviderInterface
{
    public function __construct(
        private WorkflowRepository $workflowRepository,
        private StatusProviderInterface $statusProvider,
    ) {
    }

    public function getWorkflow(int $workflowId): ?Workflow
    {
        return $this->workflowRepository->findById($workflowId);
    }

    public function getWorkflowByProject(Project $project): ?Workflow
    {
        return $this->workflowRepository->findByProject($project->id);
    }

    public function createDefaultWorkflow(Project $project): Workflow
    {
        $now = new DateTimeImmutable();
        $workflow = new Workflow(project: $project, name: 'Default');
        $workflow->createdAt = $now;
        $workflow->updatedAt = $now;

        $this->workflowRepository->persist($workflow);

        $this->statusProvider->createStatus($workflow, 'To Do', '#94a3b8', StatusTypeEnum::Start, 0);
        $this->statusProvider->createStatus($workflow, 'In Progress', '#fbbf24', StatusTypeEnum::Normal, 1);
        $this->statusProvider->createStatus($workflow, 'Done', '#4ade80', StatusTypeEnum::Finish, 2);

        return $workflow;
    }

    public function updateWorkflow(Workflow $workflow, string $name): Workflow
    {
        $workflow->name = $name;
        $workflow->updatedAt = new DateTimeImmutable();
        $this->workflowRepository->persist($workflow);

        return $workflow;
    }
}
