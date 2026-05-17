<?php

declare(strict_types=1);

namespace Ukolio\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use MarekSkopal\Router\Attribute\RoutePatch;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Ukolio\Dto\CurrentUserUpdateDto;
use Ukolio\Dto\UserDto;
use Ukolio\Model\Entity\Enum\LocaleEnum;
use Ukolio\Response\ErrorResponse;
use Ukolio\Route\Routes;
use Ukolio\Service\Provider\UserProviderInterface;
use Ukolio\Service\Request\RequestServiceInterface;

final readonly class CurrentUserController
{
	public function __construct(private UserProviderInterface $userProvider, private RequestServiceInterface $requestService,)
	{
	}

	#[RouteGet(Routes::CurrentUser->value)]
	public function actionGetCurrentUser(ServerRequestInterface $request): ResponseInterface
	{
		return new JsonResponse(UserDto::fromEntity($this->requestService->getUser($request)));
	}

	#[RoutePatch(Routes::CurrentUser->value)]
	public function actionPatchCurrentUser(ServerRequestInterface $request): ResponseInterface
	{
		$user = $this->requestService->getUser($request);
		$dto = $this->requestService->getRequestBodyDto($request, CurrentUserUpdateDto::class);

		$locale = null;
		if ($dto->locale !== null) {
			$locale = LocaleEnum::tryFrom($dto->locale);
			if ($locale === null) {
				return new ErrorResponse('Unsupported locale.', 422);
			}
		}

		$name = $dto->name !== null ? trim($dto->name) : null;
		if ($name === '') {
			$name = null;
		}

		$updated = $this->userProvider->updateUser($user, $name, $locale);

		return new JsonResponse(UserDto::fromEntity($updated));
	}
}
