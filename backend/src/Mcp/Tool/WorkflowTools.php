<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpStatusDto;
use Ukolio\Mcp\Dto\McpStatusListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Workflow;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\WorkflowProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class WorkflowTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private ProjectProviderInterface $projectProvider,
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * List all statuses (Kanban columns) for a project's workflow, ordered by position.
	 * Each status has a type: Start, Normal, or Finish — Start is the initial column for new tasks,
	 * Finish marks completion. The default workflow is "To Do" (Start), "In Progress" (Normal), "Done" (Finish).
	 *
	 * @param int $projectId Project ID
	 */
	#[McpTool(name: 'list_statuses', description: 'List workflow statuses (columns) for a project, ordered by position')]
	public function listStatuses(int $projectId): McpStatusListDto
	{
		$workflow = $this->resolveWorkflow($projectId);

		$statuses = [];
		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			$statuses[] = McpStatusDto::fromEntity($status);
		}

		return new McpStatusListDto($statuses);
	}

	/**
	 * Find a status in a project's workflow by name (case-insensitive, exact match). Returns null if not found.
	 *
	 * @param int $projectId Project ID
	 * @param string $name Status name to look up (e.g. "In Progress")
	 */
	#[McpTool(name: 'find_status_by_name', description: 'Find a workflow status by name within a project (case-insensitive, exact match)')]
	public function findStatusByName(int $projectId, string $name): ?McpStatusDto
	{
		$workflow = $this->resolveWorkflow($projectId);

		$needle = mb_strtolower($name);
		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			if (mb_strtolower($status->name) === $needle) {
				return McpStatusDto::fromEntity($status);
			}
		}

		return null;
	}

	private function resolveWorkflow(int $projectId): Workflow
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}

		$workflow = $this->workflowProvider->getWorkflowByProject($project);
		if ($workflow === null) {
			throw new RuntimeException(sprintf('Workflow for project %d not found.', $projectId));
		}

		return $workflow;
	}
}
