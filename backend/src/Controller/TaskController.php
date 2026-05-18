<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Ukolio\Dto\TaskCreateDto;
use Ukolio\Dto\TaskDto;
use Ukolio\Dto\TaskListDto;
use Ukolio\Dto\TaskListItemDto;
use Ukolio\Dto\TaskMoveDto;
use Ukolio\Dto\TaskUpdateDto;
use Ukolio\Model\Entity\Task;
use Ukolio\Model\Entity\User;
use Ukolio\Model\Repository\Enum\OrderDirectionEnum;
use Ukolio\Model\Repository\Enum\TaskOrderByEnum;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\StatusProviderInterface;
use Ukolio\Service\Provider\TaskFieldValueProviderInterface;
use Ukolio\Service\Provider\TaskProviderInterface;
use Ukolio\Service\Provider\TaskTagProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;
use const PHP_INT_MAX;

final readonly class TaskController
{
	public function __construct(
		private ProjectProviderInterface $projectProvider,
		private TaskProviderInterface $taskProvider,
		private StatusProviderInterface $statusProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private TaskFieldValueProviderInterface $taskFieldValueProvider,
		private TaskTagProviderInterface $taskTagProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::Tasks->value)]
	public function actionGetWorkspaceTasks(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		$query = $request->getQueryParams();

		$orderBy = $this->resolveOrderBy($query);
		if ($orderBy === null) {
			return new ErrorResponse('Invalid orderBy value.', 400);
		}

		$direction = $this->resolveDirection($query);
		if ($direction === null) {
			return new ErrorResponse('Invalid orderDirection value.', 400);
		}

		$limit = $this->intParam($query, 'limit', 50, 1, 200);
		$offset = $this->intParam($query, 'offset', 0, 0, PHP_INT_MAX);
		$search = $this->stringParam($query, 'search');
		$statusIds = $this->idsParam($query, 'statusIds');
		$tagIds = $this->idsParam($query, 'tagIds');
		$onlyActive = $this->boolParam($query, 'onlyActive');

		$tasks = iterator_to_array(
			$this->taskProvider->getTasksInWorkspace(
				$workspace,
				$limit,
				$offset,
				$orderBy,
				$direction,
				$search,
				$statusIds,
				$onlyActive,
				$tagIds,
			),
			false,
		);

		$count = $this->taskProvider->countTasksInWorkspace($workspace, $search, $statusIds, $onlyActive, $tagIds);

		$tagsByTaskId = $this->taskTagProvider->getTagIdsByTaskIds(array_map(static fn (Task $t): int => $t->id, $tasks));

		return new JsonResponse(new TaskListDto(
			tasks: array_map(
				static fn (Task $t): TaskListItemDto => TaskListItemDto::fromEntity($t, $tagsByTaskId[$t->id] ?? []),
				$tasks,
			),
			count: $count,
		));
	}

	/** @param array<array-key, mixed> $query */
	private function resolveOrderBy(array $query): ?TaskOrderByEnum
	{
		if (!isset($query['orderBy']) || !is_string($query['orderBy'])) {
			return TaskOrderByEnum::CreatedAt;
		}
		return TaskOrderByEnum::tryFrom($query['orderBy']);
	}

	/** @param array<array-key, mixed> $query */
	private function resolveDirection(array $query): ?OrderDirectionEnum
	{
		if (!isset($query['orderDirection']) || !is_string($query['orderDirection'])) {
			return OrderDirectionEnum::Desc;
		}
		return OrderDirectionEnum::tryFrom(strtoupper($query['orderDirection']));
	}

	/** @param array<array-key, mixed> $query */
	private function intParam(array $query, string $key, int $default, int $min, int $max): int
	{
		if (!isset($query[$key]) || !is_string($query[$key])) {
			return $default;
		}
		return max($min, min($max, (int) $query[$key]));
	}

	/** @param array<array-key, mixed> $query */
	private function stringParam(array $query, string $key): ?string
	{
		if (!isset($query[$key]) || !is_string($query[$key]) || $query[$key] === '') {
			return null;
		}
		return $query[$key];
	}

	/** @param array<array-key, mixed> $query */
	private function boolParam(array $query, string $key): bool
	{
		if (!isset($query[$key]) || !is_string($query[$key])) {
			return false;
		}
		return $query[$key] === '1' || $query[$key] === 'true';
	}

	/**
	 * @param array<array-key, mixed> $query
	 * @return list<int>|null
	 */
	private function idsParam(array $query, string $key): ?array
	{
		if (!isset($query[$key]) || !is_string($query[$key]) || $query[$key] === '') {
			return null;
		}
		$parsed = array_values(array_filter(
			array_map('intval', explode('|', $query[$key])),
			static fn (int $id): bool => $id > 0,
		));
		return $parsed === [] ? null : $parsed;
	}

	#[RouteGet(Routes::ProjectTasks->value)]
	public function actionGetTasks(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$projectTasks = iterator_to_array($this->taskProvider->getTasksByProject($project), false);
		$tagsByTaskId = $this->taskTagProvider->getTagIdsByTaskIds(array_map(static fn (Task $t): int => $t->id, $projectTasks));

		$tasks = array_map(
			fn (Task $t): TaskDto => TaskDto::fromEntity(
				$t,
				$this->taskFieldValueProvider->findByTask($t),
				$tagsByTaskId[$t->id] ?? [],
			),
			$projectTasks,
		);

		return new JsonResponse($tasks);
	}

	#[RoutePost(Routes::ProjectTasks->value)]
	public function actionPostTask(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, TaskCreateDto::class);

		$status = $this->statusProvider->getStatus($dto->statusId);
		if ($status === null || $status->workflow->project->id !== $project->id) {
			return new NotFoundResponse('Status not found in this project.');
		}

		try {
			$task = $this->taskProvider->createTask(
				author: $user,
				project: $project,
				status: $status,
				name: $dto->name,
				description: $dto->description,
				priority: $dto->priority,
				dueDate: $dto->dueDate,
				fieldValues: $dto->fieldValues,
				tagIds: $dto->tagIds,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RouteGet(Routes::Task->value)]
	public function actionGetTask(ServerRequestInterface $request, int $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RoutePut(Routes::Task->value)]
	public function actionPutTask(ServerRequestInterface $request, int $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, TaskUpdateDto::class);

		$status = $this->statusProvider->getStatus($dto->statusId);
		if ($status === null || $status->workflow->project->id !== $task->project->id) {
			return new NotFoundResponse('Status not found in this project.');
		}

		try {
			$task = $this->taskProvider->updateTask(
				author: $user,
				task: $task,
				name: $dto->name,
				description: $dto->description,
				priority: $dto->priority,
				dueDate: $dto->dueDate,
				status: $status,
				fieldValues: $dto->fieldValues,
				tagIds: $dto->tagIds,
			);
		} catch (RuntimeException $e) {
			return new ErrorResponse($e->getMessage(), 422);
		}

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RoutePut(Routes::TaskMove->value)]
	public function actionPutTaskMove(ServerRequestInterface $request, int $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, TaskMoveDto::class);

		$newStatus = $this->statusProvider->getStatus($dto->statusId);
		if ($newStatus === null || $newStatus->workflow->project->id !== $task->project->id) {
			return new NotFoundResponse('Status not found in this project.');
		}

		$task = $this->taskProvider->moveTask($user, $task, $newStatus, $dto->position);

		return new JsonResponse(
			TaskDto::fromEntity($task, $this->taskFieldValueProvider->findByTask($task), $this->taskTagProvider->getTagIdsForTask($task)),
		);
	}

	#[RouteDelete(Routes::Task->value)]
	public function actionDeleteTask(ServerRequestInterface $request, int $taskId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$task = $this->loadTaskInScope($user, $taskId);
		if ($task === null) {
			return new NotFoundResponse('Task not found.');
		}

		$this->taskProvider->deleteTask($user, $task);

		return new OkResponse();
	}

	private function loadTaskInScope(User $user, int $taskId): ?Task
	{
		$task = $this->taskProvider->getTask($taskId);
		if ($task === null) {
			return null;
		}

		if (!$this->workspaceProvider->isMember($user, $task->project->workspace)) {
			return null;
		}

		return $task;
	}
}
