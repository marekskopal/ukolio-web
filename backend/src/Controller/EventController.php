<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Dto\EventDto;
use Ukolio\Model\Entity\Event;
use Ukolio\Response\NotFoundResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\EventProviderInterface;
use Ukolio\Service\Provider\ProjectProviderInterface;
use Ukolio\Service\Provider\WorkspaceProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class EventController
{
	public function __construct(
		private ProjectProviderInterface $projectProvider,
		private EventProviderInterface $eventProvider,
		private WorkspaceProviderInterface $workspaceProvider,
		private RequestServiceInterface $requestService,
	) {
	}

	#[RouteGet(Routes::ProjectEvents->value)]
	public function actionGetEvents(ServerRequestInterface $request, int $projectId): ResponseInterface
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

		$query = $request->getQueryParams();
		$limit = is_numeric($query['limit'] ?? null) ? (int) $query['limit'] : 100;
		$offset = is_numeric($query['offset'] ?? null) ? (int) $query['offset'] : 0;

		$events = array_map(
			fn (Event $e): EventDto => EventDto::fromEntity($e),
			iterator_to_array($this->eventProvider->getEvents($project, $limit, $offset), false),
		);

		return new JsonResponse($events);
	}
}
