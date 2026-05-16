<?php

declare(strict_types=1);

namespace TaskManager\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TaskManager\Dto\WorkflowDto;
use TaskManager\Dto\WorkflowUpdateDto;
use TaskManager\Response\NotFoundResponse;
use TaskManager\Route\Routes;
use TaskManager\Service\Provider\ProjectProviderInterface;
use TaskManager\Service\Provider\WorkflowProviderInterface;
use TaskManager\Service\Request\RequestServiceInterface;

final readonly class WorkflowController
{
    public function __construct(
        private ProjectProviderInterface $projectProvider,
        private WorkflowProviderInterface $workflowProvider,
        private RequestServiceInterface $requestService,
    ) {
    }

    #[RouteGet(Routes::ProjectWorkflow->value)]
    public function actionGetWorkflow(ServerRequestInterface $request, int $projectId): ResponseInterface
    {
        $project = $this->projectProvider->getProject($this->requestService->getUser($request), $projectId);
        if ($project === null) {
            return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
        }

        $workflow = $this->workflowProvider->getWorkflowByProject($project);
        if ($workflow === null) {
            return new NotFoundResponse('Project has no workflow.');
        }

        return new JsonResponse(WorkflowDto::fromEntity($workflow));
    }

    #[RoutePut(Routes::ProjectWorkflow->value)]
    public function actionPutWorkflow(ServerRequestInterface $request, int $projectId): ResponseInterface
    {
        $project = $this->projectProvider->getProject($this->requestService->getUser($request), $projectId);
        if ($project === null) {
            return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
        }

        $workflow = $this->workflowProvider->getWorkflowByProject($project);
        if ($workflow === null) {
            return new NotFoundResponse('Project has no workflow.');
        }

        $dto = $this->requestService->getRequestBodyDto($request, WorkflowUpdateDto::class);
        $workflow = $this->workflowProvider->updateWorkflow($workflow, $dto->name);

        return new JsonResponse(WorkflowDto::fromEntity($workflow));
    }
}
