<?php

declare(strict_types=1);

namespace Ukolio\Service\Provider;

use DateTimeImmutable;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Workflow;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\WorkflowRepository;

final readonly class WorkflowProvider implements WorkflowProviderInterface
{
	public function __construct(private WorkflowRepository $workflowRepository, private StatusProviderInterface $statusProvider,)
	{
	}

	public function getWorkflow(int $workflowId): ?Workflow
	{
		return $this->workflowRepository->findById($workflowId);
	}

	public function getWorkflowByProject(Project $project): ?Workflow
	{
		return $this->workflowRepository->findByProject($project->id);
	}

	/** @return list<Workflow> */
	public function getWorkflowsInWorkspace(Workspace $workspace): array
	{
		return $this->workflowRepository->findByWorkspace($workspace->id);
	}

	public function createDefaultWorkflow(Project $project): Workflow
	{
		$now = new DateTimeImmutable();
		$workflow = new Workflow(project: $project, name: 'Default');
		$workflow->createdAt = $now;
		$workflow->updatedAt = $now;

		// The workflow and its statuses are written by seedStatuses()' flush, in one transaction.
		$this->workflowRepository->schedulePersist($workflow);
		$this->statusProvider->seedStatuses($workflow, [
			['name' => 'To Do', 'color' => '#94a3b8', 'type' => StatusTypeEnum::Start],
			['name' => 'In Progress', 'color' => '#fbbf24', 'type' => StatusTypeEnum::Normal],
			['name' => 'Done', 'color' => '#4ade80', 'type' => StatusTypeEnum::Finish],
		]);

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
