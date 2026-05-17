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
use Ukolio\Dto\WorkspaceCreateDto;
use Ukolio\Dto\WorkspaceDto;
use Ukolio\Dto\WorkspaceMemberDto;
use Ukolio\Dto\WorkspaceUpdateDto;
use Ukolio\Model\Entity\Enum\WorkspaceRoleEnum;
use Ukolio\Response\ErrorResponse;
use Ukolio\Response\NotAuthorizedResponse;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Response\OkResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class WorkspaceController
{
	public function __construct(private WorkspaceProviderInterface $workspaceProvider, private RequestServiceInterface $requestService,)
	{
	}

	#[RouteGet(Routes::Workspaces->value)]
	public function actionGetWorkspaces(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);

		$workspaces = [];
		foreach ($this->workspaceProvider->getMemberships($user) as $membership) {
			$workspaces[] = WorkspaceDto::fromEntity($membership->workspace);
		}

		return new JsonResponse($workspaces);
	}

	#[RoutePost(Routes::Workspaces->value)]
	public function actionPostWorkspace(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$dto = $this->requestService->getRequestBodyDto($request, WorkspaceCreateDto::class);

		$name = trim($dto->name);
		if ($name === '') {
			return new ErrorResponse('Workspace name is required.', 422);
		}

		$workspace = $this->workspaceProvider->createWorkspace($user, $name);

		return new JsonResponse(WorkspaceDto::fromEntity($workspace));
	}

	#[RoutePut(Routes::Workspace->value)]
	public function actionPutWorkspace(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$membership = $this->workspaceProvider->findMembership($user, $workspace);
		if ($membership === null || $membership->role !== WorkspaceRoleEnum::Owner) {
			return new NotAuthorizedResponse('Only the owner can update the workspace.');
		}

		$dto = $this->requestService->getRequestBodyDto($request, WorkspaceUpdateDto::class);
		$name = $dto->name !== null ? trim($dto->name) : $workspace->name;
		if ($name === '') {
			return new ErrorResponse('Workspace name is required.', 422);
		}

		$updated = $this->workspaceProvider->updateWorkspace($workspace, $name);

		return new JsonResponse(WorkspaceDto::fromEntity($updated));
	}

	#[RouteDelete(Routes::Workspace->value)]
	public function actionDeleteWorkspace(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if ($workspace->owner->id !== $user->id) {
			return new NotAuthorizedResponse('Only the owner can delete the workspace.');
		}

		$this->workspaceProvider->deleteWorkspace($workspace);

		return new OkResponse();
	}

	#[RoutePost(Routes::WorkspaceSwitch->value)]
	public function actionPostSwitch(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->workspaceProvider->isMember($user, $workspace)) {
			return new NotAuthorizedResponse('You are not a member of this workspace.');
		}

		$this->workspaceProvider->switchCurrentWorkspace($user, $workspace);

		return new JsonResponse(WorkspaceDto::fromEntity($workspace));
	}

	#[RouteGet(Routes::WorkspaceMembers->value)]
	public function actionGetMembers(ServerRequestInterface $request, int $workspaceId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		if (!$this->workspaceProvider->isMember($user, $workspace)) {
			return new NotAuthorizedResponse('You are not a member of this workspace.');
		}

		$members = [];
		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			$members[] = WorkspaceMemberDto::fromEntity($membership);
		}

		return new JsonResponse($members);
	}

	#[RouteDelete(Routes::WorkspaceMember->value)]
	public function actionDeleteMember(ServerRequestInterface $request, int $workspaceId, int $userId): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$workspace = $this->workspaceProvider->getWorkspace($workspaceId);
		if ($workspace === null) {
			return new NotFoundResponse('Workspace not found.');
		}

		$actingMembership = $this->workspaceProvider->findMembership($user, $workspace);
		if ($actingMembership === null) {
			return new NotAuthorizedResponse('You are not a member of this workspace.');
		}

		if ($actingMembership->role !== WorkspaceRoleEnum::Owner && $userId !== $user->id) {
			return new NotAuthorizedResponse('Only the owner can remove other members.');
		}

		if ($workspace->owner->id === $userId) {
			return new ErrorResponse('Cannot remove the workspace owner.', 422);
		}

		foreach ($this->workspaceProvider->getMembers($workspace) as $membership) {
			if ($membership->user->id === $userId) {
				$this->workspaceProvider->removeMember($membership);
				return new OkResponse();
			}
		}

		return new NotFoundResponse('Member not found.');
	}
}
