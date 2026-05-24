<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use DateTimeImmutable;
use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpTaskDto;
use Ukolio\Mcp\Dto\McpTaskListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Enum\TaskPriorityEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Status;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Entity\Workspace;
use Ukolio\Model\Repository\UserRepository;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskFieldValueProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskTagProviderInterface;
use Ukolio\Service\Provider\WorkflowProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class TaskTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private ProjectProviderInterface $projectProvider,
		private WorkflowProviderInterface $workflowProvider,
		private StatusProviderInterface $statusProvider,
		private TaskProviderInterface $taskProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
		private WorkspaceProviderInterface $workspaceProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private UserRepository $userRepository,
	) {
	}

	/**
	 * List all tasks in a project, ordered by status then position. Optionally filter by status.
	 *
	 * @param int $projectId Project ID
	 * @param int|null $statusId Optional: only return tasks in this status
	 */
	#[McpTool(name: 'list_tasks', description: 'List tasks in a project, optionally filtered by status')]
	public function listTasks(int $projectId, ?int $statusId = null): McpTaskListDto
	{
		$project = $this->requireProject($projectId);

		$tasks = [];
		foreach ($this->taskProvider->getTasksByProject($project) as $task) {
			if ($statusId !== null && $task->status->id !== $statusId) {
				continue;
			}
			$tasks[] = McpTaskDto::fromEntity(
				$task,
				$this->taskFieldValueProvider->findByTask($task),
				$this->taskTagProvider->getTagIdsForTask($task),
			);
		}

		return new McpTaskListDto($tasks);
	}

	/**
	 * Find a task by case-insensitive name match within a project. Returns the first match,
	 * preferring exact matches over substring matches.
	 *
	 * @param int $projectId Project ID
	 * @param string $name Task name to search for
	 */
	#[McpTool(
		name: 'find_task_by_name',
		description: 'Find a task in a project by name (case-insensitive). Prefers exact matches over substring matches.',
	)]
	public function findTaskByName(int $projectId, string $name): ?McpTaskDto
	{
		$project = $this->requireProject($projectId);
		$needle = mb_strtolower($name);

		$exact = null;
		$partial = null;
		foreach ($this->taskProvider->getTasksByProject($project) as $task) {
			$haystack = mb_strtolower($task->name);
			if ($haystack === $needle) {
				$exact = $task;
				break;
			}
			if ($partial === null && str_contains($haystack, $needle)) {
				$partial = $task;
			}
		}

		$found = $exact ?? $partial;

		return $found !== null
			? McpTaskDto::fromEntity(
				$found,
				$this->taskFieldValueProvider->findByTask($found),
				$this->taskTagProvider->getTagIdsForTask($found),
			)
			: null;
	}

	/**
	 * Get a single task by numeric ID or by code (e.g. "MP-3").
	 *
	 * @param int|string $taskId Task ID or code
	 */
	#[McpTool(name: 'get_task', description: 'Get a single task by numeric ID or code (e.g. "MP-3")')]
	public function getTask(int|string $taskId): McpTaskDto
	{
		$task = $this->requireTask($taskId);
		return McpTaskDto::fromEntity(
			$task,
			$this->taskFieldValueProvider->findByTask($task),
			$this->taskTagProvider->getTagIdsForTask($task),
		);
	}

	/**
	 * Create a new task. By default it lands in the project's Start status (e.g. "To Do").
	 * Provide statusId or statusName to put it in a different column.
	 *
	 * @param int $projectId Project ID
	 * @param string $name Task name
	 * @param string|null $description Optional markdown description
	 * @param string $priority Priority: Low, Medium (default), or High
	 * @param int|null $statusId Optional explicit status ID
	 * @param string|null $statusName Optional status name (case-insensitive); ignored if statusId is given
	 * @param string|null $dueDate Optional due date (YYYY-MM-DD)
	 * @param int|null $assigneeId Optional user ID to assign. Defaults to the current MCP user. Must be a member of the project's workspace.
	 * @param array<array{fieldId: int, value: ?string}>|null $fieldValues Optional custom-field values keyed by fieldId
	 * @param list<int>|null $tagIds Optional list of workspace tag IDs to apply to the new task
	 */
	#[McpTool(name: 'create_task', description: 'Create a task in a project. Lands in Start status by default.')]
	public function createTask(
		int $projectId,
		string $name,
		?string $description = null,
		string $priority = 'Medium',
		?int $statusId = null,
		?string $statusName = null,
		?string $dueDate = null,
		?int $assigneeId = null,
		?array $fieldValues = null,
		?array $tagIds = null,
	): McpTaskDto {
		$user = $this->userContext->getUser();
		$project = $this->requireProject($projectId);
		$priorityEnum = $this->parsePriority($priority);
		$status = $this->resolveStatus($project, $statusId, $statusName)
			?? $this->findStatusByType($project, StatusTypeEnum::Start)
			?? throw new RuntimeException(sprintf('No Start status found for project %d.', $projectId));

		$assignee = $assigneeId !== null ? $this->resolveAssignee($project, $assigneeId) : $user;

		$task = $this->taskProvider->createTask(
			author: $user,
			project: $project,
			status: $status,
			name: $name,
			description: $description,
			priority: $priorityEnum,
			dueDate: $dueDate !== null ? new DateTimeImmutable($dueDate) : null,
			assignee: $assignee,
			fieldValues: $this->normalizeFieldValues($fieldValues),
			tagIds: $tagIds,
		);

		return McpTaskDto::fromEntity(
			$task,
			$this->taskFieldValueProvider->findByTask($task),
			$this->taskTagProvider->getTagIdsForTask($task),
		);
	}

	/**
	 * Update a task's editable fields. Omitted parameters are left unchanged.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 * @param string|null $name New name
	 * @param string|null $description New description
	 * @param string|null $priority New priority: Low, Medium, or High
	 * @param string|null $dueDate New due date (YYYY-MM-DD), or empty string to clear
	 * @param int|null $assigneeId New assignee user ID. Pass null to clear (unassign). Omit the parameter to leave unchanged. Must be a member of the project's workspace.
	 * @param bool $clearAssignee Pass true together with omitting assigneeId to explicitly unassign the task.
	 * @param array<array{fieldId: int, value: ?string}>|null $fieldValues Optional custom-field values to replace
	 * @param list<int>|null $tagIds Optional list of workspace tag IDs to apply (replaces the full set)
	 */
	#[McpTool(name: 'update_task', description: 'Update a task. Use move_task to change status.')]
	public function updateTask(
		int|string $taskId,
		?string $name = null,
		?string $description = null,
		?string $priority = null,
		?string $dueDate = null,
		?int $assigneeId = null,
		bool $clearAssignee = false,
		?array $fieldValues = null,
		?array $tagIds = null,
	): McpTaskDto {
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$newDueDate = $this->resolveNewDueDate($task->dueDate, $dueDate);
		$assignee = $this->resolveAssigneeForUpdate($task, $assigneeId, $clearAssignee);

		$updated = $this->taskProvider->updateTask(
			author: $user,
			task: $task,
			name: $name ?? $task->name,
			description: $description ?? $task->description,
			priority: $priority !== null ? $this->parsePriority($priority) : $task->priority,
			dueDate: $newDueDate,
			status: $task->status,
			assignee: $assignee,
			fieldValues: $this->normalizeFieldValues($fieldValues),
			tagIds: $tagIds,
		);

		return McpTaskDto::fromEntity(
			$updated,
			$this->taskFieldValueProvider->findByTask($updated),
			$this->taskTagProvider->getTagIdsForTask($updated),
		);
	}

	/**
	 * Move a task to a different status (column). Provide either statusId or statusName.
	 * The task is appended to the end of the destination column.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 * @param int|null $statusId Target status ID
	 * @param string|null $statusName Target status name (case-insensitive); ignored if statusId is given
	 */
	#[McpTool(name: 'move_task', description: 'Move a task to a different status. Appends to the end of the destination column.')]
	public function moveTask(int|string $taskId, ?int $statusId = null, ?string $statusName = null): McpTaskDto
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);
		$status = $this->resolveStatus($task->project, $statusId, $statusName);
		if ($status === null) {
			throw new RuntimeException('Provide statusId or statusName to move the task.');
		}

		$position = $this->nextPositionInStatus($status->id);
		$moved = $this->taskProvider->moveTask($user, $task, $status, $position);

		return McpTaskDto::fromEntity(
			$moved,
			$this->taskFieldValueProvider->findByTask($moved),
			$this->taskTagProvider->getTagIdsForTask($moved),
		);
	}

	/**
	 * Delete a task.
	 *
	 * @param int|string $taskId Task ID or code (e.g. "MP-3")
	 */
	#[McpTool(name: 'delete_task', description: 'Delete a task (irreversible)')]
	public function deleteTask(int|string $taskId): string
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$this->taskProvider->deleteTask($user, $task);

		return 'Task deleted.';
	}

	private function requireWorkspace(): Workspace
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->userContext->getUser());
		if ($workspace === null) {
			throw new RuntimeException('No active workspace.');
		}

		return $workspace;
	}

	private function requireProject(int $projectId): Project
	{
		$workspace = $this->requireWorkspace();
		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			throw new RuntimeException(sprintf('Project %d not found.', $projectId));
		}

		return $project;
	}

	private function requireTask(int|string $taskId): Task
	{
		$task = $this->taskCodeResolver->resolveForUser($this->userContext->getUser(), (string) $taskId);
		if ($task === null) {
			throw new RuntimeException(sprintf('Task "%s" not found.', (string) $taskId));
		}
		return $task;
	}

	private function resolveAssignee(Project $project, int $assigneeId): User
	{
		$assignee = $this->userRepository->findUserById($assigneeId);
		if ($assignee === null || !$this->workspaceProvider->isMember($assignee, $project->workspace)) {
			throw new RuntimeException(sprintf(
				'Assignee user %d must be a member of the project\'s workspace.',
				$assigneeId,
			));
		}

		return $assignee;
	}

	private function resolveAssigneeForUpdate(Task $task, ?int $assigneeId, bool $clearAssignee): ?User
	{
		if ($assigneeId !== null) {
			return $this->resolveAssignee($task->project, $assigneeId);
		}
		if ($clearAssignee) {
			return null;
		}
		return $task->assignee;
	}

	private function resolveNewDueDate(?DateTimeImmutable $current, ?string $dueDate): ?DateTimeImmutable
	{
		if ($dueDate === null) {
			return $current;
		}
		return $dueDate === '' ? null : new DateTimeImmutable($dueDate);
	}

	private function parsePriority(string $priority): TaskPriorityEnum
	{
		$enum = TaskPriorityEnum::tryFrom($priority);
		if ($enum === null) {
			throw new RuntimeException(sprintf(
				'Invalid priority "%s". Valid values: %s',
				$priority,
				implode(', ', array_column(TaskPriorityEnum::cases(), 'value')),
			));
		}

		return $enum;
	}

	private function resolveStatus(Project $project, ?int $statusId, ?string $statusName): ?Status
	{
		if ($statusId !== null) {
			return $this->resolveStatusById($project, $statusId);
		}
		if ($statusName !== null) {
			return $this->resolveStatusByName($project, $statusName);
		}
		return null;
	}

	private function resolveStatusById(Project $project, int $statusId): Status
	{
		$status = $this->statusProvider->getStatus($statusId);
		if ($status === null || $status->workflow->project->id !== $project->id) {
			throw new RuntimeException(sprintf('Status %d not found in project %d.', $statusId, $project->id));
		}
		return $status;
	}

	private function resolveStatusByName(Project $project, string $statusName): Status
	{
		$workflow = $this->workflowProvider->getWorkflowByProject($project);
		if ($workflow === null) {
			throw new RuntimeException(sprintf('Workflow for project %d not found.', $project->id));
		}
		$needle = mb_strtolower($statusName);
		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			if (mb_strtolower($status->name) === $needle) {
				return $status;
			}
		}

		throw new RuntimeException(sprintf('Status "%s" not found in project %d.', $statusName, $project->id));
	}

	private function findStatusByType(Project $project, StatusTypeEnum $type): ?Status
	{
		$workflow = $this->workflowProvider->getWorkflowByProject($project);
		if ($workflow === null) {
			return null;
		}

		foreach ($this->statusProvider->getStatuses($workflow) as $status) {
			if ($status->type === $type) {
				return $status;
			}
		}

		return null;
	}

	private function nextPositionInStatus(int $statusId): int
	{
		$max = -1;
		foreach ($this->taskProvider->getTasksByProject($this->resolveAnyProjectForStatus($statusId)) as $task) {
			if ($task->status->id !== $statusId) {
				continue;
			}
			if ($task->position > $max) {
				$max = $task->position;
			}
		}

		return $max + 1;
	}

	private function resolveAnyProjectForStatus(int $statusId): Project
	{
		$status = $this->statusProvider->getStatus($statusId);
		if ($status === null) {
			throw new RuntimeException(sprintf('Status %d not found.', $statusId));
		}

		return $status->workflow->project;
	}

	/**
	 * @param array<array{fieldId: int, value: ?string}>|null $raw
	 * @return array<int, ?string>|null
	 */
	private function normalizeFieldValues(?array $raw): ?array
	{
		return $raw === null ? null : array_column($raw, 'value', 'fieldId');
	}
}
