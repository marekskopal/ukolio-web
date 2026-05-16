<?php

declare(strict_types=1);

namespace TaskManager\Controller;

use Laminas\Diactoros\Response\JsonResponse;
use MarekSkopal\Router\Attribute\RouteGet;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TaskManager\Dto\UserDto;
use TaskManager\Route\Routes;
use TaskManager\Service\Request\RequestServiceInterface;

final readonly class CurrentUserController
{
    public function __construct(private RequestServiceInterface $requestService)
    {
    }

    #[RouteGet(Routes::CurrentUser->value)]
    public function actionGetCurrentUser(ServerRequestInterface $request): ResponseInterface
    {
        return new JsonResponse(UserDto::fromEntity($this->requestService->getUser($request)));
    }
}
