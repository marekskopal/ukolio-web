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
use TaskManager\Dto\ProjectCreateDto;
use TaskManager\Dto\ProjectDto;
use TaskManager\Dto\ProjectUpdateDto;
use TaskManager\Model\Entity\Project;
use TaskManager\Response\NotFoundResponse;
use TaskManager\Response\OkResponse;
use TaskManager\Route\Routes;
use TaskManager\Service\Provider\ProjectProviderInterface;
use TaskManager\Service\Request\RequestServiceInterface;

final readonly class ProjectController
{
    public function __construct(
        private ProjectProviderInterface $projectProvider,
        private RequestServiceInterface $requestService,
    ) {
    }

    #[RouteGet(Routes::Projects->value)]
    public function actionGetProjects(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requestService->getUser($request);

        $projects = array_map(
            fn (Project $p): ProjectDto => ProjectDto::fromEntity($p),
            iterator_to_array($this->projectProvider->getProjects($user), false),
        );

        return new JsonResponse($projects);
    }

    #[RouteGet(Routes::Project->value)]
    public function actionGetProject(ServerRequestInterface $request, int $projectId): ResponseInterface
    {
        $project = $this->projectProvider->getProject($this->requestService->getUser($request), $projectId);
        if ($project === null) {
            return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
        }

        return new JsonResponse(ProjectDto::fromEntity($project));
    }

    #[RoutePost(Routes::Projects->value)]
    public function actionPostProject(ServerRequestInterface $request): ResponseInterface
    {
        $user = $this->requestService->getUser($request);
        $dto = $this->requestService->getRequestBodyDto($request, ProjectCreateDto::class);

        $project = $this->projectProvider->createProject($user, $dto->name, $dto->description);

        return new JsonResponse(ProjectDto::fromEntity($project));
    }

    #[RoutePut(Routes::Project->value)]
    public function actionPutProject(ServerRequestInterface $request, int $projectId): ResponseInterface
    {
        $project = $this->projectProvider->getProject($this->requestService->getUser($request), $projectId);
        if ($project === null) {
            return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
        }

        $dto = $this->requestService->getRequestBodyDto($request, ProjectUpdateDto::class);

        $project = $this->projectProvider->updateProject(
            project: $project,
            name: $dto->name ?? $project->name,
            description: $dto->description ?? $project->description,
        );

        return new JsonResponse(ProjectDto::fromEntity($project));
    }

    #[RouteDelete(Routes::Project->value)]
    public function actionDeleteProject(ServerRequestInterface $request, int $projectId): ResponseInterface
    {
        $project = $this->projectProvider->getProject($this->requestService->getUser($request), $projectId);
        if ($project === null) {
            return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
        }

        $this->projectProvider->deleteProject($project);

        return new OkResponse();
    }
}
