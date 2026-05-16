<?php

declare(strict_types=1);

namespace TaskManager\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TaskManager\Dto\BoardDto;
use TaskManager\Dto\ProjectDto;
use TaskManager\Dto\StatusDto;
use TaskManager\Dto\TaskDto;
use TaskManager\Dto\WorkflowDto;
use TaskManager\Model\Entity\Status;
use TaskManager\Model\Entity\Task;
use TaskManager\Response\NotFoundResponse;
use TaskManager\Route\Routes;
use TaskManager\Service\Provider\ProjectProviderInterface;
use TaskManager\Service\Provider\StatusProviderInterface;
use TaskManager\Service\Provider\TaskProviderInterface;
use TaskManager\Service\Provider\WorkflowProviderInterface;
use TaskManager\Service\Request\RequestServiceInterface;

final readonly class BoardController
{
    public function __construct(
        private ProjectProviderInterface $projectProvider,
        private WorkflowProviderInterface $workflowProvider,
        private StatusProviderInterface $statusProvider,
        private TaskProviderInterface $taskProvider,
        private RequestServiceInterface $requestService,
    ) {
    }

    #[RouteGet(Routes::ProjectBoard->value)]
    public function actionGetBoard(ServerRequestInterface $request, int $projectId): ResponseInterface
    {
        $project = $this->projectProvider->getProject($this->requestService->getUser($request), $projectId);
        if ($project === null) {
            return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
        }

        $workflow = $this->workflowProvider->getWorkflowByProject($project);
        if ($workflow === null) {
            return new NotFoundResponse('Project has no workflow.');
        }

        $statuses = array_map(
            fn (Status $s): StatusDto => StatusDto::fromEntity($s),
            iterator_to_array($this->statusProvider->getStatuses($workflow), false),
        );

        $tasks = array_map(
            fn (Task $t): TaskDto => TaskDto::fromEntity($t),
            iterator_to_array($this->taskProvider->getTasksByProject($project), false),
        );

        return new JsonResponse(new BoardDto(
            project: ProjectDto::fromEntity($project),
            workflow: WorkflowDto::fromEntity($workflow),
            statuses: $statuses,
            tasks: $tasks,
        ));
    }
}
