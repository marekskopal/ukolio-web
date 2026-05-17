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
use Ukolio\Dto\ProjectCreateDto;
use Ukolio\Dto\ProjectDto;
use Ukolio\Dto\ProjectUpdateDto;
use Ukolio\Model\Entity\Project;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class ProjectController
{
	public function __construct(
		private ProjectProviderInterface $projectProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::Projects->value)]
	public function actionGetProjects(ServerRequestInterface $request): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new JsonResponse([]);
		}

		$projects = array_map(
			fn (Project $p): ProjectDto => ProjectDto::fromEntity($p),
			iterator_to_array($this->projectProvider->getProjects($workspace), false),
		);

		return new JsonResponse($projects);
	}

	#[RouteGet(Routes::Project->value)]
	public function actionGetProject(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		return new JsonResponse(ProjectDto::fromEntity($project));
	}

	#[RoutePost(Routes::Projects->value)]
	public function actionPostProject(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getCurrentWorkspace($user);
		if ($workspace === null) {
			return new ErrorResponse('No active workspace.', 422);
		}

		$dto = $this->requestService->getRequestBodyDto($request, ProjectCreateDto::class);

		$project = $this->projectProvider->createProject($user, $workspace, $dto->name, $dto->description);

		return new JsonResponse(ProjectDto::fromEntity($project));
	}

	#[RoutePut(Routes::Project->value)]
	public function actionPutProject(ServerRequestInterface $request, int $projectId): ResponseInterface
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

		$dto = $this->requestService->getRequestBodyDto($request, ProjectUpdateDto::class);

		$project = $this->projectProvider->updateProject(
			author: $user,
			project: $project,
			name: $dto->name ?? $project->name,
			description: $dto->description ?? $project->description,
		);

		return new JsonResponse(ProjectDto::fromEntity($project));
	}

	#[RouteDelete(Routes::Project->value)]
	public function actionDeleteProject(ServerRequestInterface $request, int $projectId): ResponseInterface
	{
		$workspace = $this->workspaceProvider->getCurrentWorkspace($this->requestService->getUser($request));
		if ($workspace === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$project = $this->projectProvider->getProject($workspace, $projectId);
		if ($project === null) {
			return new NotFoundResponse('Project with id "' . $projectId . '" was not found.');
		}

		$this->projectProvider->deleteProject($project);

		return new OkResponse();
	}
}
