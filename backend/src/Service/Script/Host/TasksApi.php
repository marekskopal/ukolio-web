<?php

declare(strict_types=1);

namespace Ukolio\Service\Script\Host;

use DateTimeImmutable;
use RuntimeException;
use Ukolio\Mcp\Tool\Helper\PriorityResolver;
use Ukolio\Mcp\Tool\Helper\StatusResolver;
use Ukolio\Model\Entity\Enum\StatusTypeEnum;
use Ukolio\Model\Entity\Project;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Repository\Enum\OrderDirectionEnum;
use Ukolio\Model\Repository\Enum\TaskOrderByEnum;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\TaskCodeResolverInterface;
use Ukolio\Service\Provider\TaskCommentProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;

/**
 * Exposed to JS as `ukolio.tasks`. Every call is authorised as the script's owner and scoped to
 * the run's workspace via the same providers/resolvers the MCP tools use. Counts against the
 * per-run task-API cap.
 */
final readonly class TasksApi
{
	private const int DefaultLimit = 50;
	private const int MaxLimit = 200;

	public function __construct(
		private ScriptRunContext $context,
		private TaskProviderInterface $taskProvider,
		private TaskCodeResolverInterface $taskCodeResolver,
		private ProjectProviderInterface $projectProvider,
		private PriorityResolver $priorityResolver,
		private StatusResolver $statusResolver,
		private TaskCommentProviderInterface $commentProvider,
	) {
	}

	/** @return list<array<string, mixed>> */
	public function list(mixed $filters = null): array
	{
		$this->context->recordTaskApiCall();

		$f = JsValue::toAssoc($filters);
		$limit = min(max(JsValue::int($f['limit'] ?? null) ?? self::DefaultLimit, 1), self::MaxLimit);
		$offset = max(JsValue::int($f['offset'] ?? null) ?? 0, 0);
		$statusIds = array_key_exists('statusIds', $f) ? JsValue::intList($f['statusIds']) : null;

		$out = [];
		$tasks = $this->taskProvider->getTasksInWorkspace(
			$this->context->workspace,
			$limit,
			$offset,
			TaskOrderByEnum::CreatedAt,
			OrderDirectionEnum::Desc,
			JsValue::string($f['search'] ?? null),
			$statusIds,
			(bool) ($f['onlyActive'] ?? false),
		);
		foreach ($tasks as $task) {
			$out[] = HostSerializer::task($task);
		}

		return $out;
	}

	/** @return array<string, mixed>|null */
	public function get(int|string $id): ?array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id);

		return $task === null ? null : HostSerializer::task($task);
	}

	/** @return array<string, mixed> */
	public function create(mixed $input): array
	{
		$this->context->recordTaskApiCall();
		$data = JsValue::toAssoc($input);

		$projectId = JsValue::int($data['projectId'] ?? null) ?? throw new RuntimeException('tasks.create requires a projectId.');
		$name = JsValue::string($data['name'] ?? null) ?? throw new RuntimeException('tasks.create requires a name.');
		$project = $this->requireProject($projectId);

		$priority = $this->priorityResolver->resolve(
			$project,
			JsValue::int($data['priorityId'] ?? null),
			JsValue::string($data['priorityName'] ?? null),
		)
			?? throw new RuntimeException('Workspace has no priorities configured.');

		$status = $this->statusResolver->resolve(
			$project,
			JsValue::int($data['statusId'] ?? null),
			JsValue::string($data['statusName'] ?? null),
		)
			?? $this->statusResolver->findByType($project, StatusTypeEnum::Start)
			?? throw new RuntimeException(sprintf('No Start status found for project %d.', $projectId));

		$dueDate = JsValue::string($data['dueDate'] ?? null);

		$task = $this->taskProvider->createTask(
			author: $this->context->owner,
			project: $project,
			status: $status,
			name: $name,
			description: JsValue::string($data['description'] ?? null),
			priority: $priority,
			dueDate: $dueDate !== null && $dueDate !== '' ? new DateTimeImmutable($dueDate) : null,
			assignee: $this->context->owner,
		);

		return HostSerializer::task($task);
	}

	/** @return array<string, mixed> */
	public function move(int|string $id, string $statusName): array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id) ?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $id));

		$status = $this->statusResolver->resolve($task->project, null, $statusName)
			?? throw new RuntimeException(sprintf('Status "%s" not found.', $statusName));

		$moved = $this->taskProvider->moveTask($this->context->owner, $task, $status, $this->taskProvider->nextPosition($status));

		return HostSerializer::task($moved);
	}

	/** @return array{id: int, body: string} */
	public function addComment(int|string $id, string $body): array
	{
		$this->context->recordTaskApiCall();
		$task = $this->resolve($id) ?? throw new RuntimeException(sprintf('Task "%s" not found.', (string) $id));

		$comment = $this->commentProvider->createComment($this->context->owner, $task, $body);

		return ['id' => $comment->id, 'body' => $comment->body];
	}

	private function resolve(int|string $id): ?Task
	{
		return $this->taskCodeResolver->resolveForUser($this->context->owner, (string) $id);
	}

	private function requireProject(int $projectId): Project
	{
		return $this->projectProvider->getProject($this->context->workspace, $projectId)
			?? throw new RuntimeException(sprintf('Project %d not found.', $projectId));
	}
}
