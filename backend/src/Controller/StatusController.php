<?php

declare(strict_types=1);

namespace TaskManager\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteDelete;
use MarekSkopal\Router\Attribute\RoutePost;
use MarekSkopal\Router\Attribute\RoutePut;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TaskManager\Dto\StatusCreateDto;
use TaskManager\Dto\StatusDto;
use TaskManager\Dto\StatusMoveDto;
use TaskManager\Dto\StatusUpdateDto;
use TaskManager\Response\ErrorResponse;
use TaskManager\Response\NotFoundResponse;
use TaskManager\Response\OkResponse;
use TaskManager\Route\Routes;
use TaskManager\Service\Provider\StatusProviderInterface;
use TaskManager\Service\Provider\WorkflowProviderInterface;
use TaskManager\Service\Request\RequestServiceInterface;

final readonly class StatusController
{
    public function __construct(
        private WorkflowProviderInterface $workflowProvider,
        private StatusProviderInterface $statusProvider,
        private RequestServiceInterface $requestService,
    ) {
    }

    #[RoutePost(Routes::WorkflowStatuses->value)]
    public function actionPostStatus(ServerRequestInterface $request, int $workflowId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $workflow = $this->workflowProvider->getWorkflow($workflowId);
        if ($workflow === null || $workflow->project->user->id !== $user->id) {
            return new NotFoundResponse('Workflow not found.');
        }

        $dto = $this->requestService->getRequestBodyDto($request, StatusCreateDto::class);

        $status = $this->statusProvider->createStatus(
            workflow: $workflow,
            name: $dto->name,
            color: $dto->color,
            type: $dto->type,
            position: $dto->position,
        );

        return new JsonResponse(StatusDto::fromEntity($status));
    }

    #[RoutePut(Routes::Status->value)]
    public function actionPutStatus(ServerRequestInterface $request, int $statusId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $status = $this->statusProvider->getStatus($statusId);
        if ($status === null || $status->workflow->project->user->id !== $user->id) {
            return new NotFoundResponse('Status not found.');
        }

        $dto = $this->requestService->getRequestBodyDto($request, StatusUpdateDto::class);
        $status = $this->statusProvider->updateStatus($status, $dto->name, $dto->color, $dto->type);

        return new JsonResponse(StatusDto::fromEntity($status));
    }

    #[RoutePut(Routes::StatusMove->value)]
    public function actionPutStatusMove(ServerRequestInterface $request, int $statusId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $status = $this->statusProvider->getStatus($statusId);
        if ($status === null || $status->workflow->project->user->id !== $user->id) {
            return new NotFoundResponse('Status not found.');
        }

        $dto = $this->requestService->getRequestBodyDto($request, StatusMoveDto::class);
        $status = $this->statusProvider->moveStatus($status, $dto->position);

        return new JsonResponse(StatusDto::fromEntity($status));
    }

    #[RouteDelete(Routes::Status->value)]
    public function actionDeleteStatus(ServerRequestInterface $request, int $statusId): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $status = $this->statusProvider->getStatus($statusId);
        if ($status === null || $status->workflow->project->user->id !== $user->id) {
            return new NotFoundResponse('Status not found.');
        }

        $siblings = iterator_to_array($this->statusProvider->getStatuses($status->workflow), false);
        if (count($siblings) <= 1) {
            return new ErrorResponse('Cannot delete the last status of a workflow.', 422);
        }

        $this->statusProvider->deleteStatus($status);

        return new OkResponse();
    }
}
