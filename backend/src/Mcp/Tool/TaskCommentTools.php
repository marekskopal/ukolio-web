<?php

declare(strict_types=1);

namespace Ukolio\Mcp\Tool;

use Mcp\Capability\Attribute\McpTool;
use RuntimeException;
use Ukolio\Mcp\Dto\McpTaskCommentDto;
use Ukolio\Mcp\Dto\McpTaskCommentListDto;
use Ukolio\Mcp\McpUserContextInterface;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\TaskComment;
use Ukolio\Service\Provider\TaskCommentProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;

final readonly class TaskCommentTools
{
	public function __construct(
		private McpUserContextInterface $userContext,
		private TaskProviderInterface $taskProvider,
		private TaskCommentProviderInterface $taskCommentProvider,
		private WorkspaceProviderInterface $workspaceProvider,
	) {
	}

	/**
	 * List comments on a task in chronological order.
	 *
	 * @param int $taskId Task ID
	 */
	#[McpTool(name: 'list_task_comments', description: 'List comments on a task in chronological order.')]
	public function listTaskComments(int $taskId): McpTaskCommentListDto
	{
		$task = $this->requireTask($taskId);
		$comments = array_map(
			static fn (TaskComment $c): McpTaskCommentDto => McpTaskCommentDto::fromEntity($c),
			$this->taskCommentProvider->findByTask($task),
		);
		return new McpTaskCommentListDto($comments);
	}

	/**
	 * Add a comment to a task. The comment is tagged as agent-authored
	 * and the MCP client name appears next to it in the UI.
	 *
	 * @param int $taskId Task ID
	 * @param string $body Markdown-formatted comment body (non-empty, max 10000 characters)
	 */
	#[McpTool(name: 'add_task_comment', description: 'Add a comment to a task (agent-tagged).')]
	public function addTaskComment(int $taskId, string $body): McpTaskCommentDto
	{
		$user = $this->userContext->getUser();
		$task = $this->requireTask($taskId);

		$comment = $this->taskCommentProvider->createComment($user, $task, $body);
		return McpTaskCommentDto::fromEntity($comment);
	}

	private function requireTask(int $taskId): Task
	{
		$task = $this->taskProvider->getTask($taskId);
		if ($task === null || !$this->workspaceProvider->isMember($this->userContext->getUser(), $task->project->workspace)) {
			throw new RuntimeException(sprintf('Task %d not found.', $taskId));
		}
		return $task;
	}
}
