<?php

declare(strict_types=1);

namespace TaskManager\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TaskManager\Dto\TaskCreateDto;
use TaskManager\Dto\TaskDto;
use TaskManager\Dto\TaskMoveDto;
use TaskManager\Dto\TaskUpdateDto;
use TaskManager\Model\Entity\Task;
use TaskManager\Response\NotFoundResponse;
use TaskManager\Response\OkResponse;
use TaskManager\Route\Routes;
use TaskManager\Service\Provider\ProjectProviderInterface;
use TaskManager\Service\Provider\StatusProviderInterface;
use TaskManager\Service\Provider\TaskProviderInterface;
use TaskManager\Service\Request\RequestServiceInterface;

final readonly class TaskController
{
    public function __construct(
        private ProjectProviderInterface $projectProvider,
        private TaskProviderInterface $taskProvider,
        private StatusProviderInterface $statusProvider,
        private RequestServiceInterface $requestService,
    ) {
    }

    #[RouteGet(Routes::ProjectTasks->value)]
    public function actionGetTasks(ServerRequestInterface $request, int $projectId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $project = $this->projectProvider->getProject($user, $projectId);
        if ($project === null) {
            return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
        }

        $tasks = array_map(
            fn (Task $t): TaskDto => TaskDto::fromEntity($t),
            iterator_to_array($this->taskProvider->getTasksByProject($project), false),
        );

        return new JsonResponse($tasks);
    }

    #[RoutePost(Routes::ProjectTasks->value)]
    public function actionPostTask(ServerRequestInterface $request, int $projectId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $project = $this->projectProvider->getProject($user, $projectId);
        if ($project === null) {
            return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
        }

        $dto = $this->requestService->getRequestBodyDto($request, TaskCreateDto::class);

        $status = $this->statusProvider->getStatus($dto->statusId);
        if ($status === null || $status->workflow->project->id !== $project->id) {
            return new NotFoundResponse('Status not found in this project.');
        }

        $task = $this->taskProvider->createTask(
            author: $user,
            project: $project,
            status: $status,
            name: $dto->name,
            description: $dto->description,
            priority: $dto->priority,
            dueDate: $dto->dueDate,
        );

        return new JsonResponse(TaskDto::fromEntity($task));
    }

    #[RouteGet(Routes::Task->value)]
    public function actionGetTask(ServerRequestInterface $request, int $taskId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $task = $this->taskProvider->getTask($taskId);
        if ($task === null || $task->project->user->id !== $user->id) {
            return new NotFoundResponse('Task not found.');
        }

        return new JsonResponse(TaskDto::fromEntity($task));
    }

    #[RoutePut(Routes::Task->value)]
    public function actionPutTask(ServerRequestInterface $request, int $taskId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $task = $this->taskProvider->getTask($taskId);
        if ($task === null || $task->project->user->id !== $user->id) {
            return new NotFoundResponse('Task not found.');
        }

        $dto = $this->requestService->getRequestBodyDto($request, TaskUpdateDto::class);

        $status = $this->statusProvider->getStatus($dto->statusId);
        if ($status === null || $status->workflow->project->id !== $task->project->id) {
            return new NotFoundResponse('Status not found in this project.');
        }

        $task = $this->taskProvider->updateTask(
            author: $user,
            task: $task,
            name: $dto->name,
            description: $dto->description,
            priority: $dto->priority,
            dueDate: $dto->dueDate,
            status: $status,
        );

        return new JsonResponse(TaskDto::fromEntity($task));
    }

    #[RoutePut(Routes::TaskMove->value)]
    public function actionPutTaskMove(ServerRequestInterface $request, int $taskId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $task = $this->taskProvider->getTask($taskId);
        if ($task === null || $task->project->user->id !== $user->id) {
            return new NotFoundResponse('Task not found.');
        }

        $dto = $this->requestService->getRequestBodyDto($request, TaskMoveDto::class);

        $newStatus = $this->statusProvider->getStatus($dto->statusId);
        if ($newStatus === null || $newStatus->workflow->project->id !== $task->project->id) {
            return new NotFoundResponse('Status not found in this project.');
        }

        $task = $this->taskProvider->moveTask($user, $task, $newStatus, $dto->position);

        return new JsonResponse(TaskDto::fromEntity($task));
    }

    #[RouteDelete(Routes::Task->value)]
    public function actionDeleteTask(ServerRequestInterface $request, int $taskId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $task = $this->taskProvider->getTask($taskId);
        if ($task === null || $task->project->user->id !== $user->id) {
            return new NotFoundResponse('Task not found.');
        }

        $this->taskProvider->deleteTask($user, $task);

        return new OkResponse();
    }
}
