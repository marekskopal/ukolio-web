<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpProjectDto;
use Ukolio\Mcp\Dto\McpProjectListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Service\Auth\PermissionCheckerInterface;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class ProjectTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private ProjectProviderInterface $projectProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private PermissionCheckerInterface $permissionChecker,
	) {
	}

	/** List all projects belonging to the user's current workspace. */
	#[McpTool(name: 'list_projects', description: 'List all projects in the current workspace')]
	public function listProjects(): McpProjectListDto
	{
		$workspace = $this->requireWorkspace();

		$projects = [];
		foreach ($this->projectProvider->getProjects($workspace) as $project) {
			$projects[] = McpProjectDto::fromEntity($project);
		}

		return new McpProjectListDto($projects);
	}

	/**
	 * Find a project by case-insensitive name match. Returns null if not found.
	 * Use this before creating a project to avoid duplicates.
	 *
	 * @param string $name Project name to search for (case-insensitive, exact match)
	 */
	#[McpTool(
		name: 'find_project_by_name',
		description: 'Find a project in the current workspace by name (case-insensitive, exact match). Returns null if not found.',
	)]
	public function findProjectByName(string $name): ?McpProjectDto
	{
		$workspace = $this->requireWorkspace();
		$needle = mb_strtolower($name);
		foreach ($this->projectProvider->getProjects($workspace) as $project) {
			if (mb_strtolower($project->name) === $needle) {
				return McpProjectDto::fromEntity($project);
			}
		}

		return null;
	}

	/**
	 * Get a single project by ID.
	 *
	 * @param int $projectId Project ID
	 */
	#[McpTool(name: 'get_project', description: 'Get a single project by ID')]
	public function getProject(int $projectId): McpProjectDto
	{
		$workspace = $this->requireWorkspace();
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}

		return McpProjectDto::fromEntity($project);
	}

	/**
	 * Create a new project in the current workspace. A default workflow with statuses "To Do", "In Progress", "Done"
	 * is automatically created. Call find_project_by_name first to avoid duplicates.
	 *
	 * @param string $name Project name
	 * @param string|null $description Optional project description
	 */
	#[McpTool(
		name: 'create_project',
		description: 'Create a new project in the current workspace with the default To Do / In Progress / Done workflow',
	)]
	public function createProject(string $name, ?string $description = null): McpProjectDto
	{
		$workspace = $this->requireWorkspace();
		$project = $this->projectProvider->createProject($this->userContext->getUser(), $workspace, $name, $description);

		return McpProjectDto::fromEntity($project);
	}

	/**
	 * Rename a project and/or change its description. Omitted parameters keep their current value; pass an empty
	 * description to clear it. Renaming regenerates the project prefix from the new name, which changes the task
	 * codes (e.g. "OLD-12" → "NEW-12") — the returned project carries the new prefix.
	 *
	 * @param int $projectId Project ID
	 * @param string|null $name New project name (omit to keep the current name)
	 * @param string|null $description New description in markdown (omit to keep, empty string to clear)
	 */
	#[McpTool(
		name: 'update_project',
		description: 'Rename a project and/or change its description. Renaming regenerates the project prefix used in task codes.',
	)]
	public function updateProject(int $projectId, ?string $name = null, ?string $description = null): McpProjectDto
	{
		$workspace = $this->requireWorkspace();
		$user = $this->userContext->getUser();
		if (!$this->permissionChecker->canManageProjects($user, $workspace)) {
			throw new RuntimeException('You do not have permission to update projects.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}

		$newDescription = match (true) {
			$description === null => $project->description,
			trim($description) === '' => null,
			default => $description,
		};

		$project = $this->projectProvider->updateProject($user, $project, $name ?? $project->name, $newDescription);

		return McpProjectDto::fromEntity($project);
	}

	/**
	 * Delete a project and all its tasks and workflow data.
	 *
	 * @param int $projectId Project ID
	 */
	#[McpTool(name: 'delete_project', description: 'Delete a project (irreversible — removes all its tasks)')]
	public function deleteProject(int $projectId): string
	{
		$workspace = $this->requireWorkspace();
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}

		$this->projectProvider->deleteProject($project);

		return 'Project deleted.';
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace. Create one in the Ukolio app first.');
		}

		return $workspace;
	}
}
